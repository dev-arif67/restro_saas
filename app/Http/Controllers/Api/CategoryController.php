<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::withCount('menuItems')->ordered();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return $this->success($query->get());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());
        return $this->created($category, 'Category created');
    }

    public function show(int $id): JsonResponse
    {
        $category = Category::with(['menuItems' => fn($q) => $q->active()->ordered()])
            ->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        return $this->success($category);
    }

    public function update(StoreCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        $category->update($request->validated());

        return $this->success($category->fresh(), 'Category updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        // Move items to uncategorized
        $category->menuItems()->update(['category_id' => null]);
        $category->delete();

        return $this->success(null, 'Category deleted');
    }
}
