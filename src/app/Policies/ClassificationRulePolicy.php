<?php

namespace App\Policies;

use App\Models\ClassificationRule;
use App\Models\User;

class ClassificationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, ClassificationRule $classificationRule): bool
    {
        return $user->is($classificationRule->user);
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, ClassificationRule $classificationRule): bool
    {
        return $user->is($classificationRule->user);
    }

    public function delete(User $user, ClassificationRule $classificationRule): bool
    {
        return $user->is($classificationRule->user);
    }
}
