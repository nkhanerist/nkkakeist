<?php

namespace App\Services\Imports;

class ImportMessageLocalizer
{
    /**
     * @param  array<int, string>  $messages
     * @return array<int, string>
     */
    public function messages(array $messages): array
    {
        return array_map($this->message(...), $messages);
    }

    /**
     * @param  array{source_resolution_type: ?string, source_resolution_message: ?string, destination_resolution_type: ?string, destination_resolution_message: ?string, unresolved_reason: ?string}  $resolution
     * @return array{source_resolution_type: ?string, source_resolution_message: ?string, destination_resolution_type: ?string, destination_resolution_message: ?string, unresolved_reason: ?string}
     */
    public function transferResolution(array $resolution): array
    {
        foreach ([
            'source_resolution_message',
            'destination_resolution_message',
            'unresolved_reason',
        ] as $key) {
            if (is_string($resolution[$key])) {
                $resolution[$key] = $this->message($resolution[$key]);
            }
        }

        return $resolution;
    }

    public function message(string $message): string
    {
        $key = match ($message) {
            '取込先口座を特定できません。' => 'destination_required',
            '同名の口座が複数あるため取込先口座を特定できません。共通適用口座を選択してください。' => 'destination_ambiguous',
            '振替元口座と振替先口座は同じにできません。' => 'transfer_accounts_same',
            '取引日を解釈できません。' => 'date_invalid',
            '金額を解釈できません。' => 'amount_invalid',
            '取引種別を判定できません。' => 'type_invalid',
            '中項目を使う場合は大項目が必要です。' => 'subcategory_without_category',
            '既存カテゴリの種別が取引種別と一致していません。' => 'category_type_mismatch',
            '同じ日の残高がすでにあります。既存値を確認してから取り込んでください。' => 'same_day_balance',
            '取込先口座を1件に特定できません。口座を選択してください。' => 'balance_account_required',
            '取得データと取込先口座の通貨が一致しません。' => 'balance_currency_mismatch',
            '時価評価額は評価額スナップショット方式の口座へ取り込んでください。' => 'valuation_account_invalid',
            'カード未払残高は負債口座へ取り込んでください。' => 'card_account_invalid',
            '公式口座残高は台帳方式の資産・負債口座へ取り込んでください。' => 'official_balance_account_invalid',
            '残高種別を解釈できません。' => 'balance_kind_invalid',
            '資産履歴の日付を解釈できません。' => 'asset_history_date_invalid',
            '総資産額が不正です。' => 'asset_history_amount_invalid',
            '振替元口座は口座名・取込用別名の候補が複数あり、自動決定できません。' => 'transfer_source_ambiguous_message',
            '振替元口座候補が複数あります。' => 'transfer_source_ambiguous_reason',
            '振替元口座を特定できません。' => 'transfer_source_required',
            'CSV の保有金融機関と一致する振替元口座を見つけられませんでした。' => 'transfer_source_not_matched',
            '振替元口座を取得できませんでした。' => 'transfer_source_not_loaded',
            '振替先口座を特定できません。' => 'transfer_destination_required',
            '摘要 / 説明 / メモから振替先口座を特定できませんでした。' => 'transfer_destination_not_matched',
            '振替先として一致する口座が複数あるため、相手口座を特定できません。' => 'transfer_destination_ambiguous_error',
            '振替先候補が複数あるため、自動決定できません。' => 'transfer_destination_ambiguous_message',
            '振替先候補が複数あります。' => 'transfer_destination_ambiguous_reason',
            '振替先口座を取得できませんでした。' => 'transfer_destination_not_loaded',
            '振替方向を安全に特定できないため、相手口座を自動決定できません。' => 'transfer_direction_error',
            '振替方向を安全に特定できません。' => 'transfer_direction_reason',
            '振替元口座と振替先口座は同じ通貨である必要があります。' => 'transfer_currency_error',
            '振替元口座と振替先口座の通貨が一致しません。' => 'transfer_currency_reason',
            'CSV の保有金融機関が空のため、振替元口座を判定できません。' => 'transfer_source_empty',
            '振替元口座は口座名一致で解決しました。' => 'transfer_source_name',
            '振替元口座は取込用別名一致で解決しました。' => 'transfer_source_alias',
            '振替先口座は手動指定で解決しました。' => 'transfer_destination_manual',
            '振替先口座は摘要 / 説明の口座名完全一致で解決しました。' => 'transfer_destination_exact_name',
            '振替先口座は取込用別名の完全一致で解決しました。' => 'transfer_destination_exact_alias',
            '振替先口座は摘要 / 説明の部分一致で解決しました。' => 'transfer_destination_partial',
            default => null,
        };

        return $key === null ? $message : trans("imports.stored_messages.{$key}");
    }
}
