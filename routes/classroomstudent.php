<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClassroomStudentController;

// ------------------ OVERALL ----------------------//
Route::middleware('auth:professor-api')->group(function () {
    // Enroll a student manually (admin/professor assigns)
    Route::post('/classrooms/enroll', [ClassroomStudentController::class, 'store']);
    // Remove a student from a classroom
    Route::delete('/classroom-students/{id}', [ClassroomStudentController::class, 'destroy']);
});

Route::middleware('auth:admin-api')->group(function () {

});

Route::middleware('auth:user-api')->group(function () {
    // Self-enroll (student enters a code)
    Route::post('/classrooms-self-enroll', [ClassroomStudentController::class, 'enroll']);
    // Get all classrooms the logged-in student is enrolled in
    Route::get('/my-classrooms', [ClassroomStudentController::class, 'myClassrooms']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Show all students in a classroom
    Route::get('/classrooms/students/{classroomId}', [ClassroomStudentController::class, 'index']);
});
