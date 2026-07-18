<?php

namespace Tests\Unit;

use App\Services\Imports\MobileSuicaPdfParser;
use App\Services\Imports\PdfTextExtractorService;
use RuntimeException;
use Tests\TestCase;

class MobileSuicaPdfParserTest extends TestCase
{
    public function test_it_parses_only_expenses_and_maps_transport_categories(): void
    {
        $parser = new MobileSuicaPdfParser(new PdfTextExtractorService);

        $rows = $parser->parseExtractedText($this->sampleText());

        $this->assertCount(3, $rows);
        $this->assertSame('2026-12-31', $rows[0]['transaction_date']);
        $this->assertSame('300.00', $rows[0]['amount']);
        $this->assertSame('交通費', $rows[0]['category_name']);
        $this->assertSame('電車', $rows[0]['subcategory_name']);
        $this->assertSame('モバイルSuica 東京 → 品川', $rows[0]['merchant_name']);

        $this->assertSame('2027-01-02', $rows[1]['transaction_date']);
        $this->assertSame('200.00', $rows[1]['amount']);
        $this->assertNull($rows[1]['category_name']);
        $this->assertNull($rows[1]['subcategory_name']);
        $this->assertSame('モバイルSuica 物販', $rows[1]['merchant_name']);

        $this->assertSame('交通費', $rows[2]['category_name']);
        $this->assertSame('交通費', $rows[2]['subcategory_name']);
        $this->assertSame('モバイルSuica バス 都バス', $rows[2]['merchant_name']);
        $this->assertSame(
            $rows[0]['raw_payload']['ID'],
            $parser->parseExtractedText($this->sampleText())[0]['raw_payload']['ID'],
        );
    }

    public function test_it_rejects_a_pdf_when_the_running_balance_is_not_continuous(): void
    {
        $parser = new MobileSuicaPdfParser(new PdfTextExtractorService);
        $text = str_replace('\\4,700 -300', '\\4,701 -300', $this->sampleText());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF の残高が連続していません');

        $parser->parseExtractedText($text);
    }

    public function test_it_rejects_an_invalid_calendar_date(): void
    {
        $parser = new MobileSuicaPdfParser(new PdfTextExtractorService);
        $text = str_replace('01 02 物販', '02 30 物販', $this->sampleText());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF内の取引日を解釈できませんでした');

        $parser->parseExtractedText($text);
    }

    private function sampleText(): string
    {
        return <<<'TEXT'
モバイルSuica 利用履歴
JE*** **** **** 6040
12 31 繰 \5,000
12 31 入 東京 出 品川 \4,700 -300
01 01 現金 \14,700 +10,000
01 02 物販 \14,500 -200
01 02 バス等 都バス \14,270 -230
01 03 紛再 \14,270 0
2027/01/10
TEXT;
    }
}
