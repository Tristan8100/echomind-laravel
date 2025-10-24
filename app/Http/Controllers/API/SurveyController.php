<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Survey;
use Illuminate\Support\Facades\Auth;
use App\Models\Classroom;
use App\Models\SurveyResponse;

class SurveyController extends Controller
{
    public function index()
    {
        $surveys = Survey::with('sections.questions')->get();
        return response()->json($surveys);
    }

    public function index2()
    {
        $surveys = Survey::with('sections.questions')->orderByDesc('created_at')->get();
        return response()->json($surveys);
    }

    public function show($id)
    {
        $survey = Survey::with('sections.questions')->findOrFail($id);
        return response()->json($survey);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:pending,active',
        ]);

        $survey = Survey::create($data);
        return response()->json($survey, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:pending,active,archived',
        ]);

        $survey = Survey::findOrFail($id);
        $survey->update($request->only('title', 'description', 'status'));
        return response()->json($survey);
    }

    public function destroy($id)
    {
        $survey = Survey::findOrFail($id);
        $survey->delete();
        return response()->json(['message' => 'Survey deleted successfully']);
    }

    // for professor to assign survey to their classroom
    public function assignSurvey(Request $request, $id)
    {
        $data = $request->validate([
            'survey_id' => 'required|exists:surveys,id',
        ]);

        $professorId = Auth::id();

        // Find classroom owned by the logged-in professor
        $classroom = Classroom::where('id', $id)
            ->where('prof_id', $professorId)
            ->firstOrFail();

        // Prevent reassigning if already has a survey
        if ($classroom->survey_id && $classroom->survey_id != $data['survey_id']) {
            return response()->json([
                'error' => 'This classroom already has a survey assigned. Unassign it first before changing.',
            ], 400);
        }

        // Assign the survey
        $classroom->update(['survey_id' => $data['survey_id']]);

        return response()->json([
            'message' => 'Survey assigned successfully to classroom.',
            'data' => $classroom->load('survey'),
        ]);
    }

    public function checkSurvey($classroomId)
    {
        $classroom = Classroom::with('survey')
            ->where('id', $classroomId)
            ->where('prof_id', Auth::id())
            ->firstOrFail();

        if ($classroom->survey) {
            return response()->json([
                'has_survey' => true,
                'survey' => [
                    'id' => $classroom->survey->id,
                    'title' => $classroom->survey->title,
                ],
            ]);
        }

        return response()->json([
            'has_survey' => false,
            'survey' => null,
        ]);
    }

    public function getSurveyWithResponses($classroomId)
    {
        // Find classroom with survey_id
        $classroom = Classroom::with('survey.sections.questions')
            ->where('id', $classroomId)
            ->where('prof_id', Auth::id())
            ->firstOrFail();

        // Make sure classroom has a survey assigned
        if (!$classroom->survey_id) {
            return response()->json([
                'message' => 'This classroom has no survey assigned.'
            ], 404);
        }

        $survey = $classroom->survey;

        $totalRespondents = SurveyResponse::where('classroom_id', $classroom->id)
        ->distinct('student_id')
        ->count('student_id');

        // Load sections and questions
        $sections = $survey->sections->map(function ($section) use ($classroom) {
            $section->questions = $section->questions->map(function ($question) use ($classroom) {
                // Count responses 1–5 for this question in this classroom
                $responseCounts = SurveyResponse::where('classroom_id', $classroom->id)
                    ->where('survey_question_id', $question->id)
                    ->selectRaw('rating, COUNT(*) as count')
                    ->groupBy('rating')
                    ->pluck('count', 'rating');

                // Fill missing ratings (e.g. no 2's or 3's)
                $ratings = [];
                for ($i = 1; $i <= 5; $i++) {
                    $ratings[$i] = $responseCounts[$i] ?? 0;
                }

                return [
                    'id' => $question->id,
                    'text' => $question->question_text,
                    'ratings' => $ratings
                ];
            });

            return [
                'id' => $section->id,
                'title' => $section->title,
                'questions' => $section->questions
            ];
        });

        return response()->json([
            'classroom_id' => $classroom->id,
            'classroom_name' => $classroom->name,
            'total_respondents' => $totalRespondents,
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'sections' => $sections
            ]
        ]);
    }

}
