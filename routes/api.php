<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Cloudinary\Cloudinary;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

include __DIR__.'/auth.php';
include __DIR__.'/classroom.php';
include __DIR__.'/classroomstudent.php';
include __DIR__.'/professor.php';
include __DIR__.'/analytics.php';
include __DIR__.'/institutes.php';
include __DIR__.'/students.php';
include __DIR__.'/chatbot.php';
include __DIR__.'/survey.php';

Route::post('add-admin', [\App\Http\Controllers\API\AdminAuthenticationController::class, 'register']); //temporary

Route::post('/upload-image', function (Request $request) {
    $request->validate(['image' => 'required|image']);

    $cloudinary = new Cloudinary();

    $uploadResult = $cloudinary->uploadApi()->upload($request->file('image')->getRealPath());

    // Create URL with f_auto,q_auto transformations
    $optimizedUrl = $cloudinary->image($uploadResult['public_id'])
                              ->format('auto')
                              ->quality('auto')
                              ->toUrl();

    return response()->json([
        'original_url' => $uploadResult['secure_url'],
        'optimized_url' => $optimizedUrl,
    ]);
});

Route::post('/try-ai', function (Request $request) {
    
    $response = Prism::text()
    ->using(Provider::OpenRouter, 'tngtech/deepseek-r1t2-chimera:free')
    ->withPrompt('Tell me a story about AI.')
    ->asText();
    


    return response()->json(['response' => $response->text]);
});

Route::post('add-student', function (Request $request) {
    $request->validate([
        'name'           => 'required|string|min:4',
        'student_number' => 'required|string|max:255',
        'password'       => 'required|string|min:8',
    ]);

    $generatedEmail = $request->student_number . '@btech.ph.education';

    if (\App\Models\User::where('email', $generatedEmail)->exists()) {
        return response()->json([
            'response_code' => 422,
            'status'        => 'error',
            'message'       => 'A user with this student number already exists.',
        ], 422);
    }

    $user = \App\Models\User::create([
        'name'     => $request->name,
        'email'    => $generatedEmail,
        'password' => Hash::make($request->password),
    ]);

    return response()->json([
        'response_code' => 201,
        'status'        => 'success',
        'message'       => 'Student added successfully.',
        'data'          => $user,
    ], 201);
});