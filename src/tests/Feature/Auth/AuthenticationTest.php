<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_development_login_is_shown_and_authenticates_the_only_local_user(): void
    {
        config()->set('auth.development_login.enabled', true);

        $user = User::factory()->create();

        $this->get('/login')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('canUseDevelopmentLogin', true));

        $response = $this->post(route('development-login'));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_development_login_uses_the_configured_user_when_multiple_users_exist(): void
    {
        config()->set('auth.development_login.enabled', true);

        User::factory()->create();
        $developmentUser = User::factory()->create([
            'email' => 'developer@example.test',
        ]);
        config()->set('auth.development_login.user_email', $developmentUser->email);

        $this->post(route('development-login'));

        $this->assertAuthenticatedAs($developmentUser);
    }

    public function test_development_login_is_not_available_when_disabled(): void
    {
        config()->set('auth.development_login.enabled', false);

        User::factory()->create();

        $this->get('/login')
            ->assertInertia(fn (Assert $page) => $page
                ->where('canUseDevelopmentLogin', false));

        $this->post(route('development-login'))->assertNotFound();
        $this->assertGuest();
    }

    public function test_development_login_is_not_available_outside_local_or_testing(): void
    {
        config()->set('auth.development_login.enabled', true);
        $this->app->instance('env', 'production');

        User::factory()->create();

        $this->post(route('development-login'))->assertNotFound();
        $this->assertGuest();
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
