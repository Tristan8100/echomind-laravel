<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\InstituteController;

Route::middleware('auth:admin-api')->group(function () {
    Route::get('institutes', [InstituteController::class, 'index']);
    Route::post('institutes', [InstituteController::class, 'store']);
    Route::get('institutes/{id}', [InstituteController::class, 'show']);
    Route::put('institutes/{id}', [InstituteController::class, 'update']);
    Route::delete('institutes/{id}', [InstituteController::class, 'destroy']);
});