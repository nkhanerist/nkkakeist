<?php

namespace App\Http\Controllers;

use App\Actions\Imports\BuildImportPreviewAction;
use App\Actions\Imports\CommitImportAction;
use App\Actions\Imports\DeleteImportAction;
use App\Actions\Imports\ListImportsAction;
use App\Actions\Imports\ParseImportAction;
use App\Actions\Imports\StoreImportAction;
use App\Actions\Imports\UpdateImportRowAccountAction;
use App\Actions\Imports\UpdateImportRowReplacementAction;
use App\Actions\Imports\UpdateImportRowTransferAccountAction;
use App\Http\Requests\Imports\CommitImportRequest;
use App\Http\Requests\Imports\StoreImportRequest;
use App\Http\Requests\Imports\UpdateImportRowAccountRequest;
use App\Http\Requests\Imports\UpdateImportRowReplacementRequest;
use App\Http\Requests\Imports\UpdateImportRowTransferAccountRequest;
use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Imports\BalanceSnapshotConflictService;
use App\Services\Imports\ImportMessageLocalizer;
use App\Services\Imports\ImportOptionsService;
use App\Services\Imports\JrePointReconciliationService;
use App\Services\Imports\ResolveTransferImportRowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function __construct(
        private readonly ListImportsAction $listImportsAction,
        private readonly StoreImportAction $storeImportAction,
        private readonly ParseImportAction $parseImportAction,
        private readonly BuildImportPreviewAction $buildImportPreviewAction,
        private readonly CommitImportAction $commitImportAction,
        private readonly UpdateImportRowTransferAccountAction $updateImportRowTransferAccountAction,
        private readonly UpdateImportRowAccountAction $updateImportRowAccountAction,
        private readonly UpdateImportRowReplacementAction $updateImportRowReplacementAction,
        private readonly DeleteImportAction $deleteImportAction,
        private readonly ImportOptionsService $importOptionsService,
        private readonly ImportMessageLocalizer $importMessageLocalizer,
        private readonly ResolveTransferImportRowService $resolveTransferImportRowService,
        private readonly JrePointReconciliationService $jrePointReconciliationService,
        private readonly BalanceSnapshotConflictService $balanceSnapshotConflictService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Import::class);

        $imports = $this->listImportsAction
            ->handle(request()->user())
            ->through(fn (Import $import): array => $this->importOptionsService->importListItem($import));

        return Inertia::render('Imports/Index', [
            'imports' => $imports,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Import::class);

        $sourceOptions = $this->importOptionsService->sourceOptions();
        $requestedSource = request()->string('source')->toString();
        $selectedSource = collect($sourceOptions)->pluck('value')->contains($requestedSource)
            ? $requestedSource
            : 'money_forward';

        return Inertia::render('Imports/Create', [
            'sourceOptions' => $sourceOptions,
            'accountOptions' => $this->importOptionsService->accountOptions(request()->user()),
            'selectedSource' => $selectedSource,
            'suggestedAccountIds' => $this->importOptionsService->suggestedAccountIds(request()->user()),
        ]);
    }

    public function store(StoreImportRequest $request): RedirectResponse
    {
        $import = $this->storeImportAction->handle($request->user(), $request->validated());
        $import = $this->parseImportAction->handle($import);
        $this->buildImportPreviewAction->handle($import);

        return to_route('imports.preview', $import);
    }

    public function show(Import $import): Response
    {
        $this->authorize('view', $import);
        $import->load([
            'user',
            'account',
            'importRows.resolvedAccount',
            'importRows.manualResolvedAccount',
            'importRows.resolvedTransferAccount',
            'importRows.manualResolvedTransferAccount',
            'importRows.resolvedCategory',
            'importRows.resolvedSubcategory',
            'importRows.matchedClassificationRule',
        ]);
        $accounts = $import->user->accounts()->get();

        return Inertia::render('Imports/Show', [
            'import' => $this->importOptionsService->importListItem($import),
            'accountOptions' => $this->importOptionsService->accountOptions($import->user),
            'jrePointReconciliation' => $this->jrePointReconciliationService->handle($import),
            'rows' => $import->importRows->map(fn ($importRow): array => [
                'id' => $importRow->id,
                'row_number' => $importRow->row_number,
                'transaction_date' => $importRow->transaction_date?->format('Y-m-d'),
                'amount' => $importRow->amount,
                'account_name' => $importRow->account_name,
                'category_name' => $importRow->detected_type === 'transfer' ? null : $importRow->category_name,
                'subcategory_name' => $importRow->detected_type === 'transfer' ? null : $importRow->subcategory_name,
                'merchant_name' => $importRow->merchant_name,
                'description' => $importRow->description,
                'detected_type' => $importRow->detected_type,
                'is_calculation_target' => $importRow->resolved_is_calculation_target,
                'affects_account_balance' => $importRow->resolved_affects_account_balance,
                'resolved_account' => $importRow->resolvedAccount === null ? null : [
                    'id' => $importRow->resolvedAccount->id,
                    'name' => $importRow->resolvedAccount->name,
                    'currency' => $importRow->resolvedAccount->currency,
                ],
                'manual_resolved_account_id' => $importRow->manual_resolved_account_id,
                'remember_mapping_recommended' => $import->source_name === 'balance_snapshot'
                    && $importRow->account_name === 'Money Forward 年金'
                    && $importRow->resolved_account_id === null,
                'replace_account_snapshot_id' => $importRow->replace_account_snapshot_id,
                'same_day_snapshot' => $this->sameDaySnapshotItem($import, $importRow),
                'resolved_transfer_account' => $importRow->resolvedTransferAccount === null ? null : [
                    'id' => $importRow->resolvedTransferAccount->id,
                    'name' => $importRow->resolvedTransferAccount->name,
                    'currency' => $importRow->resolvedTransferAccount->currency,
                ],
                'manual_resolved_transfer_account_id' => $importRow->manual_resolved_transfer_account_id,
                'resolved_category' => $importRow->resolvedCategory === null ? null : [
                    'id' => $importRow->resolvedCategory->id,
                    'name' => $importRow->resolvedCategory->name,
                ],
                'resolved_subcategory' => $importRow->resolvedSubcategory === null ? null : [
                    'id' => $importRow->resolvedSubcategory->id,
                    'name' => $importRow->resolvedSubcategory->name,
                ],
                'matched_classification_rule' => $importRow->matchedClassificationRule === null ? null : [
                    'id' => $importRow->matchedClassificationRule->id,
                    'name' => $importRow->matchedClassificationRule->name,
                    'priority' => $importRow->matchedClassificationRule->priority,
                ],
                'rule_applied_fields' => $importRow->rule_applied_fields ?? [],
                'category_resolution_source' => $importRow->resolved_category_id === null
                    ? null
                    : (in_array('category', $importRow->rule_applied_fields ?? [], true) ? 'rule' : 'csv'),
                'subcategory_resolution_source' => $importRow->resolved_subcategory_id === null
                    ? null
                    : (in_array('subcategory', $importRow->rule_applied_fields ?? [], true) ? 'rule' : 'csv'),
                'calculation_target_source' => $importRow->detected_type === 'transfer'
                    ? null
                    : ($importRow->is_calculation_target === null
                        ? ($importRow->resolved_is_calculation_target === null ? null : 'rule')
                        : (in_array('is_calculation_target', $importRow->rule_applied_fields ?? [], true) ? 'rule' : 'csv')),
                'status' => $importRow->status,
                'is_duplicate_candidate' => $importRow->is_duplicate_candidate,
                'duplicate_hash' => $importRow->duplicate_hash,
                'validation_errors' => $this->importMessageLocalizer->messages(
                    $importRow->validation_errors ?? [],
                ),
                'raw_payload' => $importRow->raw_payload,
                'transfer_resolution' => $this->importMessageLocalizer->transferResolution(
                    $this->resolveTransferImportRowService->explain($importRow, $accounts),
                ),
            ])->all(),
        ]);
    }

    public function parse(Import $import): RedirectResponse
    {
        $this->authorize('view', $import);

        try {
            $import = $this->parseImportAction->handle($import);
            $this->buildImportPreviewAction->handle($import);
        } catch (ValidationException $exception) {
            return to_route('imports.show', $import)->with(
                'error',
                $exception->errors()['import'][0] ?? trans('imports.messages.reparse_failed'),
            );
        }

        return to_route('imports.preview', $import);
    }

    public function commit(CommitImportRequest $request, Import $import): RedirectResponse
    {
        try {
            $import = $this->commitImportAction->handle($import);
        } catch (ValidationException $exception) {
            return to_route('imports.show', $import)->with(
                'error',
                $exception->errors()['import'][0] ?? trans('imports.messages.commit_failed'),
            );
        }

        return to_route('imports.show', $import);
    }

    public function updateTransferAccount(
        UpdateImportRowTransferAccountRequest $request,
        Import $import,
        ImportRow $importRow,
    ): RedirectResponse {
        $this->updateImportRowTransferAccountAction->handle(
            $import,
            $importRow,
            $request->filled('resolved_transfer_account_id')
                ? (int) $request->input('resolved_transfer_account_id')
                : null,
        );

        return to_route('imports.preview', $import);
    }

    public function updateAccount(
        UpdateImportRowAccountRequest $request,
        Import $import,
        ImportRow $importRow,
    ): RedirectResponse {
        $this->updateImportRowAccountAction->handle(
            $import,
            $importRow,
            $request->filled('resolved_account_id')
                ? (int) $request->input('resolved_account_id')
                : null,
            $request->boolean('remember_mapping'),
        );

        return to_route('imports.preview', $import);
    }

    public function updateReplacement(
        UpdateImportRowReplacementRequest $request,
        Import $import,
        ImportRow $importRow,
    ): RedirectResponse {
        $this->updateImportRowReplacementAction->handle(
            $import,
            $importRow,
            $request->boolean('replace_existing'),
        );

        return to_route('imports.preview', $import);
    }

    public function destroy(Import $import): RedirectResponse
    {
        $this->authorize('delete', $import);

        try {
            $this->deleteImportAction->handle($import);
        } catch (ValidationException $exception) {
            return to_route('imports.show', $import)->with(
                'error',
                $exception->errors()['import'][0] ?? trans('imports.messages.delete_failed'),
            );
        }

        return to_route('imports.index');
    }

    /**
     * @return array<string, int|string|null>|null
     */
    private function sameDaySnapshotItem(Import $import, ImportRow $importRow): ?array
    {
        if ($import->source_name !== 'balance_snapshot') {
            return null;
        }

        $snapshot = $this->balanceSnapshotConflictService->find($import, $importRow);

        if ($snapshot === null) {
            return null;
        }

        return [
            'id' => $snapshot->id,
            'balance_date' => $snapshot->captured_at->toDateString(),
            'balance' => (string) $snapshot->balance,
            'source_name' => $snapshot->source_name,
            'import_id' => $snapshot->import_id,
        ];
    }
}
