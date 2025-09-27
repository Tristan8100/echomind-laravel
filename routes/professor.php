<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProfessorSettingsController;
use App\Http\Controllers\API\StudentSettingsController;
use App\Models\Professor;

Route::middleware('auth:professor-api')->group(function () {
   Route::get('professor-settings', [ProfessorSettingsController::class, 'show']);
   Route::patch('professor-name', [ProfessorSettingsController::class, 'updateName']);
   Route::patch('professor-email', [ProfessorSettingsController::class, 'updateEmail']);
   Route::patch('professor-password', [ProfessorSettingsController::class, 'updatePassword']);
   Route::post('professor-photo', [ProfessorSettingsController::class, 'updatePhoto']);
});

Route::middleware('auth:user-api')->group(function () {
   Route::get('student-settings', [StudentSettingsController::class, 'show']);
   Route::patch('student-name', [StudentSettingsController::class, 'updateName']);
   Route::patch('student-password', [StudentSettingsController::class, 'updatePassword']);
});