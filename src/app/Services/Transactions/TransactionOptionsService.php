<?php

namespace App\Services\Transactions;

use App\Models\User;

class TransactionOptionsService
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function typeOptions(): array
    {
        return collect(['income', 'expense', 'transfer'])
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => trans("transactions.types.{$type}"),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function typeLabels(): array
    {
        $labels = [];

        foreach ($this->typeOptions() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return $labels;
    }

    /**
     * @return array<int, array{id: int, name: string, type: string, currency: string, is_active: bool}>
     */
    public function accountOptions(User $user): array
    {
        return $user->accounts()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency' => $account->currency,
                'is_active' => $account->is_active,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, type: string, is_active: bool}>
     */
    public function categoryOptions(User $user): array
    {
        return $user->categories()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'is_active' => $category->is_active,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function currencyOptions(User $user): array
    {
        $accountCurrencies = $user->accounts()
            ->distinct()
            ->pluck('currency');
        $transactionCurrencies = $user->transactions()
            ->distinct()
            ->pluck('currency');

        return $accountCurrencies
            ->merge($transactionCurrencies)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($currency): string => (string) $currency)
            ->all();
    }

    /**
     * @return array<int, array{id: int, category_id: int, name: string, is_active: bool}>
     */
    public function subcategoryOptions(User $user): array
    {
        return $user->subcategories()
            ->orderBy('category_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($subcategory): array => [
                'id' => $subcategory->id,
                'category_id' => $subcategory->category_id,
                'name' => $subcategory->name,
                'is_active' => $subcategory->is_active,
            ])
            ->all();
    }
}
