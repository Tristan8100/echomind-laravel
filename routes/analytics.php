<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AnalyticsController;

// ------------------ PROFESSOR ANALYTICS ----------------------//
Route::middleware('auth:professor-api')->group(function () {
    // Main dashboard analytics
    Route::get('professor-analytics', [AnalyticsController::class, 'index']);
    
    // Detailed analytics endpoints
    Route::get('professor-analytics/classroom-performance', [AnalyticsController::class, 'getClassroomPerformance']);
    Route::get('professor-analytics/recent-feedback', [AnalyticsController::class, 'getRecentFeedback']);
    Route::get('professor-analytics/trends', [AnalyticsController::class, 'getTrendData']);
    Route::get('professor-analytics/top-classrooms', [AnalyticsController::class, 'getTopPerformingClassrooms']);
    Route::get('professor-analytics/rating-distribution', [AnalyticsController::class, 'getRatingDistribution']);
});