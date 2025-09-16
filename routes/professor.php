<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProfessorSettingsController;
use App\Models\Professor;

Route::middleware('auth:professor-api')->group(function () {
   Route::get('professor-settings', [ProfessorSettingsController::class, 'show']);
   Route::patch('professor-name', [ProfessorSettingsController::class, 'updateName']);
   Route::patch('professor-email', [ProfessorSettingsController::class, 'updateEmail']);
   Route::patch('professor-password', [ProfessorSettingsController::class, 'updatePassword']);
   Route::post('professor-photo', [ProfessorSettingsController::class, 'updatePhoto']);
});
