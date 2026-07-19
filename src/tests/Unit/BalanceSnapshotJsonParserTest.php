<?php

namespace Tests\Unit;

use App\Services\Imports\BalanceSnapshotJsonParser;
use RuntimeException;
use Tests\TestCase;

class BalanceSnapshotJsonParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ja');
    }

    public function test_it_parses_valuation_and_card_outstanding_balances(): void
    {
        $result = app(BalanceSnapshotJsonParser::class)->parse(json_encode([
            'format' => 'nkkakeist-balance-snapshot',
            'version' => 1,
            'source' => 'money_forward',
            'captured_at' => '2026-07-18T21:00:00+09:00',
            'diagnostics' => [
                'exporter_version' => 2,
                'portfolio_summary_table' => true,
                'investment_tables' => 2,
                'deposit_table' => true,
                'pension_table' => true,
                'liability_tables' => 1,
            ],
            'asset_history' => [
                'captured_on' => '2026-07-18',
                'total_assets' => '22667260',
                'currency' => 'JPY',
                'breakdown' => ['投資信託' => '15525499'],
            ],
            'items' => [
                [
                    'source_account_name' => 'THEO',
                    'balance_kind' => 'valuation',
                    'balance' => '412345.67',
                    'currency' => 'jpy',
                    'source_updated_at' => '2026-07-18T20:30:00+09:00',
                    'positions' => [[
                        'instrument_name' => 'グロース株式',
                        'external_id' => 'money_forward:THEO:グロース株式',
                        'asset_class' => 'investment_fund',
                        'quantity' => '123.45678901',
                        'average_acquisition_price' => '9876.5',
                        'unit_price' => '10001',
                        'acquisition_cost' => '475430',
                        'valuation' => '123456',
                        'unrealized_gain' => '-1200',
                    ]],
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
        self::assertSame(2, $result['metadata']['acquisition_diagnostics']['exporter_version']);
        self::assertSame(2, $result['metadata']['acquisition_diagnostics']['investment_tables']);
        self::assertSame('22667260.00', $result['metadata']['asset_history']['total_assets']);
        self::assertSame('15525499.00', $result['metadata']['asset_history']['breakdown']['投資信託']);
        self::assertCount(2, $result['rows']);
        self::assertSame('412345.67', $result['rows'][0]['amount']);
        self::assertSame('2026-07-18', $result['rows'][0]['transaction_date']);
        self::assertSame('123.45678901', $result['rows'][0]['raw_payload']['positions'][0]['quantity']);
        self::assertSame('9876.500000', $result['rows'][0]['raw_payload']['positions'][0]['average_acquisition_price']);
        self::assertSame('475430.00', $result['rows'][0]['raw_payload']['positions'][0]['acquisition_cost']);
        self::assertSame('123456.00', $result['rows'][0]['raw_payload']['positions'][0]['valuation']);
        self::assertSame('JPY', $result['rows'][0]['raw_payload']['positions'][0]['currency']);
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

    public function test_it_rejects_invalid_screen_structure_diagnostics(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('画面構造チェック情報が不正です。');

        app(BalanceSnapshotJsonParser::class)->parse(json_encode([
            'format' => 'nkkakeist-balance-snapshot',
            'version' => 1,
            'source' => 'money_forward',
            'captured_at' => '2026-07-18T21:00:00+09:00',
            'diagnostics' => [
                'exporter_version' => 2,
                'portfolio_summary_table' => 'unknown',
                'investment_tables' => 0,
                'deposit_table' => false,
                'pension_table' => false,
                'liability_tables' => 0,
            ],
            'items' => [[
                'source_account_name' => 'ソニー銀行',
                'balance_kind' => 'account_balance',
                'balance' => '1000',
                'currency' => 'JPY',
            ]],
        ], JSON_THROW_ON_ERROR));
    }

    public function test_it_accepts_missing_portfolio_summary_with_valid_detail_diagnostics(): void
    {
        $result = app(BalanceSnapshotJsonParser::class)->parse(json_encode([
            'format' => 'nkkakeist-balance-snapshot',
            'version' => 1,
            'source' => 'money_forward',
            'captured_at' => '2026-07-18T21:00:00+09:00',
            'diagnostics' => [
                'exporter_version' => 2,
                'portfolio_summary_table' => false,
                'investment_tables' => 1,
                'deposit_table' => true,
                'pension_table' => false,
                'liability_tables' => 1,
            ],
            'items' => [[
                'source_account_name' => 'ソニー銀行',
                'balance_kind' => 'account_balance',
                'balance' => '1000',
                'currency' => 'JPY',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertFalse($result['metadata']['acquisition_diagnostics']['portfolio_summary_table']);
        self::assertNull($result['metadata']['asset_history']);
        self::assertSame('1000.00', $result['rows'][0]['amount']);
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

    public function test_it_rejects_positions_on_a_bank_balance(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('銘柄明細は時価評価額にのみ指定できます。');

        app(BalanceSnapshotJsonParser::class)->parse(json_encode([
            'format' => 'nkkakeist-balance-snapshot',
            'version' => 1,
            'source' => 'money_forward',
            'captured_at' => '2026-07-18T21:00:00+09:00',
            'items' => [[
                'source_account_name' => 'ソニー銀行',
                'balance_kind' => 'account_balance',
                'balance' => '1000',
                'currency' => 'JPY',
                'positions' => [[
                    'instrument_name' => '不正な明細',
                    'valuation' => '1000',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));
    }

    public function test_it_rejects_semantically_duplicate_positions_with_different_external_ids(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('1件目の銘柄明細2件目が同じ口座内で重複しています。');

        app(BalanceSnapshotJsonParser::class)->parse(json_encode([
            'format' => 'nkkakeist-balance-snapshot',
            'version' => 1,
            'source' => 'money_forward',
            'captured_at' => '2026-07-18T21:00:00+09:00',
            'items' => [[
                'source_account_name' => 'Money Forward 年金',
                'balance_kind' => 'valuation',
                'balance' => '2000',
                'currency' => 'JPY',
                'positions' => [
                    [
                        'instrument_name' => '同じ銘柄',
                        'external_id' => 'money_forward:JIS&T:同じ銘柄',
                        'valuation' => '1000',
                    ],
                    [
                        'instrument_name' => ' 同じ銘柄 ',
                        'external_id' => 'money_forward:pension:同じ銘柄',
                        'valuation' => '1000',
                    ],
                ],
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}
