<?php

return [

    /*
    | Flat delivery fee added to every order, in the store currency.
    | Stored on each order at checkout so later changes don't rewrite history.
    */
    'delivery_fee' => (float) env('SHOP_DELIVERY_FEE', 2.50),

    'currency' => env('SHOP_CURRENCY', 'EUR'),

    /*
    | Upper bound on a single line's quantity — a sanity guard, not a stock system.
    */
    'max_item_quantity' => 50,

];
