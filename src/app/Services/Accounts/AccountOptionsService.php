<?php

namespace App\Services\Accounts;

class AccountOptionsService
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function typeOptions(): array
    {
        return [
            ['value' => 'cash', 'label' => '現金'],
            ['value' => 'bank', 'label' => '銀行口座'],
            ['value' => 'credit_card', 'label' => 'クレジットカード'],
            ['value' => 'e_money', 'label' => '電子マネー'],
            ['value' => 'securities', 'label' => '証券'],
            ['value' => 'point', 'label' => 'ポイント'],
            ['value' => 'other', 'label' => 'その他'],
        ];
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
        return [
            ['value' => 'asset', 'label' => '資産'],
            ['value' => 'liability', 'label' => '負債'],
            ['value' => 'clearing', 'label' => '中継口座'],
        ];
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
        return [
            ['value' => 'ledger', 'label' => '取引台帳から計算'],
            ['value' => 'snapshot', 'label' => '評価額スナップショット'],
        ];
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
}
