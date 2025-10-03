<?php

use Illuminate\Support\Facades\Route;

include __DIR__.'/auth.php';
include __DIR__.'/classroom.php';
include __DIR__.'/classroomstudent.php';
include __DIR__.'/professor.php';
include __DIR__.'/analytics.php';
include __DIR__.'/institutes.php';
include __DIR__.'/students.php';
include __DIR__.'/chatbot.php';

Route::post('add-admin', [\App\Http\Controllers\API\AdminAuthenticationController::class, 'register']); //temporary