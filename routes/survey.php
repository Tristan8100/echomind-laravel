<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\SurveyController;
use App\Http\Controllers\API\SurveySectionController;
use App\Http\Controllers\API\SurveyResponseController;
use App\Http\Controllers\API\SurveyQuestionController;
use App\Models\Survey;

Route::middleware('auth:professor-api')->group(function () {
    //for professor to assign survey to their classroom
    Route::post('/surveys-assign/{id}', [SurveyController::class, 'assignSurvey']);

    Route::get('/classrooms/{id}/survey-report', [SurveyController::class, 'getSurveyWithResponses']);
});

Route::middleware('auth:user-api')->group(function () {
    //Survey Responses
    Route::post('/survey-responses', [SurveyResponseController::class, 'store']);
    Route::get('/survey-responses/{classroom_id}', [SurveyResponseController::class, 'getStudentResponses']);

});

Route::middleware('auth:admin-api')->group(function () {
    //Surveys
    Route::post('/surveys', [SurveyController::class, 'store']);
    Route::put('/surveys/{id}', [SurveyController::class, 'update']);
    Route::delete('/surveys/{id}', [SurveyController::class, 'destroy']);

    //Survey Sections
    Route::post('/survey-sections', [SurveySectionController::class, 'store']);
    Route::put('/survey-sections/{id}', [SurveySectionController::class, 'update']);
    Route::delete('/survey-sections/{id}', [SurveySectionController::class, 'destroy']);

    //Survey Questions
    Route::post('/survey-questions', [SurveyQuestionController::class, 'store']);
    Route::put('/survey-questions/{id}', [SurveyQuestionController::class, 'update']);
    Route::delete('/survey-questions/{id}', [SurveyQuestionController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/surveys', [SurveyController::class, 'index2']);
    Route::get('/surveys/{id}', [SurveyController::class, 'show']);
    Route::get('/classrooms/{id}/check-survey', [SurveyController::class, 'checkSurvey']);
    //Route::get('/classrooms/{id}/survey-report', [SurveyController::class, 'getSurveyWithResponses']);



    Route::get('/survey-responses/{classroomId}', [SurveyResponseController::class, 'index']);

    Route::get('/survey-sections/{id}', [SurveySectionController::class, 'getSurveySections']);
});