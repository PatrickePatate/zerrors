<?php

namespace Tests\Feature\Fault;

use App\Enums\NotificationRuleTrigger;
use App\Jobs\Fault\ForwardFaultEvent;
use App\Jobs\Fault\ProcessFaultEvent;
use App\Jobs\Fault\SendIssueAlert;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Support\Fault\EventPayloadCensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class FaultIngestPipelineHardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $extra = []): array
    {
        return $extra + [
            'event_id' => Str::random(10),
            'level' => 'error',
            'fingerprint' => ['same-issue'],
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
        ];
    }

    protected function process(FaultProject $project, array $payload): void
    {
        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $payload);
    }

    public function test_forwarding_is_only_dispatched_when_configured(): void
    {
        Bus::fake([ForwardFaultEvent::class, SendIssueAlert::class]);

        $off = FaultProject::factory()->create(['forward_enabled' => false]);
        $on = FaultProject::factory()->create(['forward_enabled' => true, 'forward_dsn' => 'https://key@o1.ingest.sentry.io/1']);

        $this->process($off, $this->payload());
        $this->process($on, $this->payload());

        Bus::assertDispatchedTimes(ForwardFaultEvent::class, 1);
        Bus::assertDispatched(ForwardFaultEvent::class, fn ($job) => $job->projectId === $on->id);
    }

    public function test_an_event_for_a_deleted_project_is_dropped_silently(): void
    {
        Bus::fake([ForwardFaultEvent::class, SendIssueAlert::class]);

        ProcessFaultEvent::dispatch(999999, (string) Str::uuid(), $this->payload());

        $this->assertDatabaseCount('fault_events', 0);
        Bus::assertNothingDispatched();
    }

    public function test_alerts_are_queued_with_the_exact_times_seen_on_the_default_queue(): void
    {
        Bus::fake([SendIssueAlert::class]);

        $project = FaultProject::factory()->create();

        $this->process($project, $this->payload());

        Bus::assertDispatched(SendIssueAlert::class, fn ($job) => $job->wasNew
            && ! $job->isRegression
            && $job->timesSeen === 1
            && $job->queue === 'default');
    }

    public function test_a_plain_repeat_event_with_no_alert_channel_dispatches_no_alert_job(): void
    {
        Bus::fake([SendIssueAlert::class]);

        $project = FaultProject::factory()->create();

        $this->process($project, $this->payload());
        $this->process($project, $this->payload());

        Bus::assertDispatchedTimes(SendIssueAlert::class, 1);
    }

    public function test_a_repeat_event_dispatches_an_alert_job_when_the_project_has_an_enabled_channel(): void
    {
        Bus::fake([SendIssueAlert::class]);

        $project = FaultProject::factory()->create();
        NotificationChannel::factory()->for($project, 'project')->slack('C1', 'alerts')->create();

        $this->process($project, $this->payload());
        $this->process($project, $this->payload());

        Bus::assertDispatchedTimes(SendIssueAlert::class, 2);
        Bus::assertDispatched(SendIssueAlert::class, fn ($job) => $job->timesSeen === 2 && ! $job->wasNew);
    }

    public function test_every_event_alerts_are_throttled_per_channel_and_issue(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')->slack('C1', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::EveryEvent]);

        for ($i = 0; $i < 4; $i++) {
            $this->process($project, $this->payload());
        }

        Http::assertSentCount(1);
    }

    public function test_the_cooldown_never_throttles_new_issue_or_threshold_notifications(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')->slack('C1', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::EveryEvent]);
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::OccurrenceThreshold, 'thresholds' => [3]]);

        for ($i = 0; $i < 3; $i++) {
            $this->process($project, $this->payload());
        }

        // Occurrence 1 (every event), 2 throttled, 3 reaches the threshold despite the cooldown.
        Http::assertSentCount(2);
    }

    public function test_the_payload_column_only_stores_keys_without_a_dedicated_column(): void
    {
        $project = FaultProject::factory()->create();

        $this->process($project, $this->payload([
            'user' => ['id' => '7'],
            'tags' => ['a' => 'b'],
            'request' => ['url' => 'https://x.test', 'headers' => ['user-agent' => 'Firefox/1']],
            'breadcrumbs' => ['values' => [['message' => 'hi']]],
        ]));

        $event = FaultEvent::firstOrFail();
        $stored = json_decode(DB::table('fault_events')->value('payload'), true);

        $this->assertArrayHasKey('user', $stored);
        $this->assertArrayNotHasKey('exception', $stored);
        $this->assertArrayNotHasKey('request', $stored);
        $this->assertArrayNotHasKey('tags', $stored);
        $this->assertArrayNotHasKey('breadcrumbs', $stored);

        $this->assertSame(['id' => '7'], $event->contextUser());
        $this->assertSame('boom', $event->payload['exception']['values'][0]['value']);
        $this->assertSame('https://x.test', $event->payload['request']['url']);
        $this->assertSame([['message' => 'hi']], $event->payload['breadcrumbs']['values']);
    }

    public function test_legacy_rows_with_a_full_payload_still_render(): void
    {
        $project = FaultProject::factory()->create();
        $issue = FaultIssue::factory()->for($project, 'project')->create();

        $event = FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'payload' => ['user' => ['id' => '1'], 'exception' => ['values' => [['type' => 'Old']]]],
        ]);

        $fresh = FaultEvent::find($event->id);
        $this->assertSame(['id' => '1'], $fresh->contextUser());
        $this->assertSame('Old', $fresh->payload['exception']['values'][0]['type']);
    }

    public function test_the_forwarded_payload_is_the_full_censored_payload(): void
    {
        Bus::fake([ForwardFaultEvent::class, SendIssueAlert::class]);

        $project = FaultProject::factory()->create(['forward_enabled' => true, 'forward_dsn' => 'https://key@o1.ingest.sentry.io/1']);

        $this->process($project, $this->payload(['request' => ['headers' => ['Authorization' => 'x'], 'data' => ['password' => 'p']]]));

        Bus::assertDispatched(ForwardFaultEvent::class, fn ($job) => isset($job->payload['exception'])
            && $job->payload['request']['headers']['Authorization'] === '<CENSORED>'
            && $job->payload['request']['data']['password'] === '<CENSORED>');
    }

    public function test_the_censor_redacts_sensitive_keys_across_the_payload(): void
    {
        $result = EventPayloadCensor::redact([
            'request' => [
                'headers' => ['Authorization' => 'a', 'X-Custom' => 'b'],
                'data' => ['user' => 'bob', 'Password' => 'p', 'nested' => ['API_KEY' => 'k', 'ok' => 1, 'classname' => 'Foo']],
                'cookies' => ['sid' => 'abc', 'theme' => 'dark'],
                'query_string' => 'page=2&access_token=abc%20d&q=x',
                'env' => ['REMOTE_ADDR' => '1.2.3.4', 'DB_SECRET' => 's'],
            ],
            'extra' => ['credit_card' => '4111', 'cvv' => '123', 'ssn' => '1', 'note' => 'fine', 'list' => [['refresh-token' => 'r']]],
            'contexts' => ['app' => ['private_key' => 'pk', 'name' => 'zerrors']],
        ], ['X-Custom', 'Authorization']);

        $c = EventPayloadCensor::CENSORED_VALUE;

        $this->assertSame($c, $result['request']['headers']['Authorization']);
        $this->assertSame($c, $result['request']['headers']['X-Custom']);
        $this->assertSame('bob', $result['request']['data']['user']);
        $this->assertSame($c, $result['request']['data']['Password']);
        $this->assertSame($c, $result['request']['data']['nested']['API_KEY']);
        $this->assertSame('Foo', $result['request']['data']['nested']['classname']);
        $this->assertSame(['sid' => $c, 'theme' => $c], $result['request']['cookies']);
        $this->assertSame("page=2&access_token=$c&q=x", $result['request']['query_string']);
        $this->assertSame('1.2.3.4', $result['request']['env']['REMOTE_ADDR']);
        $this->assertSame($c, $result['request']['env']['DB_SECRET']);
        $this->assertSame($c, $result['extra']['credit_card']);
        $this->assertSame($c, $result['extra']['cvv']);
        $this->assertSame($c, $result['extra']['ssn']);
        $this->assertSame('fine', $result['extra']['note']);
        $this->assertSame($c, $result['extra']['list'][0]['refresh-token']);
        $this->assertSame($c, $result['contexts']['app']['private_key']);
        $this->assertSame('zerrors', $result['contexts']['app']['name']);
    }

    public function test_the_censor_handles_array_query_strings_and_string_cookies(): void
    {
        $c = EventPayloadCensor::CENSORED_VALUE;

        $result = EventPayloadCensor::redact(['request' => [
            'query_string' => [['page', '2'], ['token', 'abc']],
            'cookies' => 'a=1; b=2',
        ]], []);

        $this->assertSame([['page', '2'], ['token', $c]], $result['request']['query_string']);
        $this->assertSame("a=$c; b=$c", $result['request']['cookies']);
    }

    public function test_pending_last_seen_never_moves_backwards_in_redis(): void
    {
        $project = FaultProject::factory()->create();

        $this->process($project, $this->payload(['timestamp' => 2_000_000_000]));
        $this->process($project, $this->payload(['timestamp' => 1_000_000_000]));

        $issue = FaultIssue::firstOrFail();
        $this->assertSame(2_000_000_000, (int) Redis::connection()->get("fault:issue:{$issue->id}:pending_last_seen"));
    }

    public function test_flush_never_moves_last_seen_at_backwards_and_cleans_up_redis_keys(): void
    {
        $project = FaultProject::factory()->create();
        $recent = now()->startOfSecond();
        $issue = FaultIssue::factory()->for($project, 'project')->create(['times_seen' => 1, 'last_seen_at' => $recent]);

        $redis = Redis::connection();
        $redis->set("fault:issue:{$issue->id}:pending_count", 2);
        $redis->set("fault:issue:{$issue->id}:pending_last_seen", $recent->copy()->subDay()->getTimestamp());
        $redis->sadd('fault:dirty-issues', $issue->id);

        $this->artisan('fault:flush-issue-counters')->assertSuccessful();

        $issue->refresh();
        $this->assertSame(3, $issue->times_seen);
        $this->assertSame($recent->getTimestamp(), $issue->last_seen_at->getTimestamp());
        $this->assertSame(0, $redis->exists("fault:issue:{$issue->id}:pending_count"));
        $this->assertSame(0, $redis->exists("fault:issue:{$issue->id}:pending_last_seen"));
        $this->assertSame([], $redis->smembers('fault:dirty-issues'));
    }

    public function test_a_failed_flush_keeps_the_pending_count_for_the_next_run(): void
    {
        $project = FaultProject::factory()->create();
        $issue = FaultIssue::factory()->for($project, 'project')->create(['times_seen' => 1]);

        $redis = Redis::connection();
        $redis->set("fault:issue:{$issue->id}:pending_count", 4);
        $redis->set("fault:issue:{$issue->id}:pending_last_seen", now()->getTimestamp());
        $redis->sadd('fault:dirty-issues', $issue->id);

        DB::beforeExecuting(function (string $query) {
            if (str_starts_with($query, 'update `fault_issues`') || str_starts_with($query, 'update "fault_issues"')) {
                throw new \RuntimeException('db down');
            }
        });

        $this->withoutExceptionHandling();
        $this->artisan('fault:flush-issue-counters');

        $this->assertSame(1, $issue->fresh()->times_seen);
        $this->assertSame(4, (int) $redis->get("fault:issue:{$issue->id}:pending_count"));
        $this->assertContains((string) $issue->id, $redis->smembers('fault:dirty-issues'));
    }
}
