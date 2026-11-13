<?php

namespace App\Console\Commands;

use App\Mail\OrganizationDigestMail;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

#[Signature('zerrors:send-digests {frequency : daily or weekly}')]
#[Description('Email each opted-in organization member a digest of new issues, regressions and top issues for the period.')]
class SendDigests extends Command
{
    public function handle(): int
    {
        $frequency = (string) $this->argument('frequency');

        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            $this->error('Frequency must be "daily" or "weekly".');

            return self::INVALID;
        }

        $end = now();
        $start = $frequency === 'daily' ? $end->copy()->subDay() : $end->copy()->subWeek();
        $previousStart = $start->copy()->sub($start->diffAsCarbonInterval($end));

        $sent = 0;

        Organization::query()->each(function (Organization $organization) use ($frequency, $start, $end, $previousStart, &$sent): void {
            $recipients = $organization->users()->wherePivot('digest_frequency', $frequency)->get();

            if ($recipients->isEmpty()) {
                return;
            }

            $projects = $organization->projects->map(
                fn (FaultProject $project) => $this->summarise($project, $start, $end, $previousStart)
            )->filter()->values()->all();

            if ($projects === []) {
                return;
            }

            foreach ($recipients as $user) {
                Mail::to($user)->send(new OrganizationDigestMail($organization, $frequency, $start, $end, $projects));
                $sent++;
            }
        });

        $this->info("Sent {$sent} {$frequency} digest(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null Null when the project has nothing to report.
     */
    protected function summarise(FaultProject $project, Carbon $start, Carbon $end, Carbon $previousStart): ?array
    {
        $newIssues = FaultIssue::query()
            ->where('fault_project_id', $project->id)
            ->whereBetween('first_seen_at', [$start, $end])
            ->count();

        $regressions = FaultIssue::query()
            ->where('fault_project_id', $project->id)
            ->whereBetween('regressed_at', [$start, $end])
            ->count();

        $events = FaultEvent::query()
            ->where('fault_project_id', $project->id)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->count();

        if ($newIssues === 0 && $regressions === 0 && $events === 0) {
            return null;
        }

        $previousEvents = FaultEvent::query()
            ->where('fault_project_id', $project->id)
            ->where('occurred_at', '>=', $previousStart)
            ->where('occurred_at', '<', $start)
            ->count();

        $topCounts = FaultEvent::query()
            ->where('fault_project_id', $project->id)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->selectRaw('fault_issue_id, count(*) as aggregate')
            ->groupBy('fault_issue_id')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->pluck('aggregate', 'fault_issue_id');

        $issues = FaultIssue::query()->whereIn('id', $topCounts->keys())->get()->keyBy('id');

        return [
            'name' => $project->name,
            'new_issues' => $newIssues,
            'regressions' => $regressions,
            'events' => $events,
            'previous_events' => $previousEvents,
            'top_issues' => $topCounts->map(fn ($count, $issueId) => [
                'title' => $issues[$issueId]->title ?? 'Unknown issue',
                'count' => (int) $count,
                'url' => isset($issues[$issueId])
                    ? route('organizations.issues.show', [$project->organization, $project, $issues[$issueId]])
                    : null,
            ])->values()->all(),
        ];
    }
}
