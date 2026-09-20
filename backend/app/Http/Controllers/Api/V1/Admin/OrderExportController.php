<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The orders table as a CSV, for a spreadsheet or an accountant.
 *
 * It takes the same filters as the admin orders list, so the file always
 * contains exactly what the admin was looking at. Rows are streamed in chunks
 * rather than collected in memory: a year of orders should not need a bigger
 * PHP memory limit to download.
 */
class OrderExportController extends Controller
{
    private const COLUMNS = [
        'Order', 'Placed at', 'Status', 'Fulfilment', 'Customer', 'Email', 'Phone',
        'Address', 'Items', 'Subtotal', 'Delivery fee', 'Promo code', 'Discount', 'Total',
    ];

    /**
     * GET /admin/orders/export?status=&fulfillment_type=&search=
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'fulfillment_type' => ['nullable', Rule::enum(FulfillmentType::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $filename = 'leueats-orders-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // Without a BOM, Excel reads UTF-8 accents as mojibake.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::COLUMNS);

            Order::query()
                ->filtered($filters)
                ->with('user')
                ->withCount('items')
                ->orderByDesc('id')
                ->chunk(500, function ($orders) use ($handle) {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->order_number,
                            $order->created_at?->toDateTimeString(),
                            $order->status->label(),
                            $order->fulfillment()->label(),
                            $order->user?->name,
                            $order->user?->email,
                            $order->contact_phone,
                            // Empty for a pickup: there is no address to give.
                            $order->delivery_address ?? '',
                            $order->items_count,
                            $order->subtotal,
                            $order->delivery_fee,
                            $order->promo_code ?? '',
                            $order->discount_total,
                            $order->total,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
