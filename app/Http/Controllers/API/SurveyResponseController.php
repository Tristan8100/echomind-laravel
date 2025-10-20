<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\ClassroomStudent;
use App\Models\Classroom;
use App\Models\SurveyQuestion;

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

        $studentId = Auth::id();

        // Check if user is enrolled
        if (!ClassroomStudent::where('classroom_id', $data['classroom_id'])
            ->where('student_id', $studentId)
            ->exists()
        ) {
            return response()->json([
                'message' => 'You are not enrolled in this classroom',
            ], 422);
        }

        // Get survey_id of the classroom
        $classroom = Classroom::findOrFail($data['classroom_id']);
        if (!$classroom->survey_id) {
            return response()->json([
                'message' => 'No survey assigned to this classroom',
            ], 422);
        }

        // Get all question IDs for this survey
        $surveyQuestionIds = SurveyQuestion::whereIn('section_id', function($q) use ($classroom) {
            $q->select('id')
            ->from('survey_sections')
            ->where('survey_id', $classroom->survey_id);
        })->pluck('id')->toArray();

        // Check if all questions are answered
        $submittedQuestionIds = collect($data['responses'])->pluck('survey_question_id')->toArray();
        $missing = array_diff($surveyQuestionIds, $submittedQuestionIds);

        if (!empty($missing)) {
            return response()->json([
                'message' => 'You must answer all questions',
                'missing_question_ids' => $missing
            ], 422);
        }

        // Save responses
        foreach ($data['responses'] as $response) {
            SurveyResponse::updateOrCreate(
                [
                    'classroom_id' => $data['classroom_id'],
                    'student_id' => $studentId,
                    'survey_question_id' => $response['survey_question_id'],
                ],
                [
                    'rating' => $response['rating'],
                ]
            );
        }

        return response()->json([
            'message' => 'Responses submitted successfully',
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
