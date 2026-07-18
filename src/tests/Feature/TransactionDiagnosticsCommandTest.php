<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionDiagnosticsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_detects_card_withdrawal_recorded_as_expense(): void
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
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'カード引落が expense として記録されている候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', 'date', 'type', 'account', 'transfer_account', 'amount', 'currency', 'merchant_name', 'description', 'memo', '診断理由'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    '2026-04-19',
                    'expense',
                    '住信SBIネット銀行',
                    '-',
                    '5000.00',
                    'JPY',
                    '三井住友カード引落',
                    $transaction->description ?? '-',
                    $transaction->memo ?? '-',
                    'カード引落が expense として記録されている候補',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_detects_code_payment_transfer_recorded_as_expense(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($cardAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'd払い請求',
            'amount' => '3000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'コード決済請求付替が expense として記録されている候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', 'date', 'type', 'account', 'transfer_account', 'amount', 'currency', 'merchant_name', 'description', 'memo', '診断理由'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    '2026-04-19',
                    'expense',
                    'dカード',
                    '-',
                    '3000.00',
                    'JPY',
                    'd払い請求',
                    $transaction->description ?? '-',
                    $transaction->memo ?? '-',
                    'コード決済請求付替が expense として記録されている候補',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_detects_code_payment_transfer_with_billing_marker(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($cardAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'd払いB/兼由',
            'description' => 'dカード / 現金・カード / 電子マネー',
            'amount' => '1000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'コード決済請求付替が expense として記録されている候補',
        ])
            ->expectsOutputToContain((string) $transaction->id)
            ->assertSuccessful();
    }

    public function test_command_does_not_detect_code_payment_purchase_like_expense_as_transfer_candidate(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $kyashAccount = Account::factory()->for($user)->create([
            'name' => 'kyash',
            'type' => 'other',
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
        Transaction::factory()->forAccount($kyashAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '購入 DMM.com',
            'description' => 'kyash / 通信費 / 放送視聴料',
            'amount' => '550',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-18',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'コード決済請求付替が expense として記録されている候補',
        ])
            ->expectsOutput('診断候補は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_card_withdrawal_detection_ignores_generic_card_category_text(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => 'りそな銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'バンクPOS PAYPAY',
            'description' => '現金・カード / 電子マネー',
            'amount' => '1200',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'カード引落が expense として記録されている候補',
        ])
            ->expectsOutput('診断候補は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_command_detects_cash_withdrawal_recorded_as_expense(): void
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
            'merchant_name' => 'ATM引出',
            'description' => 'りそな銀行 / 現金引き出し',
            'amount' => '10000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
            'memo' => null,
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
            '--format' => 'tsv',
        ])
            ->expectsOutput("transaction_id\tuser_id\tdate\ttype\taccount\ttransfer_account\tamount\tcurrency\tmerchant_name\tdescription\tmemo\t診断理由")
            ->expectsOutput("{$transaction->id}\t{$user->id}\t2026-04-19\texpense\tりそな銀行\t-\t10000.00\tJPY\tATM引出\tりそな銀行 / 現金引き出し\t-\t現金引き出し/チャージが expense として記録されている候補")
            ->assertSuccessful();
    }

    public function test_command_detects_wallet_charge_recorded_as_expense(): void
    {
        $user = User::factory()->create();
        $emoneyAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $suicaCharge = Transaction::factory()->forAccount($emoneyAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'モバイルSuicaチャージ',
            'description' => 'd払い / 現金・カード / 電子マネー',
            'amount' => '10000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);
        $gameCharge = Transaction::factory()->forAccount($emoneyAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'YostarGamesチャージセ',
            'description' => 'd払い / 現金・カード / 電子マネー',
            'amount' => '9500',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-18',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
        ])
            ->expectsOutputToContain((string) $suicaCharge->id)
            ->doesntExpectOutputToContain('YostarGamesチャージセ')
            ->assertSuccessful();
    }

    public function test_command_detects_wallet_charge_from_description(): void
    {
        $user = User::factory()->create();
        $emoneyAccount = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($emoneyAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => null,
            'description' => 'モバイルSuicaチャージ',
            'memo' => null,
            'payment_method_label' => null,
            'amount' => '10000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
        ])
            ->expectsOutputToContain((string) $transaction->id)
            ->assertSuccessful();
    }

    public function test_command_detects_bank_pos_paypay_as_wallet_charge_recorded_as_expense(): void
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
            'merchant_name' => 'バンクPOS PAYPAY',
            'description' => 'りそな銀行 / 現金・カード / 電子マネー',
            'amount' => '1000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
        ])
            ->expectsOutputToContain((string) $transaction->id)
            ->assertSuccessful();
    }

    public function test_command_does_not_detect_code_payment_purchase_as_cash_charge(): void
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

        $this->artisan('transactions:diagnose', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
        ])
            ->expectsOutput('診断候補は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_command_does_not_detect_paypay_or_kyash_name_only_as_cash_charge(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);
        $kyashAccount = Account::factory()->for($user)->create([
            'name' => 'Kyash',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->forAccount($cardAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'PayPay請求',
            'description' => 'dカード / 現金・カード / 電子マネー',
            'amount' => '1200',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);
        Transaction::factory()->forAccount($kyashAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'Kyash / スーパー',
            'description' => 'Kyash / 食費 / 食料品',
            'amount' => '900',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-18',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => '現金引き出し/チャージが expense として記録されている候補',
        ])
            ->expectsOutput('診断候補は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_command_keeps_paypay_name_only_candidate_as_code_payment(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'currency' => 'JPY',
        ]);

        $transaction = Transaction::factory()->forAccount($cardAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'PayPay請求',
            'description' => 'dカード / 現金・カード / 電子マネー',
            'amount' => '1200',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'コード決済請求付替が expense として記録されている候補',
        ])
            ->expectsOutputToContain((string) $transaction->id)
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

        $transaction = Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'description' => "カード\n請求",
            'memo' => "確認\t必要",
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'カード引落が expense として記録されている候補',
            '--format' => 'tsv',
        ])
            ->expectsOutput("transaction_id\tuser_id\tdate\ttype\taccount\ttransfer_account\tamount\tcurrency\tmerchant_name\tdescription\tmemo\t診断理由")
            ->expectsOutput("{$transaction->id}\t{$user->id}\t2026-04-19\texpense\t住信SBIネット銀行\t-\t5000.00\tJPY\t三井住友カード引落\tカード 請求\t確認 必要\tカード引落が expense として記録されている候補")
            ->doesntExpectOutputToContain('診断候補件数')
            ->assertSuccessful();
    }

    public function test_command_outputs_markdown_format(): void
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
            'merchant_name' => '三井住友カード|引落',
            'description' => 'カード請求',
            'amount' => '5000',
            'currency' => 'JPY',
            'transaction_date' => '2026-04-19',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'カード引落が expense として記録されている候補',
            '--format' => 'markdown',
        ])
            ->expectsOutput('| transaction_id | user_id | date | type | account | transfer_account | amount | currency | merchant_name | description | memo | 診断理由 |')
            ->expectsOutput('| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |')
            ->expectsOutputToContain("| {$transaction->id} | {$user->id} | 2026-04-19 | expense | 住信SBIネット銀行 | - | 5000.00 | JPY | 三井住友カード\\|引落 | カード請求 |")
            ->doesntExpectOutputToContain('診断候補件数')
            ->assertSuccessful();
    }

    public function test_command_outputs_tsv_header_when_format_has_no_candidates(): void
    {
        $this->artisan('transactions:diagnose', [
            '--reason' => 'カード引落が expense として記録されている候補',
            '--format' => 'tsv',
        ])
            ->expectsOutput("transaction_id\tuser_id\tdate\ttype\taccount\ttransfer_account\tamount\tcurrency\tmerchant_name\tdescription\tmemo\t診断理由")
            ->doesntExpectOutput('診断候補は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_command_outputs_markdown_header_when_format_has_no_candidates(): void
    {
        $this->artisan('transactions:diagnose', [
            '--reason' => 'カード引落が expense として記録されている候補',
            '--format' => 'markdown',
        ])
            ->expectsOutput('| transaction_id | user_id | date | type | account | transfer_account | amount | currency | merchant_name | description | memo | 診断理由 |')
            ->expectsOutput('| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |')
            ->doesntExpectOutput('診断候補は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_command_rejects_invalid_format(): void
    {
        $this->artisan('transactions:diagnose', [
            '--format' => 'json',
        ])
            ->expectsOutput('--format は table, tsv, markdown のいずれかを指定してください。')
            ->assertExitCode(2);
    }

    public function test_command_detects_duplicated_investment_flow_candidate(): void
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

        $first = Transaction::factory()->transfer($dpayAccount, $securitiesAccount)->create([
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

        $this->artisan('transactions:diagnose', [
            '--reason' => '積立フローの二重表現候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', 'date', 'type', 'account', 'transfer_account', 'amount', 'currency', 'merchant_name', 'description', 'memo', '診断理由'],
                [
                    [
                        (string) $second->id,
                        (string) $user->id,
                        '2026-04-10',
                        'transfer',
                        'dカード',
                        'THEO',
                        '30000.00',
                        'JPY',
                        $second->merchant_name ?? '-',
                        $second->description ?? '-',
                        $second->memo ?? '-',
                        '積立フローの二重表現候補',
                    ],
                    [
                        (string) $first->id,
                        (string) $user->id,
                        '2026-04-10',
                        'transfer',
                        'd払い',
                        'THEO',
                        '30000.00',
                        'JPY',
                        $first->merchant_name ?? '-',
                        $first->description ?? '-',
                        $first->memo ?? '-',
                        '積立フローの二重表現候補',
                    ],
                ],
            )
            ->assertSuccessful();
    }

    public function test_command_detects_shopping_like_transfer_with_specific_reason(): void
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

        $this->artisan('transactions:diagnose', [
            '--reason' => 'shopping 系 transfer の確認候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', 'date', 'type', 'account', 'transfer_account', 'amount', 'currency', 'merchant_name', 'description', 'memo', '診断理由'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    '2026-04-19',
                    'transfer',
                    '銀行口座',
                    '電子マネー',
                    '1200.00',
                    'JPY',
                    'スーパー支払い',
                    $transaction->description ?? '-',
                    $transaction->memo ?? '-',
                    'shopping 系 transfer の確認候補',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_excludes_confirmed_shopping_transfer_pair(): void
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

        Transaction::factory()->transfer($source, $destination)->create([
            'user_id' => $user->id,
            'merchant_name' => 'd払いB/マルエツ',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
            'category_id' => null,
            'subcategory_id' => null,
            'is_calculation_target' => false,
        ]);
        Transaction::factory()->forAccount($destination)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => 'マルエツ',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
            'is_calculation_target' => true,
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => 'shopping 系 transfer の確認候補',
        ])
            ->expectsOutput('診断候補は見つかりませんでした。')
            ->assertSuccessful();
    }

    public function test_command_detects_uncategorized_category_entity(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => ' 未 分 類 ',
            'type' => 'expense',
        ]);
        $subcategory = Subcategory::factory()->for($user)->forCategory($category)->create([
            'name' => '未分類',
        ]);
        $foodCategory = Category::factory()->for($user)->create([
            'name' => '食費',
            'type' => 'expense',
        ]);

        $transaction = Transaction::factory()->forAccount($account)->forCategory($category, $subcategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '店舗',
            'transaction_date' => '2026-04-19',
            'amount' => '1200',
            'currency' => 'JPY',
        ]);
        $normalTransaction = Transaction::factory()->forAccount($account)->forCategory($foodCategory)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '別店舗',
            'transaction_date' => '2026-04-18',
            'amount' => '900',
            'currency' => 'JPY',
        ]);

        $this->artisan('transactions:diagnose', [
            '--reason' => '未分類カテゴリ実体の確認候補',
        ])
            ->expectsTable(
                ['transaction_id', 'user_id', 'date', 'type', 'account', 'transfer_account', 'amount', 'currency', 'merchant_name', 'description', 'memo', '診断理由'],
                [[
                    (string) $transaction->id,
                    (string) $user->id,
                    '2026-04-19',
                    'expense',
                    'd払い',
                    '-',
                    '1200.00',
                    'JPY',
                    '店舗',
                    $transaction->description ?? '-',
                    $transaction->memo ?? '-',
                    '未分類カテゴリ実体の確認候補',
                ]],
            )
            ->doesntExpectOutputToContain((string) $normalTransaction->id)
            ->assertSuccessful();
    }

    public function test_command_filters_by_user_and_limit(): void
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
        $other = Transaction::factory()->forAccount($otherBank)->create([
            'user_id' => $otherUser->id,
            'type' => 'expense',
            'merchant_name' => 'カード引落C',
            'transaction_date' => '2026-04-12',
        ]);

        $this->artisan('transactions:diagnose', [
            '--user' => $user->id,
            '--reason' => 'カード引落が expense として記録されている候補',
            '--limit' => 1,
        ])
            ->expectsOutputToContain((string) $first->id)
            ->doesntExpectOutputToContain((string) $second->id)
            ->doesntExpectOutputToContain((string) $other->id)
            ->assertSuccessful();
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

        $this->artisan('transactions:diagnose')->assertSuccessful();

        $transaction->refresh();

        $this->assertSame('before', $transaction->description);
        $this->assertSame('before', $transaction->memo);
        $this->assertTrue($transaction->updated_at?->equalTo($originalUpdatedAt));
    }
}
