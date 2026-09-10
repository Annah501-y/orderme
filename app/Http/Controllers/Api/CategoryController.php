<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return ApiResponse::success(
            'Category created successfully.',
            new CategoryResource($category),
            201
        );
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): JsonResponse {
        $category->update($request->validated());

        return ApiResponse::success(
            'Category updated successfully.',
            new CategoryResource($category->fresh())
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return ApiResponse::success(
            'Category deleted successfully.'
        );
    }
}