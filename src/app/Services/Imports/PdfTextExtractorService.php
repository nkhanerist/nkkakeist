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
            throw new RuntimeException(trans('imports.parse_errors.pdf_invalid'));
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'mobile-suica-');

        if ($temporaryPath === false) {
            throw new RuntimeException(trans('imports.parse_errors.pdf_temp_create_failed'));
        }

        try {
            if (file_put_contents($temporaryPath, $contents) === false) {
                throw new RuntimeException(trans('imports.parse_errors.pdf_temp_save_failed'));
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
                throw new RuntimeException(trans('imports.parse_errors.pdf_text_extract_failed'));
            }

            $text = trim($process->getOutput());

            if ($text === '') {
                throw new RuntimeException(trans('imports.parse_errors.pdf_text_missing'));
            }

            return $text;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new RuntimeException(
                trans('imports.parse_errors.pdf_tool_unavailable'),
                previous: $throwable,
            );
        } finally {
            @unlink($temporaryPath);
        }
    }
}
