<?php

namespace Tests\Feature\Fault;

use App\Enums\TriageCategory;
use App\Enums\TriagePriority;
use App\Jobs\Fault\TriageFaultIssue;
use App\Livewire\IssueTriage;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use App\Support\Ai\IssueTriager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class IssueAiTriageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Issues are created quietly by default so the triage observer does not
     * run the job during setup; pass $quietly = false to exercise the observer.
     */
    protected function issueFor(array $organizationAttributes = [], bool $quietly = true): FaultIssue
    {
        $organization = Organization::factory()->create([
            'ai_triage_enabled' => true,
            'ai_triage_api_key' => 'sk-or-triage',
            ...$organizationAttributes,
        ]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $factory = FaultIssue::factory();
        $attributes = ['fault_project_id' => $project->id];

        return $quietly ? $factory->createQuietly($attributes) : $factory->create($attributes);
    }

    /**
     * @param  array<string, string>  $labels
     */
    protected function fakeLabels(array $labels = ['category' => 'database', 'priority' => 'high']): void
    {
        Classification::fake(fn (ClassificationPrompt $prompt) => [
            'category' => new ChoiceAnswer($labels['category'], [$labels['category'] => 0.9], 0.9),
            'priority' => new ChoiceAnswer($labels['priority'], [$labels['priority'] => 0.8], 0.8),
        ])->preventStrayClassifications();
    }

    public function test_labels_are_stored_using_the_default_provider_and_model(): void
    {
        $this->fakeLabels();
        $issue = $this->issueFor();

        (new TriageFaultIssue($issue->id))->handle(app(IssueTriager::class));

        $issue->refresh();
        $this->assertSame('database', $issue->triage_category);
        $this->assertSame('high', $issue->triage_priority);
        $this->assertSame('jev-latest', $issue->triage_model);
        $this->assertNotNull($issue->triaged_at);
        $this->assertNull($issue->triage_error);
        Classification::assertClassified(fn (ClassificationPrompt $prompt) => $prompt->provider->name() === 'typesafe'
            && $prompt->model === 'jev-latest'
            && array_keys($prompt->questions) === ['category', 'priority']
            && array_keys($prompt->questions['category']->options) === array_column(TriageCategory::cases(), 'value')
            && array_keys($prompt->questions['priority']->options) === array_column(TriagePriority::cases(), 'value'));
    }

    public function test_unusable_label_records_an_error_and_does_not_fall_back(): void
    {
        $this->fakeLabels(['category' => 'something_else', 'priority' => 'low']);
        $issue = $this->issueFor();

        try {
            (new TriageFaultIssue($issue->id))->handle(app(IssueTriager::class));
            $this->fail('Expected the failure to be rethrown.');
        } catch (RuntimeException) {
        }

        $issue->refresh();
        $this->assertNull($issue->triage_category);
        $this->assertNull($issue->triaged_at);
        $this->assertNotNull($issue->triage_error);
    }

    public function test_provider_error_is_recorded_without_falling_back_to_another_model(): void
    {
        $requested = [];
        Classification::fake(function (ClassificationPrompt $prompt) use (&$requested) {
            $requested[] = [$prompt->provider->name(), $prompt->model];

            throw new RuntimeException('Unknown model');
        });
        $issue = $this->issueFor(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-general', 'ai_model' => 'claude-general']);

        try {
            (new TriageFaultIssue($issue->id))->handle(app(IssueTriager::class));
            $this->fail('Expected the failure to be rethrown for the queue to retry.');
        } catch (RuntimeException) {
        }

        $issue->refresh();
        $this->assertSame('Unknown model', $issue->triage_error);
        $this->assertNull($issue->triaged_at);
        $this->assertSame([['typesafe', 'jev-latest']], $requested);
    }

    public function test_organization_provider_and_model_override_are_sent_as_is(): void
    {
        $this->fakeLabels(['category' => 'noise', 'priority' => 'low']);
        $issue = $this->issueFor(['ai_triage_provider' => 'openrouter', 'ai_triage_model' => '~acme/custom']);

        (new TriageFaultIssue($issue->id))->handle(app(IssueTriager::class));

        Classification::assertClassified(fn (ClassificationPrompt $prompt) => $prompt->provider->name() === 'openrouter' && $prompt->model === '~acme/custom');
        $this->assertSame('~acme/custom', $issue->fresh()->triage_model);
    }

    public function test_openrouter_uses_the_jev_default_model(): void
    {
        $this->fakeLabels();
        $issue = $this->issueFor(['ai_triage_provider' => 'openrouter']);

        (new TriageFaultIssue($issue->id))->handle(app(IssueTriager::class));

        Classification::assertClassified(fn (ClassificationPrompt $prompt) => $prompt->provider->name() === 'openrouter' && $prompt->model === '~typesafe/jev-latest');
    }

    public function test_key_selection(): void
    {
        $onOpenRouter = Organization::factory()->make([
            'ai_provider' => 'openrouter', 'ai_api_key' => 'sk-or-general', 'ai_triage_api_key' => 'sk-or-triage', 'ai_triage_enabled' => true,
        ]);
        $other = Organization::factory()->make([
            'ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant', 'ai_triage_api_key' => 'sk-or-triage', 'ai_triage_enabled' => true,
        ]);
        $none = Organization::factory()->make(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant', 'ai_triage_enabled' => true]);

        $onOpenRouter->ai_triage_provider = 'openrouter';
        $this->assertSame('sk-or-general', $onOpenRouter->aiTriageKey());
        $this->assertSame('sk-or-triage', $other->aiTriageKey());
        $this->assertSame('sk-or-triage', $onOpenRouter->replicate()->fill(['ai_triage_provider' => 'typesafe'])->aiTriageKey());
        $this->assertFalse($none->canTriageIssues());
    }

    public function test_job_is_idempotent_and_rate_limited(): void
    {
        $this->fakeLabels();
        $triaged = $this->issueFor();
        $triaged->forceFill(['triaged_at' => now()])->saveQuietly();

        (new TriageFaultIssue($triaged->id))->handle(app(IssueTriager::class));
        Classification::assertNothingClassified();

        $issue = $this->issueFor();
        config(['fault.ai_triage_hourly_limit' => 1]);
        RateLimiter::hit(TriageFaultIssue::rateLimitKey($issue->project->organization_id), 3600);

        (new TriageFaultIssue($issue->id))->handle(app(IssueTriager::class));

        $this->assertNull($issue->fresh()->triaged_at);
        Classification::assertNothingClassified();
    }

    public function test_observer_dispatches_only_when_triage_is_available(): void
    {
        Queue::fake();

        $this->issueFor(['ai_triage_enabled' => false], quietly: false);
        Queue::assertNothingPushed();

        $issue = $this->issueFor(quietly: false);
        Queue::assertPushed(TriageFaultIssue::class, fn (TriageFaultIssue $job) => $job->faultIssueId === $issue->id);
    }

    public function test_backfill_command_queues_untriaged_unresolved_issues_of_enabled_organizations(): void
    {
        Queue::fake();
        $pending = $this->issueFor();
        $done = FaultIssue::factory()->createQuietly(['fault_project_id' => $pending->fault_project_id, 'triaged_at' => now()]);
        $resolved = FaultIssue::factory()->createQuietly(['fault_project_id' => $pending->fault_project_id, 'status' => 'resolved']);
        $disabled = $this->issueFor(['ai_triage_enabled' => false]);

        $this->artisan('zerrors:triage-issues', ['--project' => $pending->fault_project_id])->assertSuccessful();

        Queue::assertPushed(TriageFaultIssue::class, 1);
        Queue::assertPushed(TriageFaultIssue::class, fn (TriageFaultIssue $job) => $job->faultIssueId === $pending->id);
    }

    public function test_possible_duplicates_are_listed(): void
    {
        $issue = $this->issueFor();
        $issue->forceFill(['type' => 'PDOException', 'culprit' => 'App\\Repo::find'])->saveQuietly();
        $match = FaultIssue::factory()->createQuietly(['fault_project_id' => $issue->fault_project_id, 'type' => 'PDOException', 'culprit' => 'App\\Repo::find', 'title' => 'Duplicate one']);
        FaultIssue::factory()->createQuietly(['fault_project_id' => $issue->fault_project_id, 'type' => 'PDOException', 'culprit' => 'Other', 'title' => 'Different culprit']);
        FaultIssue::factory()->createQuietly(['fault_project_id' => $issue->fault_project_id, 'type' => 'PDOException', 'culprit' => 'App\\Repo::find', 'status' => 'resolved', 'title' => 'Resolved one']);
        $owner = User::factory()->create();
        $issue->project->organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)->test(IssueTriage::class, ['issue' => $issue])
            ->assertSee('Possible duplicates')
            ->assertSee($match->title)
            ->assertDontSee('Different culprit')
            ->assertDontSee('Resolved one');
    }

    public function test_rerun_triage_component_updates_labels(): void
    {
        $this->fakeLabels(['category' => 'configuration', 'priority' => 'medium']);
        $issue = $this->issueFor();
        $owner = User::factory()->create();
        $issue->project->organization->users()->attach($owner->id, ['role' => 'member']);

        Livewire::actingAs($owner)->test(IssueTriage::class, ['issue' => $issue])
            ->call('rerun')
            ->assertSee('Configuration');

        $this->assertSame('medium', $issue->fresh()->triage_priority);
    }
}
