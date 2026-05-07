<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;

// Protected with Nest.js JWT token
Route::middleware('jwt')->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::get('/user/orders', [UserController::class, 'orders']);
});
