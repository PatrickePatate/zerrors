<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Support\Fault\EventFingerprinter;
use App\Support\Fault\IssueTagFacets;
use Illuminate\Support\Facades\Redis;

class ThrownExceptionAndUsersTest extends IngestExtrasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->del('fault:dirty-issues');
    }

    public function test_title_type_and_handled_use_the_last_exception_value(): void
    {
        $project = FaultProject::factory()->create();
        $payload = $this->errorPayload(str_repeat('b', 32), ['exception' => ['values' => [
            ['type' => 'PDOException', 'value' => 'inner', 'mechanism' => ['handled' => true]],
            ['type' => 'RuntimeException', 'value' => 'outer', 'mechanism' => ['handled' => false]],
        ]]]);

        $this->sendEnvelope($project, [[['type' => 'event'], json_encode($payload)]])->assertOk();

        $issue = FaultIssue::firstOrFail();
        $this->assertSame('RuntimeException', $issue->type);
        $this->assertSame('RuntimeException', $issue->title);
        $this->assertSame('RuntimeException', EventFingerprinter::title($payload));
        $this->assertFalse($issue->latest_event_handled);
        $this->assertFalse(FaultEvent::firstOrFail()->handled());
        $this->assertSame('outer', FaultEvent::firstOrFail()->message);
    }

    public function test_user_identifier_prefers_id_then_email_then_username_then_ip(): void
    {
        $project = FaultProject::factory()->create();
        $users = [
            'c' => ['id' => 42, 'email' => 'a@x.test'],
            'd' => ['email' => 'a@x.test', 'username' => 'bob'],
            'e' => ['username' => 'bob', 'ip_address' => '10.0.0.1'],
            'f' => ['ip_address' => '10.0.0.2'],
        ];
        $expected = ['c' => '42', 'd' => 'a@x.test', 'e' => 'bob', 'f' => '10.0.0.2'];

        foreach ($users as $letter => $user) {
            $id = str_repeat($letter, 32);
            $this->sendEnvelope($project, [[['type' => 'event'], json_encode($this->errorPayload($id, ['user' => $user]))]]);
            $this->assertSame($expected[$letter], FaultEvent::where('event_id', 'like', "{$letter}%")->firstOrFail()->user_identifier);
        }
    }

    public function test_flush_writes_hyperloglog_cardinality_to_users_affected(): void
    {
        $project = FaultProject::factory()->create();

        foreach (['u1', 'u2', 'u1', 'u3'] as $index => $user) {
            $id = str_repeat((string) $index, 32);
            $this->sendEnvelope($project, [[['type' => 'event'], json_encode($this->errorPayload($id, ['user' => ['id' => $user]]))]]);
        }

        $issue = FaultIssue::firstOrFail();
        $this->assertSame(0, $issue->users_affected);

        $this->artisan('fault:flush-issue-counters')->assertSuccessful();

        $this->assertSame(3, $issue->fresh()->users_affected);
        $this->assertSame(4, $issue->fresh()->times_seen);
        Redis::connection()->del("fault:issue:{$issue->id}:users");
    }

    public function test_top_tags_facets_show_percentages(): void
    {
        $issue = FaultIssue::factory()->create();

        foreach (['chrome', 'chrome', 'chrome', 'firefox'] as $browser) {
            FaultEvent::factory()->create([
                'fault_project_id' => $issue->fault_project_id,
                'fault_issue_id' => $issue->id,
                'tags' => ['browser' => $browser],
            ]);
        }

        $facets = app(IssueTagFacets::class)->for($issue);

        $this->assertSame('browser', $facets[0]['key']);
        $this->assertSame(['chrome', 75.0], [$facets[0]['values'][0]['value'], $facets[0]['values'][0]['percent']]);
    }
}
