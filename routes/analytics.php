<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AnalyticsController;
use App\Http\Controllers\API\AdminAnalyticsController;
use App\Models\Admin;

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

Route::middleware('auth:admin-api')->group(function () {
    Route::get('admin-system-overview', [AdminAnalyticsController::class, 'systemOverview']);
    Route::get('admin-professor-analytics', [AdminAnalyticsController::class, 'professorAnalytics']);
    Route::get('admin-classroom-analytics', [AdminAnalyticsController::class, 'classroomAnalytics']);
    Route::get('admin-subject-analytics', [AdminAnalyticsController::class, 'subjectAnalytics']);
    Route::get('admin-student-engagement', [AdminAnalyticsController::class, 'studentEngagement']);
    Route::get('admin-system-trends', [AdminAnalyticsController::class, 'systemTrends']);
    Route::get('admin-content-moderation', [AdminAnalyticsController::class, 'contentModeration']);
    Route::get('admin-ai-insights', [AdminAnalyticsController::class, 'aiInsights']);
    Route::get('admin-export-report', [AdminAnalyticsController::class, 'exportReport']);
});

Route::get('analytics-institutes', [AdminAnalyticsController::class, 'getAnalytics']); // For filter dropdown

Route::middleware('auth:user-api')->group(function () {
    Route::get('student-analytics', [AnalyticsController::class, 'studentAnalytics']);
});
