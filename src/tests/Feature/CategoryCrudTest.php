<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_only_their_own_categories(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownCategory = Category::factory()->for($user)->create([
            'name' => 'Food',
            'display_order' => 1,
        ]);

        Subcategory::factory()->forCategory($ownCategory)->create([
            'name' => 'Groceries',
        ]);

        Category::factory()->for($otherUser)->create([
            'name' => 'Other Category',
            'display_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Categories/Index')
                ->has('categories', 1)
                ->where('categories.0.id', $ownCategory->id)
                ->where('categories.0.name', 'Food')
                ->has('categories.0.subcategories', 1)
                ->where('categories.0.subcategories.0.name', 'Groceries'));
    }

    public function test_authenticated_user_can_create_a_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('categories.store'), [
            'name' => 'Utilities',
            'type' => 'expense',
            'color' => '2563eb',
            'icon' => 'bolt',
            'display_order' => 2,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Utilities',
            'type' => 'expense',
            'color' => '#2563eb',
            'icon' => 'bolt',
            'display_order' => 2,
            'is_active' => 1,
        ]);
    }

    public function test_authenticated_user_can_edit_their_own_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $this->actingAs($user)
            ->get(route('categories.edit', $category))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Categories/Edit')
                ->where('category.id', $category->id)
                ->where('category.name', 'Food'));

        $response = $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => 'Food Updated',
            'type' => 'both',
            'color' => '#10b981',
            'icon' => 'fork',
            'display_order' => 5,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'user_id' => $user->id,
            'name' => 'Food Updated',
            'type' => 'both',
            'color' => '#10b981',
            'icon' => 'fork',
            'display_order' => 5,
            'is_active' => 0,
        ]);
    }

    public function test_authenticated_user_can_delete_their_own_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('categories.index'));

        $this->assertModelMissing($category);
    }

    public function test_user_cannot_access_or_modify_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($otherUser)->create();

        $this->actingAs($user)
            ->get(route('categories.edit', $category))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => 'Blocked',
                'type' => 'expense',
                'color' => null,
                'icon' => null,
                'display_order' => 0,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertForbidden();
    }

    public function test_user_can_add_subcategory_to_their_own_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('subcategories.store'), [
            'category_id' => $category->id,
            'name' => 'Groceries',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('categories.edit', $category));

        $this->assertDatabaseHas('subcategories', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Groceries',
            'display_order' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_add_subcategory_to_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($otherUser)->create();

        $this->actingAs($user)->post(route('subcategories.store'), [
            'category_id' => $category->id,
            'name' => 'Blocked',
            'display_order' => 1,
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('subcategories', [
            'category_id' => $category->id,
            'name' => 'Blocked',
        ]);
    }

    public function test_user_can_update_and_delete_their_own_subcategory(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $subcategory = Subcategory::factory()->forCategory($category)->create([
            'name' => 'Groceries',
        ]);

        $this->actingAs($user)
            ->put(route('subcategories.update', $subcategory), [
                'name' => 'Dining Out',
                'display_order' => 3,
                'is_active' => false,
            ])
            ->assertRedirect(route('categories.edit', $category));

        $this->assertDatabaseHas('subcategories', [
            'id' => $subcategory->id,
            'name' => 'Dining Out',
            'display_order' => 3,
            'is_active' => 0,
        ]);

        $this->actingAs($user)
            ->delete(route('subcategories.destroy', $subcategory))
            ->assertRedirect(route('categories.edit', $category));

        $this->assertModelMissing($subcategory);
    }

    public function test_user_cannot_modify_another_users_subcategory(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($otherUser)->create();
        $subcategory = Subcategory::factory()->forCategory($category)->create();

        $this->actingAs($user)
            ->put(route('subcategories.update', $subcategory), [
                'name' => 'Blocked',
                'display_order' => 0,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('subcategories.destroy', $subcategory))
            ->assertForbidden();
    }
}
