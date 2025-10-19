<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SurveyQuestion;

class SurveyQuestionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'section_id' => 'required|exists:survey_sections,id',
            'question_text' => 'required|string',
        ]);

        $question = SurveyQuestion::create($data);
        return response()->json($question, 201);
    }

    public function update(Request $request, $id)
    {
        $question = SurveyQuestion::findOrFail($id);
        $question->update($request->only('question_text'));
        return response()->json($question);
    }

    public function destroy($id)
    {
        $question = SurveyQuestion::findOrFail($id);
        $question->delete();
        return response()->json(['message' => 'Question deleted successfully']);
    }
}
