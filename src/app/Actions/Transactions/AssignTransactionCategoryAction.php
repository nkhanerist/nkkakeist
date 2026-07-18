<?php

namespace App\Actions\Transactions;

use App\Actions\ClassificationRules\StoreClassificationRuleAction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignTransactionCategoryAction
{
    public function __construct(
        private readonly StoreClassificationRuleAction $storeClassificationRuleAction,
    ) {}

    /**
     * @param  array{category_id:int, subcategory_id:?int, create_rule:bool, rule_match_field?:string, rule_match_operator?:string, rule_match_value?:string}  $attributes
     */
    public function handle(Transaction $transaction, array $attributes): Transaction
    {
        return DB::transaction(function () use ($transaction, $attributes): Transaction {
            $transaction->update([
                'category_id' => $attributes['category_id'],
                'subcategory_id' => $attributes['subcategory_id'] ?? null,
            ]);

            if ($attributes['create_rule']) {
                $category = $transaction->user->categories()->findOrFail($attributes['category_id']);
                $subcategory = $attributes['subcategory_id'] === null
                    ? null
                    : $category->subcategories()->findOrFail($attributes['subcategory_id']);
                $targetName = $category->name.($subcategory === null ? '' : " / {$subcategory->name}");
                $matchValue = (string) $attributes['rule_match_value'];

                $this->storeClassificationRuleAction->handle($transaction->user, [
                    'name' => Str::limit("{$matchValue} → {$targetName}", 255, ''),
                    'transaction_type' => $transaction->type,
                    'match_field' => $attributes['rule_match_field'],
                    'match_operator' => $attributes['rule_match_operator'],
                    'match_value' => $matchValue,
                    'category_id' => $category->id,
                    'subcategory_id' => $subcategory?->id,
                    'is_calculation_target' => null,
                    'priority' => 0,
                    'is_active' => true,
                ]);
            }

            return $transaction->refresh();
        });
    }
}
