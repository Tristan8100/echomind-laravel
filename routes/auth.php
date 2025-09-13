<?php

use App\Http\Controllers\API\ResetPasswordController;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\VerifyEmailController;

use App\Http\Controllers\API\AuthenticationController;
use App\Http\Controllers\API\AdminAuthenticationController;
use App\Http\Controllers\API\ProfessorAuthenticationController;


// --------------- Register and Login (STUDENTS) ----------------//
Route::post('register', [AuthenticationController::class, 'register'])->name('register');
Route::post('login', [AuthenticationController::class, 'login'])->name('login');

// ------------------ OVERALL ----------------------//
Route::middleware('auth:sanctum')->group(function () {
    Route::get('get-user', [AuthenticationController::class, 'userInfo'])->name('get-user'); //testing
    Route::post('logout', [AuthenticationController::class, 'logOut'])->name('logout');
});


Route::post('/send-otp', [VerifyEmailController::class, 'sendOtp'])
    ->name('verification.send')
    ->middleware(['throttle:6,1']);

Route::post('/verify-otp', [VerifyEmailController::class, 'verifyOtp']) //STUDENTS AND PROFESSORS
    ->name('verification.verify')
    ->middleware(['throttle:6,1']);

Route::post('/forgot-password', [ResetPasswordController::class, 'sendResetLink'])
    ->name('password.email')
    ->middleware(['throttle:6,1']);

Route::post('/forgot-password-token', [ResetPasswordController::class, 'verifyOtp'])
    ->name('password.reset')
    ->middleware(['throttle:6,1']);

Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword'])
    ->name('password.update')
    ->middleware(['throttle:6,1']);


// --------------- Register and Login (ADMIN) ----------------//
Route::post('admin-login', [AdminAuthenticationController::class, 'login']);
Route::post('admin-change-password', [AdminAuthenticationController::class, 'changePasswordAdmin']);

// --------------- Register and Login (PROFESSOR) ----------------//
Route::post('professor-login', [ProfessorAuthenticationController::class, 'login']); //not yet tested
Route::post('professor-change-password', [ProfessorAuthenticationController::class, 'changePasswordProffesor']);
Route::post('professor-register', [ProfessorAuthenticationController::class, 'register']); //not yet tested //ADMIN ROUTE BTW


Route::middleware('auth:user-api')->group(function () {
    Route::get('hi-user', function () {
        return response()->json(['message' => 'Hi!']);
    })->name('hi user');

    // Route Checking in Next js
    Route::get('verify-user', [AuthenticationController::class, 'user'])->name('verify-user');
});

Route::middleware('auth:admin-api')->group(function () {
    Route::get('hi-admin', function () {
        return response()->json(['message' => 'Hi!']);
    })->name('hi admin');

    // Route Checking in Next js
    Route::get('verify-admin', [AuthenticationController::class, 'user'])->name('verify-user');
});

Route::middleware('auth:professor-api')->group(function () {
    Route::get('hi-prof', function () {
        return response()->json(['message' => 'Hi!']);
    })->name('hi prof');

    // Route Checking in Next js
    Route::get('verify-prof', [AuthenticationController::class, 'user'])->name('verify-user');
});