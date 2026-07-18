<?php

namespace Tests\Unit;

use App\Services\Imports\BalanceSnapshotJsonParser;
use RuntimeException;
use Tests\TestCase;

class BalanceSnapshotJsonParserTest extends TestCase
{
    public function test_it_parses_valuation_and_card_outstanding_balances(): void
    {
        $result = app(BalanceSnapshotJsonParser::class)->parse(json_encode([
            'format' => 'nkkakeist-balance-snapshot',
            'version' => 1,
            'source' => 'money_forward',
            'captured_at' => '2026-07-18T21:00:00+09:00',
            'items' => [
                [
                    'source_account_name' => 'THEO',
                    'balance_kind' => 'valuation',
                    'balance' => '412345.67',
                    'currency' => 'jpy',
                    'source_updated_at' => '2026-07-18T20:30:00+09:00',
                ],
                [
                    'source_account_name' => 'dカード',
                    'balance_kind' => 'card_outstanding',
                    'balance' => '65940',
                    'currency' => 'JPY',
                    'balance_date' => '2026-07-18',
                    'next_payment_amount' => '42000',
                    'next_payment_date' => '2026-08-10',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertSame('money_forward', $result['metadata']['source']);
        self::assertCount(2, $result['rows']);
        self::assertSame('412345.67', $result['rows'][0]['amount']);
        self::assertSame('2026-07-18', $result['rows'][0]['transaction_date']);
        self::assertSame('-65940.00', $result['rows'][1]['amount']);
        self::assertSame('42000.00', $result['rows'][1]['raw_payload']['next_payment_amount']);
        self::assertSame('2026-08-10', $result['rows'][1]['raw_payload']['next_payment_date']);
    }

    public function test_it_rejects_an_unknown_format(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('対応していない残高取得JSONです。');

        app(BalanceSnapshotJsonParser::class)->parse(json_encode([
            'format' => 'unknown',
            'version' => 1,
        ], JSON_THROW_ON_ERROR));
    }

    public function test_it_rejects_an_invalid_balance_date(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('1件目の残高日を解釈できませんでした。');

        app(BalanceSnapshotJsonParser::class)->parse(json_encode([
            'format' => 'nkkakeist-balance-snapshot',
            'version' => 1,
            'source' => 'money_forward',
            'captured_at' => '2026-07-18T21:00:00+09:00',
            'items' => [[
                'source_account_name' => 'THEO',
                'balance_kind' => 'valuation',
                'balance' => '1000',
                'currency' => 'JPY',
                'balance_date' => '2026-02-30',
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}
