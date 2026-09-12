<?php

namespace App\Livewire;

use App\Enums\FaultPlatform;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationRuleTrigger;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Organization\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProjectCreationWizard extends Component
{
    public Organization $organization;

    public bool $showModal = false;

    public int $step = 1;

    public string $name = '';

    public string $platform = '';

    public ?FaultProject $project = null;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->showModal = request()->boolean('new');
        // The select shows its first option as chosen before the user touches it,
        // but that's purely a client-side default that never reaches this property
        // unless it matches here too — otherwise submitting without opening the
        // dropdown fails "platform is required" with no visible reason.
        $this->platform = FaultPlatform::cases()[0]->value;
    }

    public function createProject(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(FaultPlatform::class)],
        ]);

        $this->project = $this->organization->projects()->create($data);

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'project.created', $this->project->name, [
            'platform' => $this->project->platform->value,
        ]);

        $channel = $this->project->notificationChannels()->create([
            'type' => NotificationChannelType::Email,
            'name' => 'Email',
            'config' => ['email' => auth()->user()->email],
            'enabled' => true,
        ]);

        $channel->rules()->create([
            'trigger' => NotificationRuleTrigger::OccurrenceThreshold,
            'thresholds' => [10, 100, 1000],
        ]);

        $this->step = 2;
    }

    public function finish()
    {
        $project = $this->project;

        $this->reset(['step', 'name', 'platform', 'project']);
        $this->showModal = false;

        return redirect()->route('organizations.projects.show', [$this->organization, $project]);
    }

    /**
     * @return array<int, array{description: string, code: ?string}>
     */
    public function tutorialSteps(): array
    {
        $dsn = $this->project?->dsn();

        return match ($this->project?->platform) {
            FaultPlatform::Laravel => [
                ['description' => 'Install the Sentry SDK for Laravel.', 'code' => 'composer require sentry/sentry-laravel'],
                ['description' => 'Publish the Sentry config file.', 'code' => 'php artisan sentry:publish-config'],
                ['description' => 'Add the DSN to your .env file.', 'code' => "SENTRY_LARAVEL_DSN={$dsn}"],
            ],
            FaultPlatform::Symfony => [
                ['description' => 'Install the Sentry SDK for Symfony.', 'code' => 'composer require sentry/sentry-symfony'],
                ['description' => 'Add the DSN to your .env file.', 'code' => "SENTRY_DSN={$dsn}"],
            ],
            FaultPlatform::Php => [
                ['description' => 'Install the Sentry PHP SDK.', 'code' => 'composer require sentry/sentry'],
                ['description' => 'Initialize it as early as possible in your app.', 'code' => "\\Sentry\\init(['dsn' => '{$dsn}']);"],
            ],
            FaultPlatform::WordPress => [
                ['description' => 'Install the Sentry plugin for WordPress from the plugin directory, or via Composer.', 'code' => 'composer require sentry/sentry'],
                ['description' => 'Enter this DSN in the plugin settings.', 'code' => $dsn],
            ],
            FaultPlatform::NodeJs => [
                ['description' => 'Install the Sentry SDK for Node.js.', 'code' => 'npm install --save @sentry/node'],
                ['description' => 'Initialize it at the top of your entry file.', 'code' => "Sentry.init({ dsn: '{$dsn}' });"],
            ],
            default => [
                ['description' => 'Use this DSN to configure any Sentry-compatible SDK for your stack.', 'code' => $dsn],
            ],
        };
    }

    public function render()
    {
        return view('livewire.project-creation-wizard');
    }
}
