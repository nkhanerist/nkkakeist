<?php

namespace App\Http\Requests\ClassificationRules;

use App\Models\ClassificationRule;

class UpdateClassificationRuleRequest extends StoreClassificationRuleRequest
{
    public function authorize(): bool
    {
        $classificationRule = $this->route('classification_rule');

        return $classificationRule instanceof ClassificationRule
            && ($this->user()?->can('update', $classificationRule) ?? false);
    }
}
