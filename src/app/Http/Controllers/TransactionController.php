<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\ListTransactionsAction;
use App\Actions\Transactions\ShowTransactionAction;
use App\Actions\Transactions\StoreTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Services\Transactions\TransactionOptionsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly ListTransactionsAction $listTransactionsAction,
        private readonly StoreTransactionAction $storeTransactionAction,
        private readonly UpdateTransactionAction $updateTransactionAction,
        private readonly DeleteTransactionAction $deleteTransactionAction,
        private readonly ShowTransactionAction $showTransactionAction,
        private readonly TransactionOptionsService $transactionOptionsService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $filters = [
            'date_from' => request()->string('date_from')->toString(),
            'date_to' => request()->string('date_to')->toString(),
            'account_id' => request()->string('account_id')->toString(),
            'category_id' => request()->string('category_id')->toString(),
            'category_state' => in_array(
                request()->string('category_state')->toString(),
                ['all', 'categorized', 'uncategorized'],
                true,
            )
                ? request()->string('category_state')->toString()
                : 'all',
            'currency' => request()->string('currency')->toString(),
            'type' => request()->string('type')->toString(),
            'keyword' => request()->string('keyword')->toString(),
            'is_confirmed' => request()->has('is_confirmed')
                ? request()->string('is_confirmed')->toString()
                : '',
            'calculation_target' => in_array(
                request()->string('calculation_target')->toString(),
                ['all', 'included', 'excluded'],
                true,
            )
                ? request()->string('calculation_target')->toString()
                : 'all',
        ];

        $typeLabels = $this->transactionOptionsService->typeLabels();
        $transactions = $this->listTransactionsAction
            ->handle(request()->user(), $filters)
            ->through(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
                'type' => $transaction->type,
                'type_label' => $typeLabels[$transaction->type] ?? $transaction->type,
                'account' => $transaction->account === null ? null : [
                    'id' => $transaction->account->id,
                    'name' => $transaction->account->name,
                ],
                'transfer_account' => $transaction->transferAccount === null ? null : [
                    'id' => $transaction->transferAccount->id,
                    'name' => $transaction->transferAccount->name,
                ],
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'category' => $transaction->category === null ? null : [
                    'id' => $transaction->category->id,
                    'name' => $transaction->category->name,
                ],
                'subcategory' => $transaction->subcategory === null ? null : [
                    'id' => $transaction->subcategory->id,
                    'name' => $transaction->subcategory->name,
                ],
                'merchant_name' => $transaction->merchant_name,
                'description' => $transaction->description,
                'payment_method_label' => $transaction->payment_method_label,
                'memo' => $transaction->memo,
                'is_confirmed' => $transaction->is_confirmed,
                'is_calculation_target' => $transaction->is_calculation_target,
                'affects_account_balance' => $transaction->affects_account_balance,
            ]);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'typeOptions' => $this->transactionOptionsService->typeOptions(),
            'accountOptions' => $this->transactionOptionsService->accountOptions(request()->user()),
            'categoryOptions' => $this->transactionOptionsService->categoryOptions(request()->user()),
            'currencyOptions' => $this->transactionOptionsService->currencyOptions(request()->user()),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('Transactions/Create', [
            'typeOptions' => $this->transactionOptionsService->typeOptions(),
            'accountOptions' => $this->transactionOptionsService->accountOptions(request()->user()),
            'categoryOptions' => $this->transactionOptionsService->categoryOptions(request()->user()),
            'subcategoryOptions' => $this->transactionOptionsService->subcategoryOptions(request()->user()),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $transaction = $this->storeTransactionAction->handle(
            $request->user(),
            $request->validated(),
        );

        return to_route('transactions.show', $transaction);
    }

    public function show(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction = $this->showTransactionAction->handle($transaction);
        $typeLabels = $this->transactionOptionsService->typeLabels();

        return Inertia::render('Transactions/Show', [
            'transaction' => [
                'id' => $transaction->id,
                'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
                'posted_at' => $transaction->posted_at?->format('Y-m-d H:i:s'),
                'type' => $transaction->type,
                'type_label' => $typeLabels[$transaction->type] ?? $transaction->type,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'merchant_name' => $transaction->merchant_name,
                'description' => $transaction->description,
                'payment_method_label' => $transaction->payment_method_label,
                'memo' => $transaction->memo,
                'is_confirmed' => $transaction->is_confirmed,
                'is_calculation_target' => $transaction->is_calculation_target,
                'affects_account_balance' => $transaction->affects_account_balance,
                'account' => $transaction->account === null ? null : [
                    'id' => $transaction->account->id,
                    'name' => $transaction->account->name,
                    'type' => $transaction->account->type,
                ],
                'transfer_account' => $transaction->transferAccount === null ? null : [
                    'id' => $transaction->transferAccount->id,
                    'name' => $transaction->transferAccount->name,
                    'type' => $transaction->transferAccount->type,
                ],
                'category' => $transaction->category === null ? null : [
                    'id' => $transaction->category->id,
                    'name' => $transaction->category->name,
                ],
                'subcategory' => $transaction->subcategory === null ? null : [
                    'id' => $transaction->subcategory->id,
                    'name' => $transaction->subcategory->name,
                ],
            ],
        ]);
    }

    public function edit(Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        return Inertia::render('Transactions/Edit', [
            'transaction' => [
                'id' => $transaction->id,
                'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
                'type' => $transaction->type,
                'account_id' => $transaction->account_id,
                'transfer_account_id' => $transaction->transfer_account_id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'merchant_name' => $transaction->merchant_name,
                'description' => $transaction->description,
                'category_id' => $transaction->category_id,
                'subcategory_id' => $transaction->subcategory_id,
                'payment_method_label' => $transaction->payment_method_label,
                'is_confirmed' => $transaction->is_confirmed,
                'is_calculation_target' => $transaction->is_calculation_target,
                'affects_account_balance' => $transaction->affects_account_balance,
                'memo' => $transaction->memo,
            ],
            'typeOptions' => $this->transactionOptionsService->typeOptions(),
            'accountOptions' => $this->transactionOptionsService->accountOptions(request()->user()),
            'categoryOptions' => $this->transactionOptionsService->categoryOptions(request()->user()),
            'subcategoryOptions' => $this->transactionOptionsService->subcategoryOptions(request()->user()),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $transaction = $this->updateTransactionAction->handle($transaction, $request->validated());

        return to_route('transactions.show', $transaction);
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->deleteTransactionAction->handle($transaction);

        return to_route('transactions.index');
    }
}
