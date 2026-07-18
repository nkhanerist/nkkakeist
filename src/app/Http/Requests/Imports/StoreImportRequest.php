<?php

namespace App\Http\Requests\Imports;

use App\Models\Import;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Import::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $isMobileSuica = $this->input('source_name') === 'mobile_suica';
        $isJrePoint = $this->input('source_name') === 'jre_point';
        $isBalanceSnapshot = $this->input('source_name') === 'balance_snapshot';

        return [
            'source_name' => ['required', 'string', Rule::in(['money_forward', 'mobile_suica', 'jre_point', 'balance_snapshot', 'asset_history'])],
            'account_id' => [
                ($isMobileSuica || $isJrePoint) ? 'required' : 'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(
                    fn ($query) => $query
                        ->where('user_id', $this->user()?->id)
                        ->when($isMobileSuica, fn ($query) => $query->where('type', 'e_money'))
                        ->when($isJrePoint, fn ($query) => $query->where('type', 'point')),
                ),
            ],
            'csv_file' => [
                'required',
                'file',
                $isMobileSuica
                    ? 'mimes:pdf'
                    : (($isJrePoint || $isBalanceSnapshot) ? 'mimes:json,txt' : 'mimes:csv,txt'),
                'max:10240',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => $this->input('source_name') === 'jre_point'
                ? 'JRE POINTの取込先口座を選択してください。'
                : 'モバイルSuicaの取込先口座を選択してください。',
            'account_id.exists' => $this->input('source_name') === 'jre_point'
                ? '取込先には自分のポイント口座を選択してください。'
                : '取込先には自分の電子マネー口座を選択してください。',
            'csv_file.mimes' => $this->input('source_name') === 'mobile_suica'
                ? 'モバイルSuicaから取得したPDFを選択してください。'
                : ($this->input('source_name') === 'jre_point'
                    ? 'JRE POINT書き出しツールで保存したJSONを選択してください。'
                    : ($this->input('source_name') === 'balance_snapshot'
                        ? '対応する残高取得ツールで保存したJSONを選択してください。'
                        : 'CSVファイルを選択してください。')),
            'csv_file.max' => 'ファイルサイズは10MB以下にしてください。',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_name' => $this->filled('source_name') ? $this->input('source_name') : 'money_forward',
        ]);
    }
}
