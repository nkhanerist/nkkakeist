<?php

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Account::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Account::types())],
            'balance_role' => ['required', 'string', Rule::in(Account::balanceRoles())],
            'balance_method' => ['required', 'string', Rule::in(Account::balanceMethods())],
            'include_in_net_worth' => ['required', 'boolean'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'initial_balance' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99'],
            'opening_balance_date' => ['nullable', 'date'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'import_aliases' => ['nullable', 'array'],
            'import_aliases.*' => ['string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('type', 'cash');
        $defaultRole = match ($type) {
            'credit_card' => 'liability',
            'other' => 'clearing',
            default => 'asset',
        };
        $balanceRole = (string) $this->input('balance_role', $defaultRole);
        $defaultMethod = $type === 'securities' ? 'snapshot' : 'ledger';
        $aliases = $this->input('import_aliases', []);

        if (is_string($aliases)) {
            $aliases = preg_split('/\r\n|\r|\n/', $aliases) ?: [];
        }

        if (! is_array($aliases)) {
            $aliases = [];
        }

        $normalizedAliases = collect($aliases)
            ->map(fn ($alias): string => trim((string) $alias))
            ->filter(fn (string $alias): bool => $alias !== '')
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', '')),
            'balance_role' => $balanceRole,
            'balance_method' => (string) $this->input('balance_method', $defaultMethod),
            'include_in_net_worth' => $balanceRole === 'clearing'
                ? false
                : ($this->has('include_in_net_worth') ? $this->boolean('include_in_net_worth') : true),
            'opening_balance_date' => $this->filled('opening_balance_date')
                ? $this->input('opening_balance_date')
                : null,
            'display_order' => $this->filled('display_order') ? $this->input('display_order') : 0,
            'is_active' => $this->boolean('is_active'),
            'import_aliases' => $normalizedAliases,
        ]);
    }
}
