<?php

namespace App\Services\Imports;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PdfTextExtractorService
{
    public function extract(string $contents): string
    {
        if (! str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException('PDF ファイルとして認識できませんでした。');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'mobile-suica-');

        if ($temporaryPath === false) {
            throw new RuntimeException('PDF 解析用の一時ファイルを作成できませんでした。');
        }

        try {
            if (file_put_contents($temporaryPath, $contents) === false) {
                throw new RuntimeException('PDF 解析用の一時ファイルを保存できませんでした。');
            }

            $process = new Process([
                'pdftotext',
                '-layout',
                '-enc',
                'UTF-8',
                $temporaryPath,
                '-',
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('PDF から文字を抽出できませんでした。');
            }

            $text = trim($process->getOutput());

            if ($text === '') {
                throw new RuntimeException('PDF に解析可能な文字情報がありません。');
            }

            return $text;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new RuntimeException(
                'PDF 解析を実行できませんでした。開発環境を再ビルドしてください。',
                previous: $throwable,
            );
        } finally {
            @unlink($temporaryPath);
        }
    }
}
