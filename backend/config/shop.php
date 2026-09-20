<?php

return [

    /*
    | Flat delivery fee added to every delivery order, in the store currency.
    | Stored on each order at checkout so later changes don't rewrite history.
    | Pickup orders are never charged it (see App\Enums\FulfillmentType).
    */
    'delivery_fee' => (float) env('SHOP_DELIVERY_FEE', 2.50),

    'currency' => env('SHOP_CURRENCY', 'EUR'),

    /*
    | Upper bound on a single line's quantity — a sanity guard, not a stock system.
    */
    'max_item_quantity' => 50,

    /*
    | Where customers collect a pickup order, and roughly how long the kitchen
    | needs before it is ready. Shown at checkout and on the order page.
    */
    'pickup' => [
        'address' => env('SHOP_PICKUP_ADDRESS', 'LeuEats Kitchen · Rruga B, 10000 Prishtina'),
        'ready_in_minutes' => (int) env('SHOP_PICKUP_READY_MINUTES', 20),
    ],

];
