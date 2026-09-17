<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public, read-only category list used for the storefront filter bar.
 */
class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            // Count only what a customer can actually order.
            ->withCount(['products' => fn ($query) => $query->available()])
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }
}
