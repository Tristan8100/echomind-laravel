<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
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
