<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Diagnostics\SuggestTransactionFixesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFixSuggestionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_suggests_transfer_review_for_card_withdrawal_candidate(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => 'カード引落が expense として記録されている候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', '現在種別', '提案操作', '提案種別', '提案元口座', '提案相手口座', '診断理由', '確認メモ'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    'expense',
                    'transfer へ見直し確認',
                    'transfer',
                    '住信SBIネット銀行',
                    '三井住友カード',
                    'カード引落が expense として記録されている候補',
                    'クレジットカード引落なら expense ではなく bank -> credit_card transfer が自然です。',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_suggests_transfer_review_for_code_payment_candidate(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
            'import_aliases' => ['d払い請求'],
        ]);

        $transaction = Transaction::factory()->forAccount($cardAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'd払い請求',
            'amount' => '3000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => 'コード決済請求付替が expense として記録されている候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', '現在種別', '提案操作', '提案種別', '提案元口座', '提案相手口座', '診断理由', '確認メモ'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    'expense',
                    'transfer へ見直し確認',
                    'transfer',
                    'dカード',
                    'd払い',
                    'コード決済請求付替が expense として記録されている候補',
                    '請求付替なら expense ではなく transfer が自然です。実際に消費した取引が別にあるか確認してください。',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_does_not_suggest_transfer_review_for_code_payment_purchase_like_expense(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->forAccount($cardAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'd払いタッチ/飲料自販機/iD',
            'description' => 'dカード / 食費 / 食料品',
            'amount' => '90',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => 'コード決済請求付替が expense として記録されている候補',
        ])
            ->expectsOutput('補正提案は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_command_suggests_transfer_review_for_cash_withdrawal_or_charge_candidate(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => 'りそな銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '現金',
            'type' => 'cash',
            'currency' => 'JPY',
            'import_aliases' => ['現金引き出し'],
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'ATM引出',
            'description' => '現金引き出し',
            'amount' => '10000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', '現在種別', '提案操作', '提案種別', '提案元口座', '提案相手口座', '診断理由', '確認メモ'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    'expense',
                    'transfer へ見直し確認',
                    'transfer',
                    'りそな銀行',
                    '現金',
                    '現金引き出し/チャージが expense として記録されている候補',
                    '現金引き出しやウォレットへのチャージなら expense ではなく transfer が自然です。',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_keeps_wallet_as_charge_source_when_same_day_claim_transfer_exists(): void
    {
        $user = User::factory()->create();
        $creditCardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $dpayAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'モバイルSuica',
            'type' => 'e_money',
            'currency' => 'JPY',
            'import_aliases' => ['モバイルSuicaチャージ'],
        ]);

        Transaction::factory()->transfer($creditCardAccount, $dpayAccount)->create([
            'user_id' => $user->id,
            'merchant_name' => 'd払いB/モバイルSuicaチャージ',
            'amount' => '10000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);
        $chargeExpense = Transaction::factory()->forAccount($dpayAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'モバイルSuicaチャージ',
            'description' => 'd払い / 現金・カード / 電子マネー',
            'amount' => '10000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', '現在種別', '提案操作', '提案種別', '提案元口座', '提案相手口座', '診断理由', '確認メモ'],
                [[
                    (string) $chargeExpense->id,
                    (string) $user->id,
                    'expense',
                    'transfer へ見直し確認',
                    'transfer',
                    'd払い',
                    'モバイルSuica',
                    '現金引き出し/チャージが expense として記録されている候補',
                    '現金引き出しやウォレットへのチャージなら expense ではなく transfer が自然です。',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_ignores_unrelated_same_day_same_amount_transfer_for_cash_charge_candidate(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => 'りそな銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $otherBankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $cashAccount = Account::factory()->for($user)->create([
            'name' => '現金',
            'type' => 'cash',
            'currency' => 'JPY',
            'import_aliases' => ['現金引き出し'],
        ]);
        $emoneyAccount = Account::factory()->for($user)->create([
            'name' => 'PayPay',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->transfer($otherBankAccount, $emoneyAccount)->create([
            'user_id' => $user->id,
            'amount' => '10000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);
        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'ATM引出',
            'description' => '現金引き出し',
            'amount' => '10000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', '現在種別', '提案操作', '提案種別', '提案元口座', '提案相手口座', '診断理由', '確認メモ'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    'expense',
                    'transfer へ見直し確認',
                    'transfer',
                    'りそな銀行',
                    '現金',
                    '現金引き出し/チャージが expense として記録されている候補',
                    '現金引き出しやウォレットへのチャージなら expense ではなく transfer が自然です。',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_outputs_tsv_format(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => 'カード引落が expense として記録されている候補',
            '--format' => 'tsv',
        ])
            ->expectsOutputToContain("transaction_id\tuser_id\t現在種別\t提案操作\t提案種別\t提案元口座\t提案相手口座\t診断理由\t確認メモ")
            ->expectsOutputToContain($transaction->id."\t".$user->id."\texpense\ttransfer へ見直し確認\ttransfer\t住信SBIネット銀行\t三井住友カード\tカード引落が expense として記録されている候補")
            ->doesntExpectOutputToContain('補正提案件数')
            ->assertSuccessful();
    }

    public function test_command_outputs_details_columns_for_manual_review(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => '未分類',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->for($user)->forCategory($category)->create([
            'name' => '未分類',
        ]);

        $transaction = Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'マルエツ',
            'description' => '食料品',
            'memo' => '手動確認用',
            'amount' => '1200.5',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $suggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, '未分類カテゴリ実体の確認候補', 10)
            ->first();

        $this->assertSame($transaction->id, $suggestion['transaction_id']);
        $this->assertSame('2026-04-19', $suggestion['transaction_date']);
        $this->assertSame('1200.50', $suggestion['amount']);
        $this->assertSame('JPY', $suggestion['currency']);
        $this->assertSame('マルエツ', $suggestion['merchant_name']);
        $this->assertSame('食料品', $suggestion['description']);
        $this->assertSame('手動確認用', $suggestion['memo']);
        $this->assertSame('未分類', $suggestion['current_category']);
        $this->assertSame('未分類', $suggestion['current_subcategory']);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => '未分類カテゴリ実体の確認候補',
            '--details' => true,
            '--format' => 'tsv',
        ])
            ->expectsOutputToContain("transaction_id\tuser_id\t現在種別\t提案操作\t提案種別\t提案元口座\t提案相手口座\t診断理由\t確認メモ\t日付\t金額\t通貨\t摘要\t説明\tメモ\t現在カテゴリ\t現在サブカテゴリ")
            ->expectsOutputToContain($transaction->id."\t".$user->id."\texpense\tカテゴリ未設定へ見直し確認\texpense\td払い\t-\t未分類カテゴリ実体の確認候補")
            ->assertSuccessful();
    }

    public function test_command_formats_details_columns_from_suggestion(): void
    {
        $user = User::factory()->create();

        $this->mock(SuggestTransactionFixesService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('handle')
                ->once()
                ->with($user->id, '未分類カテゴリ実体の確認候補', 1, null)
                ->andReturn(collect([[
                    'transaction_id' => 123,
                    'user_id' => $user->id,
                    'current_type' => 'expense',
                    'suggested_action' => 'カテゴリ未設定へ見直し確認',
                    'suggested_type' => 'expense',
                    'suggested_source_account' => 'd払い',
                    'suggested_transfer_account' => null,
                    'reason' => '未分類カテゴリ実体の確認候補',
                    'note' => 'Money Forward の未分類はカテゴリ実体ではなく category_id=null / subcategory_id=null 扱いが自然です。',
                    'transaction_date' => '2026-04-19',
                    'amount' => '1200.50',
                    'currency' => 'JPY',
                    'merchant_name' => 'マルエツ',
                    'description' => '食料品',
                    'memo' => '手動確認用',
                    'current_category' => '未分類',
                    'current_subcategory' => '未分類',
                ]]));
        });

        $this->artisan('transactions:suggest-fixes', [
            '--user' => $user->id,
            '--reason' => '未分類カテゴリ実体の確認候補',
            '--limit' => 1,
            '--details' => true,
            '--format' => 'tsv',
        ])
            ->expectsOutputToContain("123\t".$user->id."\texpense\tカテゴリ未設定へ見直し確認\texpense\td払い\t-\t未分類カテゴリ実体の確認候補\tMoney Forward の未分類はカテゴリ実体ではなく category_id=null / subcategory_id=null 扱いが自然です。\t2026-04-19\t1200.50\tJPY\tマルエツ\t食料品\t手動確認用\t未分類\t未分類")
            ->assertSuccessful();
    }

    public function test_command_outputs_markdown_format(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBI|ネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => 'カード引落が expense として記録されている候補',
            '--format' => 'markdown',
        ])
            ->expectsOutput('| transaction_id | user_id | 現在種別 | 提案操作 | 提案種別 | 提案元口座 | 提案相手口座 | 診断理由 | 確認メモ |')
            ->expectsOutput('| --- | --- | --- | --- | --- | --- | --- | --- | --- |')
            ->expectsOutputToContain('| '.$transaction->id.' | '.$user->id.' | expense | transfer へ見直し確認 | transfer | 住信SBI\|ネット銀行 | 三井住友カード | カード引落が expense として記録されている候補 |')
            ->doesntExpectOutputToContain('補正提案件数')
            ->assertSuccessful();
    }

    public function test_command_rejects_invalid_format(): void
    {
        $this->artisan('transactions:suggest-fixes', [
            '--format' => 'json',
        ])
            ->expectsOutput('--format は table, tsv, markdown のいずれかを指定してください。')
            ->assertExitCode(2);
    }

    public function test_explain_fix_command_outputs_detail_for_candidate_transaction(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'description' => 'カード請求',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $transaction->id,
        ])
            ->expectsOutput('取引情報')
            ->expectsOutputToContain('三井住友カード引落')
            ->expectsOutput('補正提案')
            ->expectsOutputToContain('カード引落が expense として記録されている候補')
            ->expectsOutputToContain('transfer へ見直し確認')
            ->expectsOutputToContain('住信SBIネット銀行')
            ->expectsOutputToContain('三井住友カード')
            ->expectsOutput('手動修正時の確認')
            ->expectsOutputToContain('同じカード利用本体の expense が別に存在するか確認する')
            ->assertSuccessful();
    }

    public function test_explain_fix_command_detects_duplicated_investment_flow_for_target_transaction(): void
    {
        $user = User::factory()->create();
        $dpayAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $dcardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $securitiesAccount = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->transfer($dpayAccount, $securitiesAccount)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-10',
            'amount' => '30000',
            'currency' => 'JPY',
        ]);
        $target = Transaction::factory()->transfer($dcardAccount, $securitiesAccount)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-10',
            'amount' => '30000',
            'currency' => 'JPY',
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $target->id,
        ])
            ->expectsOutput('補正提案')
            ->expectsOutputToContain('積立フローの二重表現候補')
            ->expectsOutputToContain('二重表現確認')
            ->expectsOutputToContain('同日同額の積立 transfer が同じ実態を重複表現していないか確認する')
            ->assertSuccessful();
    }

    public function test_explain_fix_command_outputs_related_transactions_for_shopping_like_transfer(): void
    {
        $user = User::factory()->create();
        $cashAccount = Account::factory()->for($user)->create([
            'name' => '現金',
            'type' => 'cash',
            'currency' => 'JPY',
        ]);
        $emoneyAccount = Account::factory()->for($user)->create([
            'name' => '電子マネー',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);

        $target = Transaction::factory()->transfer($cashAccount, $emoneyAccount)->create([
            'user_id' => $user->id,
            'merchant_name' => 'スーパー支払い',
            'description' => 'shopping transfer',
            'amount' => '1200',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
            'is_calculation_target' => false,
        ]);
        $relatedExpense = Transaction::factory()->forAccount($emoneyAccount)->forCategory($category)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'スーパー利用',
            'description' => 'same date same amount',
            'memo' => 'レシート確認済み',
            'amount' => '1200',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
            'is_calculation_target' => true,
        ]);
        Transaction::factory()->forAccount($emoneyAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '別日の取引',
            'amount' => '1200',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-20',
        ]);
        Transaction::factory()->forAccount($emoneyAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '別額の取引',
            'amount' => '1300',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'name' => '別ユーザー口座',
            'type' => 'cash',
            'currency' => 'JPY',
        ]);
        Transaction::factory()->forAccount($otherAccount)->create([
            'user_id' => $otherUser->id,
            'type' => 'expense',
            'merchant_name' => '別ユーザー取引',
            'amount' => '1200',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $target->id,
        ])
            ->expectsOutputToContain('shopping 系 transfer の確認候補')
            ->expectsOutputToContain('二重表現確認')
            ->expectsOutputToContain('同日同額の expense があれば、二重計上を避けるため transfer と expense のどちらを残すべきか確認する')
            ->expectsOutput('同日同額の関連候補')
            ->expectsOutput('関連候補 1 件 / expense 1 件')
            ->expectsOutput("- #{$relatedExpense->id} expense / 電子マネー -> - / スーパー利用 / same date same amount / レシート確認済み / 食費 / 集計対象:true")
            ->doesntExpectOutputToContain('別日の取引')
            ->doesntExpectOutputToContain('別額の取引')
            ->doesntExpectOutputToContain('別ユーザー取引')
            ->assertSuccessful();
    }

    public function test_explain_fix_command_reports_no_related_transactions_for_shopping_like_transfer(): void
    {
        $user = User::factory()->create();
        $cashAccount = Account::factory()->for($user)->create([
            'name' => '現金',
            'type' => 'cash',
            'currency' => 'JPY',
        ]);
        $emoneyAccount = Account::factory()->for($user)->create([
            'name' => '電子マネー',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $target = Transaction::factory()->transfer($cashAccount, $emoneyAccount)->create([
            'user_id' => $user->id,
            'merchant_name' => 'スーパー支払い',
            'amount' => '1200',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
            'is_calculation_target' => false,
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $target->id,
        ])
            ->expectsOutputToContain('expense 化確認')
            ->expectsOutputToContain('同日同額の expense がなければ、この transfer を type=expense に直すべき可能性がある')
            ->expectsOutput('同日同額の関連候補')
            ->expectsOutput('同日同額の関連候補は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_explain_fix_command_reports_no_suggestion_for_normal_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => '現金',
            'type' => 'cash',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '昼食',
            'amount' => '1000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $transaction->id,
        ])
            ->expectsOutput('取引情報')
            ->expectsOutput('この transaction に対する補正提案は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_explain_fix_command_fails_for_missing_transaction(): void
    {
        $this->artisan('transactions:explain-fix', [
            'transaction' => 999999,
        ])
            ->expectsOutput('指定された transaction は見つかりませんでした。')
            ->assertExitCode(1);
    }

    public function test_explain_fix_command_rejects_non_integer_transaction_id(): void
    {
        $this->artisan('transactions:explain-fix', [
            'transaction' => '1.5',
        ])
            ->expectsOutput('transaction は正の整数で指定してください。')
            ->assertExitCode(2);

        $this->artisan('transactions:explain-fix', [
            'transaction' => '1e3',
        ])
            ->expectsOutput('transaction は正の整数で指定してください。')
            ->assertExitCode(2);
    }

    public function test_explain_fix_command_is_read_only(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'description' => 'before',
            'memo' => 'before',
        ]);

        $originalUpdatedAt = $transaction->updated_at;

        $this->artisan('transactions:explain-fix', [
            'transaction' => $transaction->id,
        ])->assertSuccessful();

        $transaction->refresh();

        $this->assertSame('before', $transaction->description);
        $this->assertSame('before', $transaction->memo);
        $this->assertTrue($transaction->updated_at?->equalTo($originalUpdatedAt));
    }

    public function test_command_suggests_review_for_duplicated_investment_flow_candidate(): void
    {
        $user = User::factory()->create();
        $dpayAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $dcardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $securitiesAccount = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->transfer($dpayAccount, $securitiesAccount)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-10',
            'amount' => '30000',
            'currency' => 'JPY',
        ]);
        $second = Transaction::factory()->transfer($dcardAccount, $securitiesAccount)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-10',
            'amount' => '30000',
            'currency' => 'JPY',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => '積立フローの二重表現候補',
            '--limit' => 1,
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', '現在種別', '提案操作', '提案種別', '提案元口座', '提案相手口座', '診断理由', '確認メモ'],
                [[
                    (string) $second->id,
                    (string) $user->id,
                    'transfer',
                    '二重表現確認',
                    'transfer',
                    'dカード',
                    'THEO',
                    '積立フローの二重表現候補',
                    '同日同額で複数の積立 transfer があり、同じ積立を重複表現している可能性があります。d払い経由 / dcard 直 / 銀行直 のどれが実態か確認してください。',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_suggests_category_removal_review_for_categorized_transfer(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'type' => 'expense',
        ]);
        $source = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $destination = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->transfer($source, $destination)->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);

        $suggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, 'カテゴリ付き transfer の確認候補', 10)
            ->first();

        $this->assertSame($transaction->id, $suggestion['transaction_id']);
        $this->assertSame('カテゴリ削除確認', $suggestion['suggested_action']);
        $this->assertSame('dカード', $suggestion['suggested_source_account']);
        $this->assertSame('d払い', $suggestion['suggested_transfer_account']);
        $this->assertSame('カテゴリ付き transfer の確認候補', $suggestion['reason']);
        $this->assertSame(
            'このアプリでは transfer はカテゴリを持たない前提です。Money Forward 由来の補助カテゴリなら削除候補です。',
            $suggestion['note'],
        );
    }

    public function test_command_suggests_expense_review_for_shopping_like_transfer_without_related_expense(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'name' => '銀行口座',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $destination = Account::factory()->for($user)->create([
            'name' => '電子マネー',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->transfer($source, $destination)->create([
            'user_id' => $user->id,
            'merchant_name' => 'スーパー支払い',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $suggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, 'shopping 系 transfer の確認候補', 10)
            ->first();

        $this->assertSame($transaction->id, $suggestion['transaction_id']);
        $this->assertSame('expense 化確認', $suggestion['suggested_action']);
        $this->assertSame('expense', $suggestion['suggested_type']);
        $this->assertSame('銀行口座', $suggestion['suggested_source_account']);
        $this->assertSame('電子マネー', $suggestion['suggested_transfer_account']);
        $this->assertSame('shopping 系 transfer の確認候補', $suggestion['reason']);
        $this->assertSame(
            'shopping 系キーワードを含む transfer です。同日同額の expense はありません。実支出なら expense 化候補です。口座間移動として残す場合は category/subcategory を空にしてください。',
            $suggestion['note'],
        );
    }

    public function test_command_excludes_normal_shopping_transfer_and_expense_pair(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $destination = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);

        $transaction = Transaction::factory()->transfer($source, $destination)->create([
            'user_id' => $user->id,
            'merchant_name' => 'マルエツ',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
            'category_id' => null,
            'subcategory_id' => null,
            'is_calculation_target' => false,
        ]);
        Transaction::factory()->forAccount($destination)->forCategory($category)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'マルエツ',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);

        $suggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, 'shopping 系 transfer の確認候補', 10)
            ->first();

        $this->assertNull($suggestion);
    }

    public function test_command_ignores_unrelated_expenses_for_shopping_like_transfer_related_check(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $destination = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->transfer($source, $destination)->create([
            'user_id' => $user->id,
            'merchant_name' => 'やよい軒',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);
        $otherDateExpense = Transaction::factory()->forAccount($destination)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '別日の取引',
            'transaction_date' => '2026-04-20',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);
        $otherAmountExpense = Transaction::factory()->forAccount($destination)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '別額の取引',
            'transaction_date' => '2026-04-19',
            'amount' => '1300',
            'currency' => 'JPY',
        ]);
        $otherCurrencyExpense = Transaction::factory()->forAccount($destination)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '別通貨の取引',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'USD',
        ]);
        $otherUser = User::factory()->create();
        $otherUserAccount = Account::factory()->for($otherUser)->create([
            'name' => '別ユーザー口座',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $otherUserExpense = Transaction::factory()->forAccount($otherUserAccount)->create([
            'user_id' => $otherUser->id,
            'type' => 'expense',
            'merchant_name' => '別ユーザー取引',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);

        $suggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, 'shopping 系 transfer の確認候補', 10)
            ->first();

        $this->assertSame($transaction->id, $suggestion['transaction_id']);
        $this->assertSame('expense 化確認', $suggestion['suggested_action']);
        $this->assertSame('expense', $suggestion['suggested_type']);
        $this->assertStringNotContainsString((string) $otherDateExpense->id, $suggestion['note']);
        $this->assertStringNotContainsString((string) $otherAmountExpense->id, $suggestion['note']);
        $this->assertStringNotContainsString((string) $otherCurrencyExpense->id, $suggestion['note']);
        $this->assertStringNotContainsString((string) $otherUserExpense->id, $suggestion['note']);
    }

    public function test_command_suggests_null_category_review_for_uncategorized_category_entity(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => '未分類',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->for($user)->forCategory($category)->create([
            'name' => '未分類',
        ]);

        $transaction = Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '店舗',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);

        $suggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, '未分類カテゴリ実体の確認候補', 10)
            ->first();

        $this->assertSame($transaction->id, $suggestion['transaction_id']);
        $this->assertSame('カテゴリ未設定へ見直し確認', $suggestion['suggested_action']);
        $this->assertSame('expense', $suggestion['suggested_type']);
        $this->assertSame('d払い', $suggestion['suggested_source_account']);
        $this->assertNull($suggestion['suggested_transfer_account']);
        $this->assertSame('未分類カテゴリ実体の確認候補', $suggestion['reason']);
        $this->assertSame(
            'Money Forward の未分類はカテゴリ実体ではなく category_id=null / subcategory_id=null 扱いが自然です。実カテゴリへ分類すべきかも確認してください。',
            $suggestion['note'],
        );
    }

    public function test_command_suggests_null_subcategory_review_when_only_subcategory_is_uncategorized(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->for($user)->forCategory($category)->create([
            'name' => '未分類',
        ]);

        $transaction = Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '店舗',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);

        $suggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, '未分類カテゴリ実体の確認候補', 10)
            ->first();

        $this->assertSame($transaction->id, $suggestion['transaction_id']);
        $this->assertSame('サブカテゴリ未設定へ見直し確認', $suggestion['suggested_action']);
        $this->assertSame('expense', $suggestion['suggested_type']);
        $this->assertSame('未分類カテゴリ実体の確認候補', $suggestion['reason']);
        $this->assertSame(
            'Money Forward の未分類サブカテゴリは実体ではなく subcategory_id=null 扱いが自然です。category は維持し、必要なら実サブカテゴリへ分類してください。',
            $suggestion['note'],
        );
    }

    public function test_command_adds_point_usage_context_for_uncategorized_category_entity(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => '未分類',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->for($user)->forCategory($category)->create([
            'name' => '未分類',
        ]);

        $expense = Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'サントリービバレッジソリューション',
            'transaction_date' => '2026-03-31',
            'amount' => '150',
            'currency' => 'JPY',
        ]);
        $pointIncome = Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'income',
            'merchant_name' => 'サントリービバレッジソリューション(ポイント利用分)',
            'transaction_date' => '2026-03-31',
            'amount' => '150',
            'currency' => 'JPY',
        ]);

        $suggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, '未分類カテゴリ実体の確認候補', 10)
            ->first();

        $this->assertSame($pointIncome->id, $suggestion['transaction_id']);
        $this->assertSame('カテゴリ未設定へ見直し確認', $suggestion['suggested_action']);
        $this->assertStringContainsString(
            '摘要にポイント利用分が含まれます。ポイント相殺の income/expense ペアなら、両方とも未分類カテゴリ実体を null にするか確認してください。',
            $suggestion['note'],
        );
        $this->assertStringContainsString(
            '同日同額の関連 transaction が 1 件あります（代表ID: '.$expense->id,
            $suggestion['note'],
        );
        $this->assertStringContainsString(
            '#'.$expense->id.' / d払い / サントリービバレッジソリューション / 未分類 > 未分類',
            $suggestion['note'],
        );
    }

    public function test_command_filters_by_user_reason_and_limit(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $userBank = Account::factory()->for($user)->create([
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $otherBank = Account::factory()->for($otherUser)->create([
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        $first = Transaction::factory()->forAccount($userBank)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'カード引落A',
            'transaction_date' => '2026-04-11',
        ]);
        $second = Transaction::factory()->forAccount($userBank)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'カード引落B',
            'transaction_date' => '2026-04-10',
        ]);
        Transaction::factory()->forAccount($otherBank)->create([
            'user_id' => $otherUser->id,
            'type' => 'expense',
            'merchant_name' => 'カード引落C',
            'transaction_date' => '2026-04-12',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--user' => $user->id,
            '--reason' => 'カード引落が expense として記録されている候補',
            '--limit' => 1,
        ])
            ->expectsOutputToContain((string) $first->id)
            ->doesntExpectOutputToContain((string) $second->id)
            ->assertSuccessful();
    }

    public function test_command_filters_by_suggested_action(): void
    {
        $user = User::factory()->create();
        $dpayAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $dcardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $securitiesAccount = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->transfer($dpayAccount, $securitiesAccount)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-19',
            'amount' => '30000',
            'currency' => 'JPY',
        ]);
        Transaction::factory()->transfer($dcardAccount, $securitiesAccount)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-19',
            'amount' => '30000',
            'currency' => 'JPY',
        ]);

        $expectedSuggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, '積立フローの二重表現候補', 10)
            ->first();

        $this->assertSame('二重表現確認', $expectedSuggestion['suggested_action']);

        $this->artisan('transactions:suggest-fixes', [
            '--user' => $user->id,
            '--reason' => '積立フローの二重表現候補',
            '--action' => '二重表現確認',
            '--limit' => 1,
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', '現在種別', '提案操作', '提案種別', '提案元口座', '提案相手口座', '診断理由', '確認メモ'],
                [[
                    (string) $expectedSuggestion['transaction_id'],
                    (string) $user->id,
                    'transfer',
                    '二重表現確認',
                    'transfer',
                    (string) $expectedSuggestion['suggested_source_account'],
                    'THEO',
                    '積立フローの二重表現候補',
                    '同日同額で複数の積立 transfer があり、同じ積立を重複表現している可能性があります。d払い経由 / dcard 直 / 銀行直 のどれが実態か確認してください。',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_service_filters_by_action_before_deduplicating_same_transaction_suggestions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);
        $source = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $destination = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->transfer($source, $destination)->create([
            'user_id' => $user->id,
            'merchant_name' => 'スーパー支払い',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
            'category_id' => $category->id,
            'subcategory_id' => null,
        ]);

        $defaultSuggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, null, 10)
            ->first();

        $this->assertSame($transaction->id, $defaultSuggestion['transaction_id']);
        $this->assertSame('カテゴリ削除確認', $defaultSuggestion['suggested_action']);

        $filteredSuggestion = app(SuggestTransactionFixesService::class)
            ->handle($user->id, null, 10, 'expense 化確認')
            ->first();

        $this->assertSame($transaction->id, $filteredSuggestion['transaction_id']);
        $this->assertSame('expense 化確認', $filteredSuggestion['suggested_action']);
    }

    public function test_command_passes_suggested_action_filter_to_service(): void
    {
        $user = User::factory()->create();

        $this->mock(SuggestTransactionFixesService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('handle')
                ->once()
                ->with($user->id, null, 1, 'expense 化確認')
                ->andReturn(collect([[
                    'transaction_id' => 123,
                    'user_id' => $user->id,
                    'current_type' => 'transfer',
                    'suggested_action' => 'expense 化確認',
                    'suggested_type' => 'expense',
                    'suggested_source_account' => 'dカード',
                    'suggested_transfer_account' => 'd払い',
                    'reason' => 'shopping 系 transfer の確認候補',
                    'note' => 'shopping 系キーワードを含む transfer です。',
                ]]));
        });

        $this->artisan('transactions:suggest-fixes', [
            '--user' => $user->id,
            '--action' => 'expense 化確認',
            '--limit' => 1,
            '--format' => 'tsv',
        ])
            ->expectsOutputToContain("123\t".$user->id."\ttransfer\texpense 化確認\texpense\tdカード\td払い\tshopping 系 transfer の確認候補")
            ->assertSuccessful();
    }

    public function test_command_rejects_empty_action(): void
    {
        $this->artisan('transactions:suggest-fixes', [
            '--action' => ' ',
        ])
            ->expectsOutput('--action は空でない文字列で指定してください。')
            ->assertExitCode(2);
    }

    public function test_command_does_not_suggest_cross_currency_counterparty(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Master Card',
            'type' => 'credit_card',
            'currency' => 'USD',
            'import_aliases' => ['MasterCard'],
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'MasterCard引落',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:suggest-fixes', [
            '--reason' => 'カード引落が expense として記録されている候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', '現在種別', '提案操作', '提案種別', '提案元口座', '提案相手口座', '診断理由', '確認メモ'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    'expense',
                    'transfer へ見直し確認',
                    'transfer',
                    '住信SBIネット銀行',
                    '-',
                    'カード引落が expense として記録されている候補',
                    'クレジットカード引落なら expense ではなく bank -> credit_card transfer が自然です。相手カード口座の確認が必要です。',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_service_returns_only_one_suggestion_per_transaction(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => 'りそな銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'PayPayカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
            'import_aliases' => ['PayPayカード'],
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'PayPayカード引落',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $suggestions = app(SuggestTransactionFixesService::class)->handle($user->id, null, 10);

        $this->assertCount(1, $suggestions);
        $this->assertSame($transaction->id, $suggestions->first()['transaction_id']);
    }

    public function test_explain_fix_command_outputs_all_matching_suggestions_for_transaction(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => 'りそな銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
            'import_aliases' => ['dカード'],
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'dカード引落 d払い請求',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $transaction->id,
        ])
            ->expectsOutput('補正提案 1')
            ->expectsOutputToContain('カード引落が expense として記録されている候補')
            ->expectsOutput('補正提案 2')
            ->expectsOutputToContain('コード決済請求付替が expense として記録されている候補')
            ->expectsOutputToContain('同日同額・同一摘要の重複取込ではないか確認する')
            ->assertSuccessful();
    }

    public function test_explain_fix_command_includes_alias_check_when_counterparty_is_missing(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => 'りそな銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'd払い請求',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $transaction->id,
        ])
            ->expectsOutputToContain('コード決済請求付替が expense として記録されている候補')
            ->expectsOutputToContain('提案相手口座が空の場合は、口座名と accounts.import_aliases の不足を確認する')
            ->assertSuccessful();
    }

    public function test_explain_fix_command_outputs_uncategorized_category_entity_checks(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => '未分類',
            'type' => 'expense',
        ]);

        $transaction = Transaction::factory()->forAccount($account)->forCategory($category)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '店舗',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $transaction->id,
        ])
            ->expectsOutput('補正提案')
            ->expectsOutputToContain('未分類カテゴリ実体の確認候補')
            ->expectsOutputToContain('カテゴリ未設定へ見直し確認')
            ->expectsOutputToContain('実カテゴリへ分類すべき取引ではないか確認する')
            ->expectsOutputToContain('本当に未分類なら category/subcategory を外して category_id=null / subcategory_id=null にする')
            ->doesntExpectOutputToContain('提案相手口座が空の場合は、口座名と accounts.import_aliases の不足を確認する')
            ->assertSuccessful();
    }

    public function test_explain_fix_command_keeps_category_when_only_subcategory_is_uncategorized(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->for($user)->forCategory($category)->create([
            'name' => '未分類',
        ]);

        $transaction = Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '店舗',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);

        $this->artisan('transactions:explain-fix', [
            'transaction' => $transaction->id,
        ])
            ->expectsOutputToContain('サブカテゴリ未設定へ見直し確認')
            ->expectsOutputToContain('実サブカテゴリへ分類すべき取引ではないか確認する')
            ->expectsOutputToContain('本当に未分類サブカテゴリなら category は残し、subcategory だけ外して subcategory_id=null にする')
            ->doesntExpectOutputToContain('本当に未分類なら category/subcategory を外して category_id=null / subcategory_id=null にする')
            ->assertSuccessful();
    }

    public function test_service_prioritizes_high_priority_reasons_before_low_priority_reason_limit(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $creditCardAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $emoneyAccount = Account::factory()->for($user)->create([
            'name' => 'PayPay',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $highPriorityTransaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-01',
        ]);

        Transaction::factory()->transfer($bankAccount, $emoneyAccount)->create([
            'user_id' => $user->id,
            'merchant_name' => 'スーパー支払い',
            'amount' => '1000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);
        Transaction::factory()->transfer($creditCardAccount, $emoneyAccount)->create([
            'user_id' => $user->id,
            'merchant_name' => 'コンビニ支払い',
            'amount' => '1500',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-18',
        ]);

        $suggestions = app(SuggestTransactionFixesService::class)->handle($user->id, null, 1);

        $this->assertCount(1, $suggestions);
        $this->assertSame($highPriorityTransaction->id, $suggestions->first()['transaction_id']);
        $this->assertSame('カード引落が expense として記録されている候補', $suggestions->first()['reason']);
    }

    public function test_command_is_read_only(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'description' => 'before',
            'memo' => 'before',
        ]);

        $originalUpdatedAt = $transaction->updated_at;

        $this->artisan('transactions:suggest-fixes')->assertSuccessful();

        $transaction->refresh();

        $this->assertSame('before', $transaction->description);
        $this->assertSame('before', $transaction->memo);
        $this->assertTrue($transaction->updated_at?->equalTo($originalUpdatedAt));
    }
}
