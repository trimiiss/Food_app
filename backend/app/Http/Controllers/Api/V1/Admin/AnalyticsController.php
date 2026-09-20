<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sales over time, best sellers and promo-code performance for the admin
 * analytics page. Everything it reports excludes cancelled orders.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    /**
     * GET /admin/analytics?days=30
     */
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            // A fixed set: the UI offers these, and an unbounded range would
            // let one request scan the whole orders table.
            'days' => ['nullable', 'integer', 'in:7,30,90,365'],
        ]);

        return response()->json([
            'data' => $this->analytics->forLastDays((int) ($filters['days'] ?? 30)),
        ]);
    }
}
