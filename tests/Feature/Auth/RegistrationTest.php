<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_creates_a_personal_organization_as_owner(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertCount(1, $user->organizations);
        $this->assertSame('owner', $user->organizations->first()->pivot->role);
    }

    public function test_login_requires_valid_credentials(): void
    {
        User::factory()->create(['email' => 'bob@example.com', 'password' => bcrypt('correct-password')]);

        $response = $this->post('/login', ['email' => 'bob@example.com', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
