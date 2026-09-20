<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public storefront catalogue. Unavailable products are invisible here;
 * admins see everything through Admin\ProductController.
 */
class ProductController extends Controller
{
    /**
     * GET /products?category={slug}&search={text}&per_page={n}
     */
    public function index(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->available()
            ->with('category')
            ->when($request->validated('category'), function ($query, string $slug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
            })
            ->when($request->validated('search'), function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            // ?on_offer=1 powers the storefront's "Deals" view.
            ->when($request->onlyOffers(), fn ($query) => $query->onOffer())
            ->orderBy('name')
            ->paginate($request->perPage())
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * GET /products/{slug}
     */
    public function show(Product $product): ProductResource
    {
        abort_unless($product->is_available, 404, 'Product not found.');

        return new ProductResource($product->load('category'));
    }
}
