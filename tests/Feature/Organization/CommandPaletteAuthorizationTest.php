<?php

namespace Tests\Feature\Organization;

use App\Livewire\CommandPalette;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandPaletteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_members_cannot_render_the_palette_for_an_organization(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(CommandPalette::class, ['organization' => $organization])
            ->assertForbidden();
    }

    public function test_members_can_render_the_palette(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'viewer']);

        Livewire::actingAs($user)
            ->test(CommandPalette::class, ['organization' => $organization])
            ->assertOk();
    }
}
