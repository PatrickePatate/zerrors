<?php

namespace Tests\Feature\Api;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IssueApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private FaultProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create();
        $this->organization->users()->attach($this->user->id, ['role' => 'member']);
        $this->project = FaultProject::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function base(?FaultProject $project = null, ?Organization $organization = null): string
    {
        return '/api/v1/organizations/'.($organization ?? $this->organization)->slug.'/projects/'.($project ?? $this->project)->slug;
    }

    public function test_unauthenticated_requests_get_401_json(): void
    {
        $this->getJson($this->base().'/issues')->assertUnauthorized();
        $this->patchJson($this->base().'/issues/1', [])->assertUnauthorized();
    }

    public function test_issues_can_be_filtered_and_paginated(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        FaultIssue::factory()->create(['fault_project_id' => $this->project->id, 'title' => 'Boom 100% fail', 'status' => 'resolved', 'level' => 'fatal', 'last_seen_at' => now()->subDays(10)]);
        FaultIssue::factory()->create(['fault_project_id' => $this->project->id, 'title' => 'Boom other', 'last_seen_at' => now()]);
        FaultIssue::factory()->create(['fault_project_id' => $this->project->id, 'title' => 'Unrelated']);

        $this->getJson($this->base().'/issues?status=resolved')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->base().'/issues?level=fatal')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->base().'/issues?q=Boom')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($this->base().'/issues?q=100%25')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->base().'/issues?q=%25')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->base().'/issues?since='.now()->subDays(2)->toDateString())->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($this->base().'/issues?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1);
        $this->getJson($this->base().'/issues?per_page=101')->assertStatus(422);
        $this->getJson($this->base().'/issues?status=bogus')->assertStatus(422);
    }

    public function test_a_write_token_can_update_status_and_assignee_with_audit_logs(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);

        $this->patchJson($this->base()."/issues/{$issue->id}", ['status' => 'resolved', 'assigned_to_user_id' => $this->user->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.assigned_to_user_id', $this->user->id);

        $this->assertDatabaseHas('audit_logs', ['organization_id' => $this->organization->id, 'action' => 'issue.status_updated']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $this->organization->id, 'action' => 'issue.assigned']);

        $this->patchJson($this->base()."/issues/{$issue->id}", ['assigned_to_user_id' => null])
            ->assertOk()
            ->assertJsonPath('data.assigned_to_user_id', null);
    }

    public function test_a_full_access_legacy_token_can_write(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);

        $this->patchJson($this->base()."/issues/{$issue->id}", ['status' => 'ignored'])->assertOk();
    }

    public function test_a_read_only_token_cannot_mutate(): void
    {
        Sanctum::actingAs($this->user, ['read']);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);

        $this->patchJson($this->base()."/issues/{$issue->id}", ['status' => 'resolved'])->assertForbidden();
        $this->postJson($this->base().'/releases', ['version' => '1.0'])->assertForbidden();
        $this->assertSame('unresolved', $issue->fresh()->status);
    }

    public function test_update_validation_errors(): void
    {
        Sanctum::actingAs($this->user, ['write']);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);
        $outsider = User::factory()->create();

        $this->patchJson($this->base()."/issues/{$issue->id}", ['status' => 'nope'])->assertJsonValidationErrors('status');
        $this->patchJson($this->base()."/issues/{$issue->id}", ['assigned_to_user_id' => $outsider->id])->assertJsonValidationErrors('assigned_to_user_id');
    }

    public function test_cross_organization_access_is_denied(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);
        $otherOrg = Organization::factory()->create();
        $otherProject = FaultProject::factory()->create(['organization_id' => $otherOrg->id]);
        $otherIssue = FaultIssue::factory()->create(['fault_project_id' => $otherProject->id]);
        $event = FaultEvent::factory()->create(['fault_project_id' => $otherProject->id, 'fault_issue_id' => $otherIssue->id]);

        $this->getJson($this->base($otherProject, $otherOrg).'/issues')->assertForbidden();
        $this->patchJson($this->base($otherProject, $otherOrg)."/issues/{$otherIssue->id}", ['status' => 'resolved'])->assertForbidden();
        $this->getJson($this->base($otherProject, $otherOrg)."/issues/{$otherIssue->id}/events")->assertForbidden();
        $this->getJson($this->base($otherProject, $otherOrg).'/releases')->assertForbidden();
        $this->postJson($this->base($otherProject, $otherOrg).'/releases', ['version' => '1'])->assertForbidden();

        $this->patchJson($this->base()."/issues/{$otherIssue->id}", ['status' => 'resolved'])->assertNotFound();
        $this->getJson($this->base()."/issues/{$otherIssue->id}/events/{$event->id}")->assertNotFound();
        $this->getJson($this->base($otherProject)."/issues/{$otherIssue->id}")->assertNotFound();
    }

    public function test_events_can_be_listed_and_shown(): void
    {
        Sanctum::actingAs($this->user, ['read']);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);
        $event = FaultEvent::factory()->create(['fault_project_id' => $this->project->id, 'fault_issue_id' => $issue->id, 'message' => 'kaboom', 'environment' => 'production']);
        $sibling = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);
        $siblingEvent = FaultEvent::factory()->create(['fault_project_id' => $this->project->id, 'fault_issue_id' => $sibling->id]);

        $this->getJson($this->base()."/issues/{$issue->id}/events")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_id', $event->event_id)
            ->assertJsonPath('data.0.environment', 'production');

        $this->getJson($this->base()."/issues/{$issue->id}/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.message', 'kaboom')
            ->assertJsonStructure(['data' => ['id', 'event_id', 'payload']]);

        $this->getJson($this->base()."/issues/{$issue->id}/events/{$siblingEvent->id}")->assertNotFound();
    }
}
