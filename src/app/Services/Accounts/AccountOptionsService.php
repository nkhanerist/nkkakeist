<?php

namespace App\Services\Accounts;

class AccountOptionsService
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function typeOptions(): array
    {
        return $this->translatedOptions('types', ['cash', 'bank', 'credit_card', 'e_money', 'securities', 'point', 'other']);
    }

    /**
     * @return array<string, string>
     */
    public function typeLabels(): array
    {
        return $this->labels($this->typeOptions());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function balanceRoleOptions(): array
    {
        return $this->translatedOptions('balance_roles', ['asset', 'liability', 'clearing']);
    }

    /**
     * @return array<string, string>
     */
    public function balanceRoleLabels(): array
    {
        return $this->labels($this->balanceRoleOptions());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function balanceMethodOptions(): array
    {
        return $this->translatedOptions('balance_methods', ['ledger', 'snapshot']);
    }

    /**
     * @return array<string, string>
     */
    public function balanceMethodLabels(): array
    {
        return $this->labels($this->balanceMethodOptions());
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     * @return array<string, string>
     */
    private function labels(array $options): array
    {
        $labels = [];

        foreach ($options as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return $labels;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    private function translatedOptions(string $group, array $values): array
    {
        return array_map(
            fn (string $value): array => [
                'value' => $value,
                'label' => trans("accounts.{$group}.{$value}"),
            ],
            $values,
        );
    }
}
