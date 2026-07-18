<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\Import;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Policies\AccountPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ClassificationRulePolicy;
use App\Policies\ImportPolicy;
use App\Policies\SubcategoryPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(ClassificationRule::class, ClassificationRulePolicy::class);
        Gate::policy(Import::class, ImportPolicy::class);
        Gate::policy(Subcategory::class, SubcategoryPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
