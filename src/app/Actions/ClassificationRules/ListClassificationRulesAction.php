<?php

namespace App\Actions\ClassificationRules;

use App\Models\ClassificationRule;
use App\Models\User;
use Illuminate\Support\Collection;

class ListClassificationRulesAction
{
    /**
     * @return Collection<int, ClassificationRule>
     */
    public function handle(User $user): Collection
    {
        return $user->classificationRules()
            ->with(['category', 'subcategory'])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }
}
