<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionDiagnosticsSummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_reason_counts_and_date_range(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'transaction_date' => '2026-04-10',
            'amount' => '5000',
            'currency' => 'JPY',
        ]);
        Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'transaction_date' => '2026-04-20',
            'amount' => '6000',
            'currency' => 'JPY',
        ]);

        $this->artisan('transactions:diagnose-summary')
            ->expectsTable(
                ['診断理由', '件数', '最新日', '最古日'],
                [
                    ['カード引落が expense として記録されている候補', '2', '2026-04-20', '2026-04-10'],
                    ['コード決済請求付替が expense として記録されている候補', '0', '-', '-'],
                    ['現金引き出し/チャージが expense として記録されている候補', '0', '-', '-'],
                    ['積立フローの二重表現候補', '0', '-', '-'],
                    ['カテゴリ付き transfer の確認候補', '0', '-', '-'],
                    ['shopping 系 transfer の確認候補', '0', '-', '-'],
                    ['未分類カテゴリ実体の確認候補', '0', '-', '-'],
                ],
            )
            ->assertSuccessful();
    }

    public function test_command_filters_by_user(): void
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

        Transaction::factory()->forAccount($userBank)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'transaction_date' => '2026-04-10',
        ]);
        Transaction::factory()->forAccount($otherBank)->create([
            'user_id' => $otherUser->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'transaction_date' => '2026-04-20',
        ]);

        $this->artisan('transactions:diagnose-summary', [
            '--user' => $user->id,
        ])
            ->expectsTable(
                ['診断理由', '件数', '最新日', '最古日'],
                [
                    ['カード引落が expense として記録されている候補', '1', '2026-04-10', '2026-04-10'],
                    ['コード決済請求付替が expense として記録されている候補', '0', '-', '-'],
                    ['現金引き出し/チャージが expense として記録されている候補', '0', '-', '-'],
                    ['積立フローの二重表現候補', '0', '-', '-'],
                    ['カテゴリ付き transfer の確認候補', '0', '-', '-'],
                    ['shopping 系 transfer の確認候補', '0', '-', '-'],
                    ['未分類カテゴリ実体の確認候補', '0', '-', '-'],
                ],
            )
            ->assertSuccessful();
    }

    public function test_command_outputs_only_nonzero_reason_counts(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->forAccount($bankAccount)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'transaction_date' => '2026-04-10',
            'amount' => '5000',
            'currency' => 'JPY',
        ]);

        $this->artisan('transactions:diagnose-summary', [
            '--only-nonzero' => true,
        ])
            ->expectsTable(
                ['診断理由', '件数', '最新日', '最古日'],
                [
                    ['カード引落が expense として記録されている候補', '1', '2026-04-10', '2026-04-10'],
                ],
            )
            ->doesntExpectOutputToContain('コード決済請求付替が expense として記録されている候補')
            ->doesntExpectOutputToContain('shopping 系 transfer の確認候補')
            ->assertSuccessful();
    }

    public function test_command_outputs_message_when_only_nonzero_has_no_candidates(): void
    {
        $this->artisan('transactions:diagnose-summary', [
            '--only-nonzero' => true,
        ])
            ->expectsOutput('診断候補はありません。')
            ->assertSuccessful();
    }

    public function test_command_outputs_only_nonzero_reason_counts_filtered_by_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $userBank = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);
        $otherBank = Account::factory()->for($otherUser)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
            'currency' => 'JPY',
        ]);

        Transaction::factory()->forAccount($userBank)->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'merchant_name' => '通常利用',
            'transaction_date' => '2026-04-10',
        ]);
        Transaction::factory()->forAccount($otherBank)->create([
            'user_id' => $otherUser->id,
            'type' => 'expense',
            'merchant_name' => '三井住友カード引落',
            'transaction_date' => '2026-04-20',
        ]);

        $this->artisan('transactions:diagnose-summary', [
            '--user' => $user->id,
            '--only-nonzero' => true,
        ])
            ->expectsOutput('診断候補はありません。')
            ->doesntExpectOutputToContain('三井住友カード引落')
            ->assertSuccessful();
    }

    public function test_command_rejects_invalid_user(): void
    {
        $this->artisan('transactions:diagnose-summary', [
            '--user' => 'abc',
        ])
            ->expectsOutput('--user は正の整数で指定してください。')
            ->assertExitCode(2);
    }

    public function test_command_is_read_only(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create([
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

        $this->artisan('transactions:diagnose-summary')->assertSuccessful();

        $transaction->refresh();

        $this->assertSame('before', $transaction->description);
        $this->assertSame('before', $transaction->memo);
        $this->assertTrue($transaction->updated_at?->equalTo($originalUpdatedAt));
    }
}
