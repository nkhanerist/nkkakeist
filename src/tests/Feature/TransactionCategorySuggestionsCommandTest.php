<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Diagnostics\SuggestTransactionCategoriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionCategorySuggestionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_suggests_category_from_classification_rule(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'dカード']);
        $category = Category::factory()->for($user)->create(['name' => '通信費', 'type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create(['name' => 'サブスク']);
        $transaction = Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-19',
            'amount' => '3639',
            'merchant_name' => 'OPENAI *CHATGPT SUBSCR',
            'description' => 'dカード / 未分類 / 未分類',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        $rule = ClassificationRule::create([
            'user_id' => $user->id,
            'name' => 'ChatGPT',
            'transaction_type' => 'expense',
            'match_field' => 'merchant_name',
            'match_operator' => 'contains',
            'match_value' => 'CHATGPT',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $suggestion = app(SuggestTransactionCategoriesService::class)
            ->handle($user->id, 2026, 'expense', 70, 10)
            ->firstWhere('transaction_id', $transaction->id);

        $this->assertSame('通信費', $suggestion['suggested_category']);
        $this->assertSame('サブスク', $suggestion['suggested_subcategory']);
        $this->assertSame($category->id, $suggestion['suggested_category_id']);
        $this->assertSame($subcategory->id, $suggestion['suggested_subcategory_id']);

        $this->artisan('transactions:suggest-categories', [
            '--year' => '2026',
            '--format' => 'tsv',
        ])
            ->expectsOutputToContain($transaction->id."\t".$user->id."\texpense\t2026-04-19\t3639.00\tJPY\tdカード\tOPENAI *CHATGPT SUBSCR")
            ->assertSuccessful();

        $transaction->refresh();
        $this->assertNull($transaction->category_id);
        $this->assertNull($transaction->subcategory_id);
    }

    public function test_service_suggests_category_from_same_merchant_history(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'd払い']);
        $category = Category::factory()->for($user)->create(['name' => '食費', 'type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create(['name' => '外食']);
        $target = Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-20',
            'merchant_name' => 'やよい軒川口店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        $reference = Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-01',
            'merchant_name' => 'やよい軒川口店',
        ]);
        Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-03-01',
            'merchant_name' => 'やよい軒川口店',
        ]);

        $suggestions = app(SuggestTransactionCategoriesService::class)->handle($user->id, 2026, 'expense', 70, 10);

        $suggestion = $suggestions->firstWhere('transaction_id', $target->id);
        $this->assertNotNull($suggestion);
        $this->assertSame('食費', $suggestion['suggested_category']);
        $this->assertSame('外食', $suggestion['suggested_subcategory']);
        $this->assertSame(90, $suggestion['confidence']);
        $this->assertSame(2, $suggestion['reference_count']);
        $this->assertSame($reference->id, $suggestion['reference_transaction_id']);
    }

    public function test_service_loads_transaction_history_in_bulk(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        foreach (range(1, 10) as $index) {
            Transaction::factory()->forAccount($account)->create([
                'merchant_name' => "未分類の店{$index}",
                'category_id' => null,
                'subcategory_id' => null,
            ]);
            Transaction::factory()->forAccount($account)->forCategory($category)->create([
                'merchant_name' => "未分類の店{$index}",
            ]);
        }

        DB::enableQueryLog();

        app(SuggestTransactionCategoriesService::class)
            ->handle($user->id, null, 'expense', 0, 50, 'all');

        $transactionQueryCount = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from "transactions"')
                || str_contains($query['query'], 'from `transactions`'))
            ->count();

        $this->assertSame(2, $transactionQueryCount);
    }

    public function test_service_does_not_use_other_users_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'd払い']);
        $otherAccount = Account::factory()->for($otherUser)->create(['name' => 'd払い']);
        $otherCategory = Category::factory()->for($otherUser)->create(['name' => '他人カテゴリ', 'type' => 'expense']);

        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-20',
            'merchant_name' => '同じ店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        Transaction::factory()->forAccount($otherAccount)->forCategory($otherCategory)->create([
            'user_id' => $otherUser->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-01',
            'merchant_name' => '同じ店',
        ]);

        $suggestions = app(SuggestTransactionCategoriesService::class)->handle($user->id, 2026, 'expense', 70, 10);

        $this->assertTrue($suggestions->isEmpty());
    }

    public function test_service_does_not_target_transfer_transactions(): void
    {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->for($user)->create(['name' => 'dカード']);
        $toAccount = Account::factory()->for($user)->create(['name' => 'd払い']);
        $category = Category::factory()->for($user)->create(['name' => '食費', 'type' => 'expense']);

        Transaction::factory()->transfer($fromAccount, $toAccount)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-20',
            'merchant_name' => 'やよい軒川口店',
        ]);
        Transaction::factory()->forAccount($toAccount)->forCategory($category)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-01',
            'merchant_name' => 'やよい軒川口店',
        ]);

        $suggestions = app(SuggestTransactionCategoriesService::class)->handle($user->id, 2026, 'all', 70, 10);

        $this->assertTrue($suggestions->isEmpty());
    }

    public function test_service_does_not_suggest_uncategorized_category_entities(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $uncategorized = Category::factory()->for($user)->create([
            'name' => '未分類',
            'type' => 'expense',
        ]);
        $target = Transaction::factory()->forAccount($account)->create([
            'merchant_name' => '同じ店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        Transaction::factory()->forAccount($account)->forCategory($uncategorized)->create([
            'merchant_name' => '同じ店',
        ]);
        Transaction::factory()->forAccount($account)->forCategory($uncategorized)->create([
            'merchant_name' => '同じ店',
        ]);

        $suggestion = app(SuggestTransactionCategoriesService::class)
            ->handle($user->id, null, 'expense', 0, 10, 'all')
            ->firstWhere('transaction_id', $target->id);

        $this->assertNotNull($suggestion);
        $this->assertNull($suggestion['suggested_category_id']);
        $this->assertSame(0, $suggestion['confidence']);
    }

    public function test_command_respects_min_confidence(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'd払い']);
        $category = Category::factory()->for($user)->create(['name' => '食費', 'type' => 'expense']);
        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-20',
            'merchant_name' => '単発店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        Transaction::factory()->forAccount($account)->forCategory($category)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-01',
            'merchant_name' => '単発店',
        ]);

        $this->artisan('transactions:suggest-categories', [
            '--user' => (string) $user->id,
            '--year' => '2026',
            '--min-confidence' => '90',
        ])
            ->expectsOutput('カテゴリ提案は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_command_outputs_missing_category_suggestion_targets(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'd払い']);
        $category = Category::factory()->for($user)->create(['name' => '食費', 'type' => 'expense']);
        $suggestedTarget = Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-20',
            'merchant_name' => 'やよい軒川口店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        $missingTarget = Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-21',
            'merchant_name' => '未分類の店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        Transaction::factory()->forAccount($account)->forCategory($category)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-01',
            'merchant_name' => 'やよい軒川口店',
        ]);

        $missingSuggestion = app(SuggestTransactionCategoriesService::class)
            ->handle($user->id, 2026, 'expense', 70, 10, 'missing')
            ->firstWhere('transaction_id', $missingTarget->id);

        $this->assertNotNull($missingSuggestion);
        $this->assertSame(
            __('transactions.category_review.reasons.none'),
            $missingSuggestion['reason'],
        );

        $this->artisan('transactions:suggest-categories', [
            '--user' => (string) $user->id,
            '--year' => '2026',
            '--mode' => 'missing',
            '--format' => 'tsv',
        ])
            ->expectsOutputToContain($missingTarget->id."\t".$user->id."\texpense\t2026-04-21")
            ->doesntExpectOutputToContain($suggestedTarget->id."\t".$user->id."\texpense\t2026-04-20")
            ->assertSuccessful();
    }

    public function test_service_falls_back_to_same_merchant_history_when_same_account_suggestion_is_below_min_confidence(): void
    {
        $user = User::factory()->create();
        $targetAccount = Account::factory()->for($user)->create(['name' => 'd払い']);
        $otherAccount = Account::factory()->for($user)->create(['name' => 'dカード']);
        $category = Category::factory()->for($user)->create(['name' => '食費', 'type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create(['name' => '外食']);
        $target = Transaction::factory()->forAccount($targetAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-20',
            'merchant_name' => '同じ店',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
        Transaction::factory()->forAccount($targetAccount)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-01',
            'merchant_name' => '同じ店',
        ]);
        $reference = Transaction::factory()->forAccount($otherAccount)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-10',
            'merchant_name' => '同じ店',
        ]);
        Transaction::factory()->forAccount($otherAccount)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'transaction_date' => '2026-04-09',
            'merchant_name' => '同じ店',
        ]);

        $suggestions = app(SuggestTransactionCategoriesService::class)->handle($user->id, 2026, 'expense', 85, 10);

        $suggestion = $suggestions->firstWhere('transaction_id', $target->id);
        $this->assertNotNull($suggestion);
        $this->assertSame('食費', $suggestion['suggested_category']);
        $this->assertSame('外食', $suggestion['suggested_subcategory']);
        $this->assertSame(85, $suggestion['confidence']);
        $this->assertSame(
            __('transactions.category_review.reasons.same_merchant'),
            $suggestion['reason'],
        );
        $this->assertSame(3, $suggestion['reference_count']);
        $this->assertSame($reference->id, $suggestion['reference_transaction_id']);
    }

    public function test_command_rejects_non_numeric_min_confidence(): void
    {
        $this->artisan('transactions:suggest-categories', [
            '--min-confidence' => 'foo',
        ])
            ->expectsOutput('--min-confidence は 0 から 100 の整数で指定してください。')
            ->assertExitCode(2);
    }

    public function test_command_rejects_invalid_mode(): void
    {
        $this->artisan('transactions:suggest-categories', [
            '--mode' => 'unknown',
        ])
            ->expectsOutput('--mode は suggested, missing, all のいずれかを指定してください。')
            ->assertExitCode(2);
    }
}
