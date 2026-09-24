<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezonePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_set_a_preferred_timezone(): void
    {
        $user = User::factory()->create(['timezone' => null]);

        $this->actingAs($user)
            ->post(route('security.timezone.update'), ['timezone' => 'America/New_York'])
            ->assertRedirect();

        $this->assertSame('America/New_York', $user->fresh()->timezone);
    }

    public function test_a_user_can_reset_to_automatic_browser_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'America/New_York']);

        $this->actingAs($user)
            ->post(route('security.timezone.update'), ['timezone' => ''])
            ->assertRedirect();

        $this->assertNull($user->fresh()->timezone);
    }

    public function test_an_invalid_timezone_is_rejected(): void
    {
        $user = User::factory()->create(['timezone' => null]);

        $this->actingAs($user)
            ->post(route('security.timezone.update'), ['timezone' => 'Not/A_Timezone'])
            ->assertSessionHasErrors('timezone');

        $this->assertNull($user->fresh()->timezone);
    }
}
