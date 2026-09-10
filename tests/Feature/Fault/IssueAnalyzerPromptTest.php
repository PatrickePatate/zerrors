<?php

namespace Tests\Feature\Fault;

use App\Ai\Agents\IssueAnalystAgent;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Ai\IssueAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueAnalyzerPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_prompt_containing_issue_and_event_details(): void
    {
        $capturedPrompt = null;

        IssueAnalystAgent::fake(function (string $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;

            return 'Fake analysis';
        });

        $organization = Organization::factory()->create([
            'ai_provider' => 'anthropic',
            'ai_api_key' => 'sk-test',
        ]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create([
            'fault_project_id' => $project->id,
            'type' => 'RuntimeException',
            'title' => 'Something broke',
            'culprit' => 'App\\Services\\Billing::charge()',
            'level' => 'error',
            'times_seen' => 3,
        ]);
        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'environment' => 'production',
            'release' => '1.4.0',
            'tags' => ['server' => 'web-1'],
            'occurred_at' => now(),
            'exception' => [
                'values' => [[
                    'type' => 'RuntimeException',
                    'value' => 'Something broke',
                    'stacktrace' => ['frames' => [
                        ['filename' => 'app/Services/Billing.php', 'lineno' => 42, 'function' => 'charge', 'in_app' => true, 'context_line' => '$amount->negate();'],
                        ['filename' => 'vendor/framework/Dispatcher.php', 'lineno' => 10, 'function' => 'dispatch', 'in_app' => false],
                    ]],
                ]],
            ],
        ]);

        (new IssueAnalyzer)->analyze($issue);

        $this->assertNotNull($capturedPrompt);
        $this->assertStringContainsString('Exception type: RuntimeException', $capturedPrompt);
        $this->assertStringContainsString('Message: Something broke', $capturedPrompt);
        $this->assertStringContainsString('Culprit: App\\Services\\Billing::charge()', $capturedPrompt);
        $this->assertStringContainsString('Level: error', $capturedPrompt);
        $this->assertStringContainsString('Times seen: 3', $capturedPrompt);
        $this->assertStringContainsString('Environment: production', $capturedPrompt);
        $this->assertStringContainsString('Release: 1.4.0', $capturedPrompt);
        $this->assertStringContainsString('Tags: server=web-1', $capturedPrompt);
        $this->assertStringContainsString('Stack trace (top of stack first):', $capturedPrompt);
        $this->assertStringContainsString('[in-app] app/Services/Billing.php:42 in charge()', $capturedPrompt);
        $this->assertStringContainsString('$amount->negate();', $capturedPrompt);
        $this->assertStringContainsString('[vendor] vendor/framework/Dispatcher.php:10 in dispatch()', $capturedPrompt);
    }

    public function test_it_falls_back_to_unknown_culprit_and_omits_missing_event_details(): void
    {
        $capturedPrompt = null;

        IssueAnalystAgent::fake(function (string $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;

            return 'Fake analysis';
        });

        $organization = Organization::factory()->create([
            'ai_provider' => 'anthropic',
            'ai_api_key' => 'sk-test',
        ]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create([
            'fault_project_id' => $project->id,
            'culprit' => null,
        ]);

        (new IssueAnalyzer)->analyze($issue);

        $this->assertNotNull($capturedPrompt);
        $this->assertStringContainsString('Culprit: unknown', $capturedPrompt);
        $this->assertStringNotContainsString('Environment:', $capturedPrompt);
        $this->assertStringNotContainsString('Release:', $capturedPrompt);
        $this->assertStringNotContainsString('Tags:', $capturedPrompt);
        $this->assertStringNotContainsString('Stack trace', $capturedPrompt);
    }
}
