<?php

namespace Tests\Feature\Fault;

use App\Enums\FaultPlatform;
use App\Livewire\ProjectCreationWizard;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCreationWizardLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_advances_to_the_setup_tutorial_step(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(ProjectCreationWizard::class, ['organization' => $organization])
            ->set('name', 'API')
            ->set('platform', 'laravel')
            ->call('createProject')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('composer require sentry/sentry-laravel');

        $this->assertDatabaseHas('fault_projects', ['name' => 'API', 'platform' => 'laravel']);
    }

    public function test_creating_a_project_without_touching_the_platform_field_uses_the_default(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(ProjectCreationWizard::class, ['organization' => $organization])
            ->set('name', 'API')
            ->call('createProject')
            ->assertHasNoErrors()
            ->assertSet('step', 2);

        $this->assertDatabaseHas('fault_projects', ['name' => 'API', 'platform' => FaultPlatform::cases()[0]->value]);
    }

    public function test_name_is_required_to_advance(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(ProjectCreationWizard::class, ['organization' => $organization])
            ->call('createProject')
            ->assertHasErrors(['name'])
            ->assertSet('step', 1);
    }

    public function test_platform_defaults_to_the_first_option_shown_in_the_select(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(ProjectCreationWizard::class, ['organization' => $organization])
            ->assertSet('platform', FaultPlatform::cases()[0]->value);
    }

    public function test_finishing_the_wizard_redirects_to_the_new_project(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(ProjectCreationWizard::class, ['organization' => $organization])
            ->set('name', 'API')
            ->set('platform', 'laravel')
            ->call('createProject')
            ->call('finish')
            ->assertRedirect(route('organizations.projects.show', [$organization, FaultProject::where('name', 'API')->sole()]));
    }
}
