<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * Headline numbers for the admin dashboard landing page.
 */
class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // One grouped query instead of one COUNT per status.
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        // Every status in lifecycle order, with its label, so the UI never
        // keeps its own copy of the enum.
        $ordersByStatus = collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
            'is_final' => $status->isFinal(),
        ]);

        return response()->json([
            'data' => [
                'products_count' => Product::count(),
                'available_products_count' => Product::query()->available()->count(),
                'categories_count' => Category::count(),
                'orders_count' => $ordersByStatus->sum('count'),
                'open_orders_count' => $ordersByStatus->where('is_final', false)->sum('count'),
                'orders_by_status' => $ordersByStatus->values(),
                // Only delivered orders count as revenue; cancelled/in-flight don't.
                'revenue' => (float) Order::query()->where('status', OrderStatus::Delivered)->sum('total'),
                'recent_orders' => OrderResource::collection(
                    Order::query()->with('user')->withCount('items')->latest('id')->limit(5)->get()
                ),
            ],
        ]);
    }
}
