<?php

namespace App\Actions\ClassificationRules;

use App\Models\ClassificationRule;

class DeleteClassificationRuleAction
{
    public function handle(ClassificationRule $classificationRule): void
    {
        $classificationRule->delete();
    }
}
