<?php

use Illuminate\Support\Facades\Route;

/*
| This backend is API-only; the UI is the React SPA in /frontend.
| The root URL just tells anyone who opens it in a browser where to go.
*/
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
    'health' => url('/up'),
]));
