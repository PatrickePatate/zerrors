<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_set_up_and_confirm_two_factor_authentication(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('two-factor.setup'))->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        $validCode = (new Google2FA)->getCurrentOtp($user->two_factor_secret);

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => $validCode])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_login_challenges_for_a_code_when_two_factor_is_enabled(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post(route('two-factor.challenge.store'), ['code' => $google2fa->getCurrentOtp($secret)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_recovery_code_can_be_used_once(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode(['recovery-code-1']),
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->post(route('two-factor.challenge.store'), ['code' => 'recovery-code-1'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->post(route('two-factor.challenge.store'), ['code' => 'recovery-code-1'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_user_can_disable_two_factor_authentication(): void
    {
        $google2fa = new Google2FA;
        $user = User::factory()->create([
            'two_factor_secret' => $google2fa->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('two-factor.disable'), ['password' => 'password'])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
