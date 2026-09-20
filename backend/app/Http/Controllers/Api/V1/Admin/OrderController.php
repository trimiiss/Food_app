<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FulfillmentType;
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
     * GET /admin/orders?status={status}&fulfillment_type={delivery|pickup}&search={order number, name or email}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'fulfillment_type' => ['nullable', Rule::enum(FulfillmentType::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            // Status, delivery/pickup and search live on the model, so the CSV
            // export applies exactly the same filters.
            ->filtered($filters)
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return OrderResource::collection($orders)->additional([
            // Lets the UI build its filters from the enums instead of a hardcoded copy.
            'statuses' => array_map(
                fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                OrderStatus::cases(),
            ),
            'fulfillment_types' => FulfillmentType::options(),
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
