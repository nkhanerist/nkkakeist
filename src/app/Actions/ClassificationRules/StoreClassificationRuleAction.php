<?php

namespace App\Actions\ClassificationRules;

use App\Models\ClassificationRule;
use App\Models\User;

class StoreClassificationRuleAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): ClassificationRule
    {
        return $user->classificationRules()->create($attributes);
    }
}
