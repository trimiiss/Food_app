<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Customers may only see and cancel their own orders.
 *
 * denyAsNotFound(): order ids are sequential, so a 403 would confirm that
 * another customer's order #57 exists. A 404 reveals nothing.
 * Admins use the separate /admin/orders endpoints, guarded by middleware.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        return $order->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound('Order not found.');
    }

    public function cancel(User $user, Order $order): Response
    {
        return $this->view($user, $order);
    }
}
