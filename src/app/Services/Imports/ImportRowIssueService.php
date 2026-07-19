<?php

namespace App\Services\Imports;

use Illuminate\Database\Eloquent\Builder;

class ImportRowIssueService
{
    /**
     * These messages describe intentional, completed corrections on legacy rows.
     * They remain stored for auditability but do not require user action.
     *
     * @var array<int, string>
     */
    private const ADVISORY_MESSAGES = [
        '資産残高補正: カード側にも同一チャージがあるため鏡像行をスキップしました。',
        'Kyash残高補正: 同じdカードチャージの反対側明細があるため鏡像行をスキップしました。',
    ];

    /**
     * @param  array<int, string>  $messages
     * @return array{errors: array<int, string>, advisories: array<int, string>}
     */
    public function partition(array $messages): array
    {
        $errors = [];
        $advisories = [];

        foreach ($messages as $message) {
            if (in_array($message, self::ADVISORY_MESSAGES, true)) {
                $advisories[] = $message;
            } else {
                $errors[] = $message;
            }
        }

        return [
            'errors' => $errors,
            'advisories' => $advisories,
        ];
    }

    public function constrainActionRequired(Builder $query): void
    {
        $query->whereJsonLength('validation_errors', '>', 0);

        foreach (self::ADVISORY_MESSAGES as $message) {
            $query->where(function (Builder $query) use ($message): void {
                $query
                    ->whereJsonLength('validation_errors', '!=', 1)
                    ->orWhereJsonDoesntContain('validation_errors', $message);
            });
        }
    }

    public function constrainAdvisory(Builder $query): void
    {
        $query
            ->whereJsonLength('validation_errors', 1)
            ->where(function (Builder $query): void {
                foreach (self::ADVISORY_MESSAGES as $message) {
                    $query->orWhereJsonContains('validation_errors', $message);
                }
            });
    }
}
