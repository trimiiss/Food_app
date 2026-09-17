<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    /**
     * @return array<string, array{OrderStatus, OrderStatus}>
     */
    public static function validTransitions(): array
    {
        return [
            'pending -> confirmed' => [OrderStatus::Pending, OrderStatus::Confirmed],
            'confirmed -> preparing' => [OrderStatus::Confirmed, OrderStatus::Preparing],
            'preparing -> out for delivery' => [OrderStatus::Preparing, OrderStatus::OutForDelivery],
            'out for delivery -> delivered' => [OrderStatus::OutForDelivery, OrderStatus::Delivered],
            'pending -> cancelled' => [OrderStatus::Pending, OrderStatus::Cancelled],
            'out for delivery -> cancelled' => [OrderStatus::OutForDelivery, OrderStatus::Cancelled],
        ];
    }

    /**
     * @return array<string, array{OrderStatus, OrderStatus}>
     */
    public static function invalidTransitions(): array
    {
        return [
            'cannot skip ahead' => [OrderStatus::Pending, OrderStatus::Delivered],
            'cannot go backwards' => [OrderStatus::Preparing, OrderStatus::Confirmed],
            'delivered is final' => [OrderStatus::Delivered, OrderStatus::Cancelled],
            'cancelled is final' => [OrderStatus::Cancelled, OrderStatus::Pending],
            'no self transition' => [OrderStatus::Pending, OrderStatus::Pending],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_allows_valid_transitions(OrderStatus $from, OrderStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[DataProvider('invalidTransitions')]
    public function test_rejects_invalid_transitions(OrderStatus $from, OrderStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public function test_terminal_states(): void
    {
        $this->assertTrue(OrderStatus::Delivered->isFinal());
        $this->assertTrue(OrderStatus::Cancelled->isFinal());
        $this->assertFalse(OrderStatus::Pending->isFinal());
    }

    public function test_customer_can_only_cancel_before_preparation_starts(): void
    {
        $this->assertTrue(OrderStatus::Pending->isCancellableByCustomer());
        $this->assertTrue(OrderStatus::Confirmed->isCancellableByCustomer());
        $this->assertFalse(OrderStatus::Preparing->isCancellableByCustomer());
        $this->assertFalse(OrderStatus::Delivered->isCancellableByCustomer());
    }
}
