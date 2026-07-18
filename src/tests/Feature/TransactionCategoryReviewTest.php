<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionCategoryReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_review_high_confidence_category_suggestions_for_their_uncategorized_transactions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($otherUser)->create();
        $category = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->forCategory($category)->create([
            'name' => '外食',
        ]);
        Category::factory()->for($user)->create([
            'name' => '未分類',
            'type' => 'expense',
        ]);
        Subcategory::factory()->forCategory($category)->create([
            'name' => '未分類',
        ]);
        $highConfidenceTarget = Transaction::factory()->forAccount($account)->create([
            'merchant_name' => '同じ店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        Transaction::factory()->forAccount($account)->create([
            'merchant_name' => '提案なしの店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'merchant_name' => '同じ店',
        ]);
        Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'merchant_name' => '同じ店',
        ]);
        Transaction::factory()->forAccount($otherAccount)->create([
            'merchant_name' => '他人の取引',
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.category-review.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/CategoryReview')
                ->where('filters.status', 'high')
                ->where('filters.type', 'all')
                ->where('review.summary.total', 2)
                ->where('review.summary.high_confidence', 1)
                ->where('review.summary.manual_review', 1)
                ->has('review.items', 1)
                ->where('review.items.0.transaction_id', $highConfidenceTarget->id)
                ->where('review.items.0.suggested_category_id', $category->id)
                ->where('review.items.0.suggested_subcategory_id', $subcategory->id)
                ->where('review.items.0.confidence', 90)
                ->has('categoryOptions', 1)
                ->where('categoryOptions.0.id', $category->id)
                ->has('subcategoryOptions', 1)
                ->where('subcategoryOptions.0.id', $subcategory->id));
    }

    public function test_user_can_filter_category_review_to_manual_items(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $target = Transaction::factory()->forAccount($account)->create([
            'merchant_name' => '提案なしの店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.category-review.index', [
                'status' => 'manual',
                'type' => 'expense',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'manual')
                ->where('filters.type', 'expense')
                ->has('review.items', 1)
                ->where('review.items.0.transaction_id', $target->id)
                ->where('review.items.0.confidence', 0));
    }

    public function test_user_can_assign_category_without_changing_other_transaction_fields(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create();
        $transaction = Transaction::factory()->forAccount($account)->create([
            'category_id' => null,
            'subcategory_id' => null,
            'is_confirmed' => false,
            'amount' => '1234.56',
        ]);

        $response = $this->actingAs($user)->patch(
            route('transactions.category-review.update', $transaction),
            [
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', 'カテゴリを設定しました。');
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'is_confirmed' => false,
            'amount' => '1234.56',
        ]);
    }

    public function test_user_can_assign_category_and_create_an_exact_match_rule_for_future_imports(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->forCategory($category)->create([
            'name' => '外食',
        ]);
        $transaction = Transaction::factory()->forAccount($account)->create([
            'merchant_name' => '丸亀製麺 ThinkPark',
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $response = $this->actingAs($user)->patch(
            route('transactions.category-review.update', $transaction),
            [
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
                'create_rule' => true,
                'rule_match_field' => 'merchant_name',
                'rule_match_operator' => 'equals',
                'rule_match_value' => '丸亀製麺 ThinkPark',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas(
            'success',
            'カテゴリを設定し、今後のインポートに使う分類ルールを作成しました。',
        );
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
        ]);
        $this->assertDatabaseHas('classification_rules', [
            'user_id' => $user->id,
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'equals',
            'match_value' => '丸亀製麺 ThinkPark',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'is_calculation_target' => null,
            'priority' => 0,
            'is_active' => true,
        ]);
    }

    public function test_category_and_rule_are_not_saved_when_the_rule_condition_is_invalid_or_duplicated(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $transaction = Transaction::factory()->forAccount($account)->create([
            'merchant_name' => '同じ店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('transactions.category-review.update', $transaction), [
                'category_id' => $category->id,
                'subcategory_id' => null,
                'create_rule' => true,
                'rule_match_field' => 'merchant_name',
                'rule_match_operator' => 'equals',
                'rule_match_value' => '別の店',
            ])
            ->assertSessionHasErrors('rule_match_value');

        $this->assertNull($transaction->refresh()->category_id);

        ClassificationRule::create([
            'user_id' => $user->id,
            'name' => '既存ルール',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'equals',
            'match_value' => '  同じ店  ',
            'category_id' => $category->id,
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('transactions.category-review.update', $transaction), [
                'category_id' => $category->id,
                'subcategory_id' => null,
                'create_rule' => true,
                'rule_match_field' => 'merchant_name',
                'rule_match_operator' => 'equals',
                'rule_match_value' => '同じ店',
            ])
            ->assertSessionHasErrors('create_rule');

        $this->assertNull($transaction->refresh()->category_id);
        $this->assertDatabaseCount('classification_rules', 1);
    }

    public function test_user_can_add_a_prefilled_category_and_return_to_category_review(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('categories.create', [
                'type' => 'income',
                'return_to' => 'category-review',
                'review_status' => 'manual',
                'review_type' => 'income',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Categories/Create')
                ->where('initialType', 'income')
                ->where('returnContext.return_to', 'category-review')
                ->where('returnContext.review_status', 'manual')
                ->where('returnContext.review_type', 'income'));

        $response = $this->actingAs($user)->post(route('categories.store'), [
            'name' => 'ポイント利用',
            'type' => 'income',
            'color' => '',
            'icon' => '',
            'display_order' => 0,
            'is_active' => true,
            'return_to' => 'category-review',
            'review_status' => 'manual',
            'review_type' => 'income',
        ]);

        $response->assertRedirect(route('transactions.category-review.index', [
            'status' => 'manual',
            'type' => 'income',
        ]));
        $response->assertSessionHas(
            'success',
            'カテゴリ「ポイント利用」を追加しました。対象の取引で選択してください。',
        );
        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'ポイント利用',
            'type' => 'income',
        ]);
    }

    public function test_category_review_rejects_invalid_or_stale_category_assignments(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $otherCategory = Category::factory()->for($otherUser)->create(['type' => 'expense']);
        $uncategorizedCategory = Category::factory()->for($user)->create([
            'name' => '未分類',
            'type' => 'expense',
        ]);
        $transaction = Transaction::factory()->forAccount($account)->create([
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('transactions.category-review.update', $transaction), [
                'category_id' => $otherCategory->id,
                'subcategory_id' => null,
            ])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)
            ->patch(route('transactions.category-review.update', $transaction), [
                'category_id' => $uncategorizedCategory->id,
                'subcategory_id' => null,
            ])
            ->assertSessionHasErrors('category_id');

        $transaction->update(['category_id' => $category->id]);

        $this->actingAs($user)
            ->patch(route('transactions.category-review.update', $transaction), [
                'category_id' => $category->id,
                'subcategory_id' => null,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_user_cannot_assign_category_to_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $transaction = Transaction::factory()->forAccount($otherAccount)->create([
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('transactions.category-review.update', $transaction), [
                'category_id' => $category->id,
                'subcategory_id' => null,
            ])
            ->assertForbidden();
    }
}
