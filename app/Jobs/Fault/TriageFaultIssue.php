<?php

namespace App\Jobs\Fault;

use App\Models\FaultIssue;
use App\Support\Ai\IssueTriager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class TriageFaultIssue implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $faultIssueId)
    {
        $this->onQueue('default');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(IssueTriager $triager): void
    {
        $issue = FaultIssue::with('project.organization')->find($this->faultIssueId);

        if (! $issue || $issue->triaged_at || ! $issue->project->organization->canTriageIssues()) {
            return;
        }

        $rateKey = self::rateLimitKey($issue->project->organization_id);

        if (RateLimiter::tooManyAttempts($rateKey, (int) config('fault.ai_triage_hourly_limit'))) {
            return;
        }

        RateLimiter::hit($rateKey, 3600);

        $triager->triage($issue);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    public static function rateLimitKey(int $organizationId): string
    {
        return "ai-triage:{$organizationId}";
    }
}
