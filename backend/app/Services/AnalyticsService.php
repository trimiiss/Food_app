<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Sql;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The numbers behind the admin analytics page.
 *
 * One rule throughout, so nothing here contradicts anything else on the page:
 * **cancelled orders are excluded, every other order counts** — an order that
 * is still being prepared is money the kitchen has taken. (The dashboard's
 * "Revenue" tile is deliberately stricter: it only counts delivered orders.)
 *
 * Aggregation happens in SQL rather than in PHP so the page stays fast as the
 * order table grows; the dialect differences live in App\Support\Sql.
 */
class AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function forLastDays(int $days): array
    {
        // Inclusive window: "30 days" means today plus the 29 before it.
        $from = CarbonImmutable::today()->subDays($days - 1);

        return [
            'range' => [
                'days' => $days,
                'from' => $from->toDateString(),
                'to' => CarbonImmutable::today()->toDateString(),
            ],
            'totals' => $this->totals($from),
            'sales_by_day' => $this->salesByDay($from, $days),
            'top_products' => $this->topProducts($from),
            'promo_codes' => $this->promoCodes($from),
            'fulfillment_split' => $this->fulfillmentSplit($from),
        ];
    }

    /**
     * Orders placed in the window that still count, i.e. everything but the
     * cancellations.
     *
     * @return Builder<Order>
     */
    private function countableOrders(CarbonImmutable $from): Builder
    {
        return Order::query()
            ->where('created_at', '>=', $from->startOfDay())
            ->where('status', '!=', OrderStatus::Cancelled->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function totals(CarbonImmutable $from): array
    {
        $orders = $this->countableOrders($from)->count();
        $sales = (float) $this->countableOrders($from)->sum('total');

        $cancelled = Order::query()
            ->where('created_at', '>=', $from->startOfDay())
            ->where('status', OrderStatus::Cancelled->value)
            ->count();

        return [
            'orders' => $orders,
            'sales' => round($sales, 2),
            // Guarded: a shop with no orders yet must not divide by zero.
            'average_order_value' => $orders > 0 ? round($sales / $orders, 2) : 0.0,
            'discounts' => round((float) $this->countableOrders($from)->sum('discount_total'), 2),
            'cancelled_orders' => $cancelled,
            'items_sold' => (int) OrderItem::query()
                ->whereIn('order_id', $this->countableOrders($from)->select('id'))
                ->sum('quantity'),
        ];
    }

    /**
     * One row per day, including the days nothing was sold — a chart with
     * holes in its axis lies about the shape of the week.
     *
     * @return list<array{date: string, orders: int, sales: float}>
     */
    private function salesByDay(CarbonImmutable $from, int $days): array
    {
        $rows = $this->countableOrders($from)
            ->selectRaw(Sql::dateExpression('created_at').' as day')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(total) as sales')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($from, $rows) {
                $date = $from->addDays($offset)->toDateString();
                $row = $rows->get($date);

                return [
                    'date' => $date,
                    'orders' => (int) ($row->orders ?? 0),
                    'sales' => round((float) ($row->sales ?? 0), 2),
                ];
            })
            ->all();
    }

    /**
     * Best sellers by quantity. Grouped by the *snapshot* name stored on the
     * line, so a dish deleted from the menu still shows what it sold.
     *
     * @return list<array{name: string, quantity: int, sales: float}>
     */
    private function topProducts(CarbonImmutable $from, int $limit = 8): array
    {
        return OrderItem::query()
            ->whereIn('order_id', $this->countableOrders($from)->select('id'))
            ->groupBy('product_name')
            ->selectRaw('product_name as name')
            ->selectRaw('SUM(quantity) as quantity')
            ->selectRaw('SUM(line_total) as sales')
            ->orderByDesc('quantity')
            ->orderBy('product_name')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'sales' => round((float) $row->sales, 2),
            ])
            ->all();
    }

    /**
     * How each code actually performed: how often it was used, what it gave
     * away and what was still spent alongside it.
     *
     * @return list<array{code: string, orders: int, discount_total: float, sales: float}>
     */
    private function promoCodes(CarbonImmutable $from): array
    {
        return $this->countableOrders($from)
            ->whereNotNull('promo_code')
            ->groupBy('promo_code')
            ->selectRaw('promo_code as code')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(discount_total) as discount_total')
            ->selectRaw('SUM(total) as sales')
            ->orderByDesc('orders')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'orders' => (int) $row->orders,
                'discount_total' => round((float) $row->discount_total, 2),
                'sales' => round((float) $row->sales, 2),
            ])
            ->all();
    }

    /**
     * Delivery vs pickup. Every type is listed, including the one nobody chose.
     *
     * @return list<array{value: string, label: string, orders: int, sales: float}>
     */
    private function fulfillmentSplit(CarbonImmutable $from): array
    {
        $rows = $this->countableOrders($from)
            ->groupBy('fulfillment_type')
            ->selectRaw('fulfillment_type')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(total) as sales')
            ->get()
            ->keyBy(fn ($row) => $row->fulfillment()->value);

        return collect(FulfillmentType::cases())
            ->map(function (FulfillmentType $type) use ($rows) {
                $row = $rows->get($type->value);

                return [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'orders' => (int) ($row->orders ?? 0),
                    'sales' => round((float) ($row->sales ?? 0), 2),
                ];
            })
            ->all();
    }
}
