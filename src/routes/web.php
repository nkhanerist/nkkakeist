<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountReconciliationController;
use App\Http\Controllers\AccountSnapshotController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClassificationRuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\TransactionCategoryReviewController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('accounts')->name('accounts.')->group(function (): void {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::get('/reconciliation', [AccountReconciliationController::class, 'index'])
            ->name('reconciliation.index');
        Route::get('/create', [AccountController::class, 'create'])->name('create');
        Route::post('/', [AccountController::class, 'store'])->name('store');
        Route::post('/{account}/reconciliation', [AccountReconciliationController::class, 'store'])
            ->name('reconciliation.store');
        Route::get('/{account}/snapshots', [AccountSnapshotController::class, 'index'])->name('snapshots.index');
        Route::post('/{account}/snapshots', [AccountSnapshotController::class, 'store'])->name('snapshots.store');
        Route::put('/{account}/snapshots/{account_snapshot}', [AccountSnapshotController::class, 'update'])->name('snapshots.update');
        Route::delete('/{account}/snapshots/{account_snapshot}', [AccountSnapshotController::class, 'destroy'])->name('snapshots.destroy');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])->name('edit');
        Route::put('/{account}', [AccountController::class, 'update'])->name('update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('categories')->name('categories.')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('/create', [CategoryController::class, 'create'])->name('create');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('subcategories')->name('subcategories.')->group(function (): void {
        Route::post('/', [SubcategoryController::class, 'store'])->name('store');
        Route::put('/{subcategory}', [SubcategoryController::class, 'update'])->name('update');
        Route::delete('/{subcategory}', [SubcategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('transactions')->name('transactions.')->group(function (): void {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/create', [TransactionController::class, 'create'])->name('create');
        Route::post('/', [TransactionController::class, 'store'])->name('store');
        Route::get('/category-review', [TransactionCategoryReviewController::class, 'index'])
            ->name('category-review.index');
        Route::patch('/{transaction}/category', [TransactionCategoryReviewController::class, 'update'])
            ->name('category-review.update');
        Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
        Route::get('/{transaction}/edit', [TransactionController::class, 'edit'])->name('edit');
        Route::put('/{transaction}', [TransactionController::class, 'update'])->name('update');
        Route::delete('/{transaction}', [TransactionController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('imports')->name('imports.')->group(function (): void {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::get('/create', [ImportController::class, 'create'])->name('create');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::get('/{import}', [ImportController::class, 'show'])->name('show');
        Route::post('/{import}/parse', [ImportController::class, 'parse'])->name('parse');
        Route::get('/{import}/preview', [ImportController::class, 'show'])->name('preview');
        Route::put('/{import}/rows/{import_row}/transfer-account', [ImportController::class, 'updateTransferAccount'])
            ->name('rows.update-transfer-account');
        Route::put('/{import}/rows/{import_row}/account', [ImportController::class, 'updateAccount'])
            ->name('rows.update-account');
        Route::post('/{import}/commit', [ImportController::class, 'commit'])->name('commit');
        Route::delete('/{import}', [ImportController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('classification-rules')->name('classification-rules.')->group(function (): void {
        Route::get('/', [ClassificationRuleController::class, 'index'])->name('index');
        Route::get('/create', [ClassificationRuleController::class, 'create'])->name('create');
        Route::post('/', [ClassificationRuleController::class, 'store'])->name('store');
        Route::get('/{classification_rule}/edit', [ClassificationRuleController::class, 'edit'])->name('edit');
        Route::put('/{classification_rule}', [ClassificationRuleController::class, 'update'])->name('update');
        Route::delete('/{classification_rule}', [ClassificationRuleController::class, 'destroy'])->name('destroy');
    });

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
