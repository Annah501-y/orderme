<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Public category list.
     * Only active categories are visible to buyers and sellers.
     */
    public function index()
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Admin category list.
     * Admin can see both active and inactive categories.
     */
    public function adminIndex()
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Create a new category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
     $data = $request->validated();
     if ($request->hasFile('image')){
        $data['image'] =
        $request->file('image')
        ->store('categories','public');
     }
     $category =Category::create($data);
     return ApiResponse::success(
        'category created successfully.',
        new CategoryResource($category),
        201
     );
    }

    /**
     * Show a single category.
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    /**
     * Update a category.
     */
    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): JsonResponse {
        $data = $request->validated();
    
        if ($request->hasFile('image')) {
    
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
    
            $data['image'] = $request->file('image')
                ->store('categories', 'public');
        }
    
        $category->update($data);
    
        return ApiResponse::success(
            'Category updated successfully.',
            new CategoryResource($category->fresh())
        );
    }

    /**
     * Delete a category.
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return ApiResponse::success(
            'Category deleted successfully.'
        );
    }
}