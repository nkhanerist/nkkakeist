<?php

namespace Tests\Unit;

use App\Services\Imports\JrePointJsonParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JrePointJsonParserTest extends TestCase
{
    #[Test]
    public function it_parses_point_acquisition_and_mobile_suica_charge(): void
    {
        $result = (new JrePointJsonParser)->parse(json_encode([
            'format' => 'nkkakeist-jre-point-history',
            'version' => 1,
            'captured_at' => '2026-07-18T14:00:00+09:00',
            'balance' => [
                'total' => 597,
                'limited' => 10,
                'nearest_expiry' => '2026-08-31',
            ],
            'page_count' => 5,
            'rows' => [
                [
                    'reflection_date' => '2026-07-13',
                    'place' => 'ＪＲ東日本',
                    'description' => '７／５週分　在来線乗車ポイント',
                    'points' => 16,
                    'source_icon' => 'ico-train-x32.svg',
                ],
                [
                    'reflection_date' => '2026-03-26',
                    'place' => 'モバイルＳｕｉｃａ',
                    'description' => '３／２５　チャージ',
                    'points' => -3541,
                    'source_icon' => 'ico-app.svg',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertSame(597, $result['metadata']['balance']['total']);
        self::assertSame(587, $result['metadata']['balance']['regular']);
        self::assertSame('income', $result['rows'][0]['detected_type']);
        self::assertFalse($result['rows'][0]['is_calculation_target']);
        self::assertTrue($result['rows'][0]['affects_account_balance']);
        self::assertSame('収入', $result['rows'][0]['category_name']);
        self::assertSame('ポイント獲得', $result['rows'][0]['subcategory_name']);
        self::assertSame('transfer', $result['rows'][1]['detected_type']);
        self::assertSame('2026-03-25', $result['rows'][1]['transaction_date']);
        self::assertSame('3541.00', $result['rows'][1]['amount']);
        self::assertStringStartsWith('jre-point-v1-', $result['rows'][1]['raw_payload']['ID']);
    }

    #[Test]
    public function it_rejects_unknown_export_format(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('対応していないJRE POINT書き出し形式です。');

        (new JrePointJsonParser)->parse('{"format":"unknown","version":1}');
    }
}
