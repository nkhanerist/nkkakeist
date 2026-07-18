<?php

namespace App\Http\Controllers;

use App\Actions\ClassificationRules\DeleteClassificationRuleAction;
use App\Actions\ClassificationRules\ListClassificationRulesAction;
use App\Actions\ClassificationRules\StoreClassificationRuleAction;
use App\Actions\ClassificationRules\UpdateClassificationRuleAction;
use App\Http\Requests\ClassificationRules\StoreClassificationRuleRequest;
use App\Http\Requests\ClassificationRules\UpdateClassificationRuleRequest;
use App\Models\ClassificationRule;
use App\Services\ClassificationRules\ClassificationRuleOptionsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClassificationRuleController extends Controller
{
    public function __construct(
        private readonly ListClassificationRulesAction $listClassificationRulesAction,
        private readonly StoreClassificationRuleAction $storeClassificationRuleAction,
        private readonly UpdateClassificationRuleAction $updateClassificationRuleAction,
        private readonly DeleteClassificationRuleAction $deleteClassificationRuleAction,
        private readonly ClassificationRuleOptionsService $classificationRuleOptionsService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ClassificationRule::class);

        $classificationRules = $this->listClassificationRulesAction
            ->handle(request()->user())
            ->map(fn (ClassificationRule $classificationRule): array => $this->classificationRuleOptionsService->classificationRuleItem($classificationRule))
            ->values();

        return Inertia::render('ClassificationRules/Index', [
            'classificationRules' => $classificationRules,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ClassificationRule::class);

        return Inertia::render('ClassificationRules/Create', [
            'transactionTypeOptions' => $this->classificationRuleOptionsService->transactionTypeOptions(),
            'matchFieldOptions' => $this->classificationRuleOptionsService->matchFieldOptions(),
            'matchOperatorOptions' => $this->classificationRuleOptionsService->matchOperatorOptions(),
            'categoryOptions' => $this->classificationRuleOptionsService->categoryOptions(request()->user()),
            'subcategoryOptions' => $this->classificationRuleOptionsService->subcategoryOptions(request()->user()),
        ]);
    }

    public function store(StoreClassificationRuleRequest $request): RedirectResponse
    {
        $this->storeClassificationRuleAction->handle($request->user(), $request->validated());

        return to_route('classification-rules.index');
    }

    public function edit(ClassificationRule $classificationRule): Response
    {
        $this->authorize('update', $classificationRule);

        return Inertia::render('ClassificationRules/Edit', [
            'classificationRule' => $this->classificationRuleOptionsService->classificationRuleItem(
                $classificationRule->load(['category', 'subcategory']),
            ),
            'transactionTypeOptions' => $this->classificationRuleOptionsService->transactionTypeOptions(),
            'matchFieldOptions' => $this->classificationRuleOptionsService->matchFieldOptions(),
            'matchOperatorOptions' => $this->classificationRuleOptionsService->matchOperatorOptions(),
            'categoryOptions' => $this->classificationRuleOptionsService->categoryOptions(request()->user()),
            'subcategoryOptions' => $this->classificationRuleOptionsService->subcategoryOptions(request()->user()),
        ]);
    }

    public function update(
        UpdateClassificationRuleRequest $request,
        ClassificationRule $classificationRule,
    ): RedirectResponse {
        $this->updateClassificationRuleAction->handle($classificationRule, $request->validated());

        return to_route('classification-rules.index');
    }

    public function destroy(ClassificationRule $classificationRule): RedirectResponse
    {
        $this->authorize('delete', $classificationRule);

        $this->deleteClassificationRuleAction->handle($classificationRule);

        return to_route('classification-rules.index');
    }
}
