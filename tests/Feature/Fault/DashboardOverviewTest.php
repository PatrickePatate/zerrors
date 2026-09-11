<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_the_organization_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $recentIssue = FaultIssue::factory()->create([
            'fault_project_id' => $project->id,
            'title' => 'Recent issue',
            'last_seen_at' => now(),
        ]);
        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $recentIssue->id,
            'occurred_at' => now()->subHours(2),
        ]);
        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $recentIssue->id,
            'occurred_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($member)->get(route('organizations.overview', $organization));

        $response->assertOk();
        $response->assertSee('Recent issue');
        $response->assertSee('Errors (24h)');
    }

    public function test_non_member_cannot_view_the_organization_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('organizations.overview', $organization))
            ->assertForbidden();
    }
}
