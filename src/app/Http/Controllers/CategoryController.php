<?php

namespace App\Http\Controllers;

use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\ListCategoriesAction;
use App\Actions\Categories\StoreCategoryAction;
use App\Actions\Categories\UpdateCategoryAction;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Subcategory;
use App\Services\Categories\CategoryOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly ListCategoriesAction $listCategoriesAction,
        private readonly StoreCategoryAction $storeCategoryAction,
        private readonly UpdateCategoryAction $updateCategoryAction,
        private readonly DeleteCategoryAction $deleteCategoryAction,
        private readonly CategoryOptionsService $categoryOptionsService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Category::class);

        $typeLabels = $this->categoryOptionsService->typeLabels();
        $categories = $this->listCategoriesAction
            ->handle(request()->user())
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'type_label' => $typeLabels[$category->type] ?? $category->type,
                'color' => $category->color,
                'icon' => $category->icon,
                'display_order' => $category->display_order,
                'is_active' => $category->is_active,
                'subcategories' => $category->subcategories->map(
                    fn (Subcategory $subcategory): array => [
                        'id' => $subcategory->id,
                        'name' => $subcategory->name,
                        'display_order' => $subcategory->display_order,
                        'is_active' => $subcategory->is_active,
                    ],
                )->values(),
            ])
            ->values();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Category::class);

        $initialType = $request->string('type')->toString();
        $returnToCategoryReview = $request->string('return_to')->toString() === 'category-review';
        $reviewStatus = $request->string('review_status')->toString();
        $reviewType = $request->string('review_type')->toString();

        return Inertia::render('Categories/Create', [
            'typeOptions' => $this->categoryOptionsService->typeOptions(),
            'initialType' => in_array($initialType, Category::types(), true) ? $initialType : 'expense',
            'returnContext' => $returnToCategoryReview ? [
                'return_to' => 'category-review',
                'review_status' => in_array($reviewStatus, ['high', 'manual', 'all'], true) ? $reviewStatus : 'high',
                'review_type' => in_array($reviewType, ['expense', 'income', 'all'], true) ? $reviewType : 'all',
            ] : null,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = $this->storeCategoryAction->handle(
            $request->user(),
            $request->safe()->except(['return_to', 'review_status', 'review_type']),
        );

        if ($request->validated('return_to') === 'category-review') {
            return to_route('transactions.category-review.index', [
                'status' => $request->validated('review_status', 'high'),
                'type' => $request->validated('review_type', 'all'),
            ])->with('success', "カテゴリ「{$category->name}」を追加しました。対象の取引で選択してください。");
        }

        return to_route('categories.index');
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('Categories/Edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'icon' => $category->icon,
                'display_order' => $category->display_order,
                'is_active' => $category->is_active,
                'subcategories' => $category->subcategories()
                    ->orderBy('display_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Subcategory $subcategory): array => [
                        'id' => $subcategory->id,
                        'name' => $subcategory->name,
                        'display_order' => $subcategory->display_order,
                        'is_active' => $subcategory->is_active,
                    ])
                    ->values(),
            ],
            'typeOptions' => $this->categoryOptionsService->typeOptions(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->updateCategoryAction->handle($category, $request->validated());

        return to_route('categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->deleteCategoryAction->handle($category);

        return to_route('categories.index');
    }
}
