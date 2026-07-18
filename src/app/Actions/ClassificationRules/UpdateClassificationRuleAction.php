<?php

namespace App\Actions\ClassificationRules;

use App\Models\ClassificationRule;

class UpdateClassificationRuleAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(ClassificationRule $classificationRule, array $attributes): ClassificationRule
    {
        $classificationRule->update($attributes);

        return $classificationRule->refresh();
    }
}
