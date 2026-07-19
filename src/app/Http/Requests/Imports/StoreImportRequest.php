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
                ? trans('imports.messages.jre_point_account_required')
                : trans('imports.messages.mobile_suica_account_required'),
            'account_id.exists' => $this->input('source_name') === 'jre_point'
                ? trans('imports.messages.jre_point_account_invalid')
                : trans('imports.messages.mobile_suica_account_invalid'),
            'csv_file.mimes' => $this->input('source_name') === 'mobile_suica'
                ? trans('imports.messages.mobile_suica_file_invalid')
                : ($this->input('source_name') === 'jre_point'
                    ? trans('imports.messages.jre_point_file_invalid')
                    : ($this->input('source_name') === 'balance_snapshot'
                        ? trans('imports.messages.balance_snapshot_file_invalid')
                        : trans('imports.messages.csv_file_invalid'))),
            'csv_file.max' => trans('imports.messages.file_too_large'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('imports.fields');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_name' => $this->filled('source_name') ? $this->input('source_name') : 'money_forward',
        ]);
    }
}
