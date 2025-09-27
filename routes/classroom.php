<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClassroomController;

// ------------------ OVERALL ----------------------//
Route::middleware('auth:professor-api')->group(function () {
    Route::get('classrooms', [ClassroomController::class, 'authIndex']);
    Route::get('classrooms-archived', [ClassroomController::class, 'authArchived']);
    Route::post('classrooms', [ClassroomController::class, 'store']);
    Route::post('classrooms-update/{id}', [ClassroomController::class, 'update']); // Using POST for update because PUT never works with form-data
    Route::delete('classrooms/{id}', [ClassroomController::class, 'destroy']);

    Route::get('classrooms-students/{classroomId}', [ClassroomController::class, 'showStudents']);
    Route::get('classrooms-evaluations/{classroomId}', [ClassroomController::class, 'showEvaluations']);
    Route::get('classrooms-generate-ai-prof/{id}', [ClassroomController::class, 'generateAiAnalysis']);

    Route::post('classrooms-archive/{id}', [ClassroomController::class, 'archiveClassroom']);
    Route::post('classrooms-activate/{id}', [ClassroomController::class, 'activateClassroom']);
});

Route::middleware('auth:admin-api')->group(function () {
    Route::get('all-classrooms', [ClassroomController::class, 'index']);
    Route::get('classrooms-generate-ai/{id}', [ClassroomController::class, 'generateAiAnalysis']);
});

Route::middleware('auth:user-api')->group(function () {
    Route::get('classrooms-student', [ClassroomController::class, 'getEnrolledClassrooms']);
});

Route::get('classrooms-image/{id}', [ClassroomController::class, 'getImage']); // Image generation