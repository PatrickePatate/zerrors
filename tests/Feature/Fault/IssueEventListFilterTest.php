<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueEventListFilterTest extends TestCase
{
    use RefreshDatabase;

    private const FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0';

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';

    public function test_client_details_are_derived_when_an_event_is_created(): void
    {
        $event = FaultEvent::factory()->create([
            'payload' => ['user' => ['ip_address' => '{{auto}}']],
            'request' => ['headers' => ['user-agent' => self::CHROME, 'x-forwarded-for' => '203.0.113.7, 10.0.0.1']],
        ]);

        $this->assertSame('203.0.113.7', $event->client_ip);
        $this->assertSame('Chrome', $event->browser);
        $this->assertSame('Windows', $event->os);
    }

    public function test_events_can_be_filtered_by_ip_and_browser(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $make = fn (string $message, string $ip, string $agent) => FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'message' => $message,
            'payload' => ['user' => ['ip_address' => $ip]],
            'request' => ['headers' => ['user-agent' => $agent]],
        ]);

        $make('from firefox user', '198.51.100.1', self::FIREFOX);
        $make('from chrome user', '203.0.113.9', self::CHROME);

        $url = route('organizations.issues.events.show', [$organization, $project, $issue, 'latest']);

        $this->actingAs($user)->get($url.'?browser=Firefox')
            ->assertOk()
            ->assertSee('from firefox user')
            ->assertDontSee('from chrome user');

        $this->actingAs($user)->get($url.'?ip=203.0')
            ->assertOk()
            ->assertSee('from chrome user')
            ->assertDontSee('from firefox user');
    }
}
