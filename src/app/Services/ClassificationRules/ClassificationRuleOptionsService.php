<?php

namespace App\Services\ClassificationRules;

use App\Models\ClassificationRule;
use App\Models\User;

class ClassificationRuleOptionsService
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function transactionTypeOptions(): array
    {
        return $this->translatedOptions('transaction_types', ['any', 'income', 'expense']);
    }

    /**
     * @return array<string, string>
     */
    public function transactionTypeLabels(): array
    {
        return $this->translatedLabels('transaction_types', ['any', 'income', 'expense', 'transfer']);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function matchFieldOptions(): array
    {
        return $this->translatedOptions('match_fields', ['merchant_name', 'description', 'account_name']);
    }

    /**
     * @return array<string, string>
     */
    public function matchFieldLabels(): array
    {
        return $this->translatedLabels('match_fields', ['merchant_name', 'description', 'account_name']);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function matchOperatorOptions(): array
    {
        return $this->translatedOptions('match_operators', ['contains', 'equals', 'starts_with']);
    }

    /**
     * @return array<string, string>
     */
    public function matchOperatorLabels(): array
    {
        return $this->translatedLabels('match_operators', ['contains', 'equals', 'starts_with']);
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

    /**
     * @return array{id: int, name: string, transaction_type: string|null, transaction_type_label: string, match_field: string, match_field_label: string, match_operator: string, match_operator_label: string, match_value: string, category: array{id: int, name: string}|null, subcategory: array{id: int, name: string}|null, is_calculation_target: bool|null, priority: int, is_active: bool}
     */
    public function classificationRuleItem(ClassificationRule $classificationRule): array
    {
        $transactionTypeLabels = $this->transactionTypeLabels();
        $matchFieldLabels = $this->matchFieldLabels();
        $matchOperatorLabels = $this->matchOperatorLabels();
        $transactionType = $classificationRule->transaction_type ?? 'any';

        return [
            'id' => $classificationRule->id,
            'name' => $classificationRule->name,
            'transaction_type' => $classificationRule->transaction_type,
            'transaction_type_label' => $transactionTypeLabels[$transactionType] ?? $transactionType,
            'match_field' => $classificationRule->match_field,
            'match_field_label' => $matchFieldLabels[$classificationRule->match_field] ?? $classificationRule->match_field,
            'match_operator' => $classificationRule->match_operator,
            'match_operator_label' => $matchOperatorLabels[$classificationRule->match_operator] ?? $classificationRule->match_operator,
            'match_value' => $classificationRule->match_value,
            'category' => $classificationRule->category === null ? null : [
                'id' => $classificationRule->category->id,
                'name' => $classificationRule->category->name,
            ],
            'subcategory' => $classificationRule->subcategory === null ? null : [
                'id' => $classificationRule->subcategory->id,
                'name' => $classificationRule->subcategory->name,
            ],
            'is_calculation_target' => $classificationRule->is_calculation_target,
            'priority' => $classificationRule->priority,
            'is_active' => $classificationRule->is_active,
        ];
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
                'label' => trans("classification_rules.{$group}.{$value}"),
            ],
            $values,
        );
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    private function translatedLabels(string $group, array $values): array
    {
        return collect($this->translatedOptions($group, $values))
            ->mapWithKeys(fn (array $option): array => [$option['value'] => $option['label']])
            ->all();
    }
}
