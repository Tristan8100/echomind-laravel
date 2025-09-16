<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AnalyticsController;

// ------------------ OVERALL ----------------------//
Route::middleware('auth:professor-api')->group(function () {
    Route::get('professor-analytics', [AnalyticsController::class, 'index']);
});