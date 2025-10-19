<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\Auth;

class SurveyResponseController extends Controller
{

    public function store(Request $request)
    {
        $data = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'responses' => 'required|array',
            'responses.*.survey_question_id' => 'required|exists:survey_questions,id',
            'responses.*.rating' => 'required|integer|min:1|max:5',
        ]);

        $inserted = [];
        foreach ($data['responses'] as $response) {
        SurveyResponse::updateOrCreate(
            [
                'classroom_id' => $data['classroom_id'],
                'student_id' => Auth::id(),
                'survey_question_id' => $response['survey_question_id'],
            ],
            [
                'rating' => $response['rating'],
            ]
        );
    }

        return response()->json([
            'message' => 'Responses submitted successfully',
            'data' => $inserted,
        ], 201);
    }

    public function index($classroomId)
    {
        $responses = SurveyResponse::where('classroom_id', $classroomId)
            ->with(['question.section.survey', 'student'])
            ->get();

        return response()->json($responses);
    }

    public function getStudentResponses($classroom_id)
    {
        $studentId = Auth::id();

        $responses = SurveyResponse::where('classroom_id', $classroom_id)
            ->where('student_id', $studentId)
            ->with('question') // optional: eager load related question
            ->get()
            ->map(function ($response) {
                return [
                    'survey_question_id' => $response->survey_question_id,
                    'rating' => $response->rating,
                    'question_text' => $response->question->question_text ?? null,
                ];
            });

        return response()->json([
            'classroom_id' => $classroom_id,
            'student_id' => $studentId,
            'responses' => $responses,
        ]);
    }

}
