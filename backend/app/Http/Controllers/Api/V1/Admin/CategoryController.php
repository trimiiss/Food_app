<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Admins see the total product count, including unavailable products.
        return CategoryResource::collection(
            Category::query()->withCount('products')->orderBy('name')->get()
        );
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('products'));
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category->loadCount('products'));
    }

    public function destroy(Category $category): JsonResponse|Response
    {
        // The FK is RESTRICT as a last line of defence, but a raw SQL error is
        // no use to an admin. Explain what to do instead.
        $count = $category->products()->count();

        if ($count > 0) {
            return response()->json([
                'message' => "Cannot delete \"{$category->name}\" because it still has {$count} "
                    .str('product')->plural($count).'. Move or delete them first.',
            ], 409);
        }

        $category->delete();

        return response()->noContent();
    }
}
