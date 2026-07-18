<?php

namespace App\Services\ClassificationRules;

use App\Models\ClassificationRule;
use App\Models\ImportRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApplyClassificationRulesService
{
    /**
     * @param  Collection<int, ClassificationRule>  $classificationRules
     * @return array{
     *     matched_classification_rule_id: int|null,
     *     rule_applied_fields: array<int, string>,
     *     resolved_category_id: int|null,
     *     resolved_subcategory_id: int|null,
     *     is_calculation_target: bool|null
     * }
     */
    public function handle(
        ImportRow $importRow,
        Collection $classificationRules,
        ?int $resolvedCategoryId,
        ?int $resolvedSubcategoryId,
    ): array {
        $currentCalculationTarget = $importRow->is_calculation_target;

        foreach ($classificationRules as $classificationRule) {
            if (! $this->matches($classificationRule, $importRow)) {
                continue;
            }

            $nextCategoryId = $resolvedCategoryId;
            $nextSubcategoryId = $resolvedSubcategoryId;
            $nextCalculationTarget = $currentCalculationTarget;
            $appliedFields = [];

            if ($nextCategoryId === null && $classificationRule->category_id !== null) {
                $nextCategoryId = $classificationRule->category_id;
                $appliedFields[] = 'category';
            }

            if (
                $nextSubcategoryId === null
                && $classificationRule->subcategory_id !== null
                && $classificationRule->subcategory !== null
            ) {
                $targetCategoryId = $nextCategoryId ?? $classificationRule->category_id;

                if (
                    $targetCategoryId !== null
                    && $classificationRule->subcategory->category_id === $targetCategoryId
                ) {
                    $nextSubcategoryId = $classificationRule->subcategory_id;
                    $appliedFields[] = 'subcategory';
                }
            }

            if ($nextCalculationTarget === null && $classificationRule->is_calculation_target !== null) {
                $nextCalculationTarget = $classificationRule->is_calculation_target;
                $appliedFields[] = 'is_calculation_target';
            }

            if ($appliedFields === []) {
                continue;
            }

            return [
                'matched_classification_rule_id' => $classificationRule->id,
                'rule_applied_fields' => $appliedFields,
                'resolved_category_id' => $nextCategoryId,
                'resolved_subcategory_id' => $nextSubcategoryId,
                'is_calculation_target' => $nextCalculationTarget,
            ];
        }

        return [
            'matched_classification_rule_id' => null,
            'rule_applied_fields' => [],
            'resolved_category_id' => $resolvedCategoryId,
            'resolved_subcategory_id' => $resolvedSubcategoryId,
            'is_calculation_target' => $currentCalculationTarget,
        ];
    }

    private function matches(ClassificationRule $classificationRule, ImportRow $importRow): bool
    {
        $transactionType = $classificationRule->transaction_type;

        if (
            $transactionType !== null
            && $transactionType !== 'any'
            && $transactionType !== $importRow->detected_type
        ) {
            return false;
        }

        $fieldValue = match ($classificationRule->match_field) {
            'merchant_name' => $importRow->merchant_name,
            'description' => $importRow->description,
            'account_name' => $importRow->account_name,
            default => null,
        };

        $normalizedFieldValue = $this->normalize((string) ($fieldValue ?? ''));
        $normalizedMatchValue = $this->normalize($classificationRule->match_value);

        if ($normalizedFieldValue === '' || $normalizedMatchValue === '') {
            return false;
        }

        return match ($classificationRule->match_operator) {
            'equals' => $normalizedFieldValue === $normalizedMatchValue,
            'starts_with' => str_starts_with($normalizedFieldValue, $normalizedMatchValue),
            'contains' => str_contains($normalizedFieldValue, $normalizedMatchValue),
            default => false,
        };
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::squish($value));
    }
}
