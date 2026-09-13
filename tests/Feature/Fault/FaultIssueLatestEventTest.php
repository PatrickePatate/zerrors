<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FaultIssueLatestEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_event_resolves_the_most_recently_occurred_event(): void
    {
        $issue = FaultIssue::factory()->create();

        $older = FaultEvent::factory()->create([
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subHour(),
        ]);
        $newer = FaultEvent::factory()->create([
            'fault_issue_id' => $issue->id,
            'occurred_at' => now(),
        ]);

        $this->assertTrue($issue->latestEvent->is($newer));
        $this->assertFalse($issue->latestEvent->is($older));
    }

    public function test_is_log_is_true_only_when_the_issue_has_no_exception_type(): void
    {
        $logIssue = FaultIssue::factory()->create(['type' => null]);
        $exceptionIssue = FaultIssue::factory()->create(['type' => 'RuntimeException']);

        $this->assertTrue($logIssue->isLog());
        $this->assertFalse($exceptionIssue->isLog());
    }

    public function test_eager_loading_latest_event_avoids_an_n_plus_one_across_many_issues(): void
    {
        $project = FaultProject::factory()->create();

        FaultIssue::factory()->count(5)->create(['fault_project_id' => $project->id])
            ->each(fn (FaultIssue $issue) => FaultEvent::factory()->create([
                'fault_issue_id' => $issue->id,
                'exception' => ['values' => [[
                    'type' => 'Exception',
                    'stacktrace' => ['frames' => [
                        ['filename' => 'app/Livewire/Widget.php', 'function' => 'render'],
                    ]],
                ]]],
            ]));

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $issues = $project->issues()->with('latestEvent:'.FaultEvent::BADGE_COLUMNS)->get();

        foreach ($issues as $issue) {
            $issue->latestEvent->livewireComponent();
        }

        // One query for the issues, one for the batched latestEvent eager load —
        // not one extra query per issue.
        $this->assertSame(2, $queries);
        $this->assertSame('App\Livewire\Widget', $issues->first()->latestEvent->livewireComponent());
    }
}
