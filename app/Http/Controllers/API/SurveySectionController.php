<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SurveySection;
use App\Models\Survey;

class SurveySectionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'survey_id' => 'required|exists:surveys,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $section = SurveySection::create($data);
        return response()->json($section, 201);
    }

    public function getSurveySections($id)
    {
         $survey = Survey::findOrFail($id);

        $sections = $survey->sections()->with('questions')->get();

        return response()->json($sections);
    }

    public function update(Request $request, $id)
    {
        $section = SurveySection::findOrFail($id);
        $section->update($request->only('title', 'description'));
        return response()->json($section);
    }

    public function destroy($id)
    {
        $section = SurveySection::findOrFail($id);
        $section->delete();
        return response()->json(['message' => 'Section deleted successfully']);
    }
}
