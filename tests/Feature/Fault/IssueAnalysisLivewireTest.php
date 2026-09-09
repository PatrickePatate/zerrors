<?php

namespace Tests\Feature\Fault;

use App\Ai\Agents\IssueAnalystAgent;
use App\Livewire\IssueAnalysis;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IssueAnalysisLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_analyzes_an_issue_and_stores_the_result(): void
    {
        IssueAnalystAgent::fake(['## Likely cause

The amount was negative.']);

        $organization = Organization::factory()->create([
            'ai_provider' => 'anthropic',
            'ai_api_key' => 'sk-test',
        ]);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        Livewire::actingAs($owner)
            ->test(IssueAnalysis::class, ['issue' => $issue])
            ->call('analyze')
            ->assertSet('error', null);

        $this->assertNotNull($issue->fresh()->ai_analysis);
    }

    public function test_it_surfaces_an_error_without_an_ai_provider_configured(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        Livewire::actingAs($owner)
            ->test(IssueAnalysis::class, ['issue' => $issue])
            ->call('analyze')
            ->assertSet('error', 'No AI provider is configured for this organization.');

        $this->assertNull($issue->fresh()->ai_analysis);
    }

    public function test_the_component_renders_on_the_issue_page(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $this->actingAs($owner)
            ->get(route('organizations.issues.show', [$organization, $project, $issue]))
            ->assertOk()
            ->assertSeeText('AI analysis');
    }
}
