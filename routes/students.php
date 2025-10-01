<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\StudentsController;

Route::middleware('auth:admin-api')->group(function () {
    
});

Route::get('get-students', [StudentsController::class, 'index']);
Route::get('get-student/{id}', [StudentsController::class, 'show']);
Route::get('get-student-classrooms/{id}', [StudentsController::class, 'studentClassroom']);
