<?php

namespace Tests\Feature\Fault;

use App\Ai\Agents\IssueAnalystAgent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueAiAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyzing_an_issue_stores_the_ai_response(): void
    {
        IssueAnalystAgent::fake(['## Likely cause

The amount was negative.

## Suggested fix

Validate input before charging.

## Confidence

High']);

        $organization = Organization::factory()->create([
            'ai_provider' => 'anthropic',
            'ai_api_key' => 'sk-test',
        ]);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $response = $this->actingAs($owner)->post(
            route('organizations.issues.analyze', [$organization, $project, $issue])
        );

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors('ai');

        $issue->refresh();
        $this->assertNotNull($issue->ai_analysis);
        $this->assertStringContainsString('Validate input', $issue->ai_analysis);
        $this->assertNotNull($issue->ai_analyzed_at);

        IssueAnalystAgent::assertPromptedTimes(1);
    }

    public function test_analyzing_without_an_ai_provider_configured_shows_an_error(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $response = $this->actingAs($owner)->post(
            route('organizations.issues.analyze', [$organization, $project, $issue])
        );

        $response->assertSessionHasErrors('ai');
        $this->assertNull($issue->fresh()->ai_analysis);
    }
}
