<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\NotificationRule;
use App\Models\Organization;
use App\Support\Fault\IssueAlertNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpikeAlertTest extends TestCase
{
    use RefreshDatabase;

    protected FaultIssue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')->discord()->create();
        NotificationRule::factory()->spike(['window_minutes' => 5, 'multiplier' => 10, 'min_count' => 20])->create(['notification_channel_id' => $channel->id]);
        $this->issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);
    }

    protected function events(int $count, $occurredAt): void
    {
        FaultEvent::factory()->count($count)->create([
            'fault_project_id' => $this->issue->fault_project_id,
            'fault_issue_id' => $this->issue->id,
            'occurred_at' => $occurredAt,
        ]);
    }

    protected function notify(): void
    {
        app(IssueAlertNotifier::class)->notify($this->issue, false, false);
    }

    public function test_a_spike_over_the_baseline_fires(): void
    {
        $this->events(288, now()->subHours(3)); // 1 per 5 minutes on average
        $this->events(25, now()->subMinute());

        $this->notify();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['content'], 'Spike'));
    }

    public function test_below_the_minimum_count_does_not_fire(): void
    {
        $this->events(10, now()->subMinute());

        $this->notify();

        Http::assertNothingSent();
    }

    public function test_a_high_baseline_rate_does_not_fire(): void
    {
        $this->events(2880, now()->subHours(3)); // 10 per 5 minutes on average
        $this->events(25, now()->subMinute());

        $this->notify();

        Http::assertNothingSent();
    }

    public function test_it_fires_at_most_once_per_hour_per_channel(): void
    {
        $this->events(25, now()->subMinute());

        $this->notify();
        Cache::forget("fault:spike-eval:{$this->issue->id}");
        $this->notify();

        Http::assertSentCount(1);
    }

    public function test_the_evaluation_lock_skips_back_to_back_checks(): void
    {
        $this->events(5, now()->subMinute());
        $this->notify();

        $this->events(25, now()->subMinute());
        $this->notify();

        Http::assertNothingSent();
    }
}
