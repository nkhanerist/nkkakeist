<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_primary_application_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index'));

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Accounts/Index'));

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Categories/Index'));

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Transactions/Index'));

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Imports/Index'));

        $this->actingAs($user)
            ->get(route('classification-rules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ClassificationRules/Index'));
    }

    public function test_guest_user_is_redirected_to_login_from_primary_application_pages(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('accounts.index'))->assertRedirect(route('login'));
        $this->get(route('categories.index'))->assertRedirect(route('login'));
        $this->get(route('transactions.index'))->assertRedirect(route('login'));
        $this->get(route('imports.index'))->assertRedirect(route('login'));
        $this->get(route('classification-rules.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_email_verification_from_primary_application_pages(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('classification-rules.index'))
            ->assertRedirect(route('verification.notice'));
    }
}
