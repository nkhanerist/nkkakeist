<?php

namespace Tests\Unit;

use App\Services\Imports\MoneyForwardAssetHistoryCsvParser;
use Tests\TestCase;

class MoneyForwardAssetHistoryCsvParserTest extends TestCase
{
    public function test_it_parses_cp932_asset_history_csv_with_dynamic_breakdown_columns(): void
    {
        $csv = implode("\r\n", [
            '"日付","合計（円）","預金・現金（円）","投資信託（円）","年金（円）","ポイント（円）"',
            '"2026/07/18","22,667,260","5,300,726","15,525,499","1,832,820","8,215"',
            '"2026/06/30","21,000,000","5,000,000","14,300,000","1,690,000","10,000"',
        ]);

        $result = app(MoneyForwardAssetHistoryCsvParser::class)->parse(
            mb_convert_encoding($csv, 'CP932', 'UTF-8'),
        );

        self::assertSame('money_forward_asset_history_csv', $result['metadata']['format']);
        self::assertCount(2, $result['rows']);
        self::assertSame('2026-07-18', $result['rows'][0]['transaction_date']);
        self::assertSame('22667260.00', $result['rows'][0]['amount']);
        self::assertSame('15525499.00', $result['rows'][0]['raw_payload']['breakdown']['投資信託']);
        self::assertSame('8215.00', $result['rows'][0]['raw_payload']['breakdown']['ポイント']);
    }
}
