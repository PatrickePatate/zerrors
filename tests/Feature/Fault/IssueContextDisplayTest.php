<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueContextDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_user_request_and_context_data_for_the_latest_event(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'request' => ['url' => 'https://example.test/checkout', 'method' => 'POST'],
            'contexts' => ['os' => ['name' => 'Linux']],
            'extra' => ['order_id' => 'ord_123'],
            'payload' => ['user' => ['id' => '1', 'email' => 'demo@example.com']],
        ]);

        $this->actingAs($user)
            ->get(route('organizations.issues.show', [$organization, $project, $issue]))
            ->assertOk()
            ->assertSeeText('User & context')
            ->assertSeeText('demo@example.com')
            ->assertSeeText('example.test/checkout')
            ->assertSeeText('Linux')
            ->assertSeeText('ord_123');
    }

    public function test_it_shows_a_fallback_message_without_context_data(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
        ]);

        $this->actingAs($user)
            ->get(route('organizations.issues.show', [$organization, $project, $issue]))
            ->assertOk()
            ->assertSeeText('No user or context data captured for this event.');
    }
}
