<?php

namespace App\Http\Controllers;

use App\Actions\Subcategories\DeleteSubcategoryAction;
use App\Actions\Subcategories\StoreSubcategoryAction;
use App\Actions\Subcategories\UpdateSubcategoryAction;
use App\Http\Requests\Subcategories\StoreSubcategoryRequest;
use App\Http\Requests\Subcategories\UpdateSubcategoryRequest;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;

class SubcategoryController extends Controller
{
    public function __construct(
        private readonly StoreSubcategoryAction $storeSubcategoryAction,
        private readonly UpdateSubcategoryAction $updateSubcategoryAction,
        private readonly DeleteSubcategoryAction $deleteSubcategoryAction,
    ) {}

    public function store(StoreSubcategoryRequest $request): RedirectResponse
    {
        $subcategory = $this->storeSubcategoryAction->handle(
            $request->user(),
            $request->validated(),
        );

        return to_route('categories.edit', $subcategory->category_id);
    }

    public function update(UpdateSubcategoryRequest $request, Subcategory $subcategory): RedirectResponse
    {
        $subcategory = $this->updateSubcategoryAction->handle(
            $subcategory,
            $request->validated(),
        );

        return to_route('categories.edit', $subcategory->category_id);
    }

    public function destroy(Subcategory $subcategory): RedirectResponse
    {
        $this->authorize('delete', $subcategory);

        $categoryId = $subcategory->category_id;
        $this->deleteSubcategoryAction->handle($subcategory);

        return to_route('categories.edit', $categoryId);
    }
}
