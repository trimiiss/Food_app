<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — version 1
|--------------------------------------------------------------------------
| Everything is served under /api/v1. Versioning from day one costs nothing
| and means a breaking change later can ship as /api/v2 without stranding
| existing clients.
*/

Route::prefix('v1')->group(function () {
    //
});
