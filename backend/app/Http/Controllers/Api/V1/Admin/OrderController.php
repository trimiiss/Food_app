<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Every customer's orders, with status management.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * GET /admin/orders?status={status}&search={order number, name or email}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return OrderResource::collection($orders)->additional([
            // Lets the UI build its status filter from the enum instead of a hardcoded copy.
            'statuses' => array_map(
                fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                OrderStatus::cases(),
            ),
        ]);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['items', 'user']));
    }

    /**
     * PATCH /admin/orders/{order}/status  { "status": "preparing" }
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $order = $this->orders->transition($order, $request->status());

        return new OrderResource($order->load(['items', 'user']));
    }
}
