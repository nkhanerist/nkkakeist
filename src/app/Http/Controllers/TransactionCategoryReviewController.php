<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\AssignTransactionCategoryAction;
use App\Actions\Transactions\BuildTransactionCategoryReviewAction;
use App\Http\Requests\Transactions\AssignTransactionCategoryRequest;
use App\Models\Transaction;
use App\Services\Transactions\TransactionOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCategoryReviewController extends Controller
{
    public function __construct(
        private readonly BuildTransactionCategoryReviewAction $buildTransactionCategoryReviewAction,
        private readonly AssignTransactionCategoryAction $assignTransactionCategoryAction,
        private readonly TransactionOptionsService $transactionOptionsService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        $filters = [
            'status' => in_array($status, ['high', 'manual', 'all'], true) ? $status : 'high',
            'type' => in_array($type, ['expense', 'income'], true) ? $type : 'all',
        ];

        return Inertia::render('Transactions/CategoryReview', [
            'review' => $this->buildTransactionCategoryReviewAction->handle($request->user(), $filters),
            'filters' => $filters,
            'categoryOptions' => collect($this->transactionOptionsService->categoryOptions($request->user()))
                ->reject(fn (array $category): bool => $category['name'] === '未分類')
                ->values()
                ->all(),
            'subcategoryOptions' => collect($this->transactionOptionsService->subcategoryOptions($request->user()))
                ->reject(fn (array $subcategory): bool => $subcategory['name'] === '未分類')
                ->values()
                ->all(),
        ]);
    }

    public function update(
        AssignTransactionCategoryRequest $request,
        Transaction $transaction,
    ): RedirectResponse {
        $this->authorize('update', $transaction);

        $this->assignTransactionCategoryAction->handle($transaction, $request->validated());

        $message = $request->boolean('create_rule')
            ? trans('transactions.category_review.assigned_with_rule')
            : trans('transactions.category_review.assigned');

        return back()->with('success', $message);
    }
}
