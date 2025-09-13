<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\File;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

use App\Models\ClassroomStudent;

class ClassroomController extends Controller
{
    /**
     * Display a listing of classrooms.
     */
    public function index()
    {
        $classrooms = Classroom::with('professor')->get();

        return response()->json($classrooms, 200);
    }

    public function authIndex()
    {
        $profId = Auth::id();

        if (!$profId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $classrooms = Classroom::with('professor')
            ->where('prof_id', $profId)
            ->get();

        return response()->json($classrooms, 200);
    }
    
    /**
     * Store a newly created classroom.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'subject'     => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $validated['prof_id'] = Auth::id();

        // Handle image upload with Intervention Image
        if ($request->hasFile('image')) {
            $manager = new ImageManager(new Driver());

            // Read and optimize image
            $image = $manager->read($request->file('image'))
                ->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->toJpeg(80); // Save as JPEG with 80% quality

            // Generate filename and ensure directory
            $filename = 'classroom-' . Str::slug($request->name) . '-' . time() . '.jpg';
            $uploadPath = public_path('uploads/classrooms');
            File::ensureDirectoryExists($uploadPath);
            $imagePath = $uploadPath . '/' . $filename;

            // Save optimized image
            $image->save($imagePath);

            // Store relative path in DB
            $validated['image'] = '/uploads/classrooms/' . $filename;
        }

        // Generate a unique classroom code
        do {
            $code = strtoupper(Str::random(6));
        } while (Classroom::where('code', $code)->exists());

        $validated['code'] = $code;

        $classroom = Classroom::create($validated);

        return response()->json($classroom, 201);
    }

    /**
     * Display the specified classroom.
     */
    public function show($id)
    {
        $classroom = Classroom::with(['professor', 'students'])->find($id);

        if (!$classroom) {
            return response()->json(['message' => 'Classroom not found'], 404);
        }

        return response()->json($classroom, 200);
    }

    /**
     * Update the specified classroom.
     */
    public function update(Request $request, $id)
    {
        $classroom = Classroom::find($id);

        if (!$classroom) {
            return response()->json(['message' => 'Classroom not found'], 404);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'subject'     => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Always enforce professor ownership
        $validated['prof_id'] = Auth::id();

        // Handle image upload with Intervention Image
        if ($request->hasFile('image')) {
            $manager = new ImageManager(new Driver());

            // Read and optimize image
            $image = $manager->read($request->file('image'))
                ->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->toJpeg(80); // Save as JPEG with 80% quality

            // Generate filename and ensure directory
            $filename = 'classroom-' . Str::slug($request->name ?? $classroom->name) . '-' . time() . '.jpg';
            $uploadPath = public_path('uploads/classrooms');
            File::ensureDirectoryExists($uploadPath);
            $imagePath = $uploadPath . '/' . $filename;

            // Save optimized image
            $image->save($imagePath);

            // Delete old image if exists
            if ($classroom->image && File::exists(public_path($classroom->image))) {
                File::delete(public_path($classroom->image));
            }

            // Store relative path in DB
            $validated['image'] = '/uploads/classrooms/' . $filename;
        }

        $classroom->update($validated);

        return response()->json($classroom, 200);
    }

    /**
     * Remove the specified classroom.
     */
    public function destroy($id)
    {
        $classroom = Classroom::find($id);

        if (!$classroom) {
            return response()->json(['message' => 'Classroom not found'], 404);
        }

        // Delete image file if it exists
        if ($classroom->image && File::exists(public_path($classroom->image))) {
            File::delete(public_path($classroom->image));
        }

        $classroom->delete();

        return response()->json(['message' => 'Classroom deleted successfully'], 200);
    }

    public function generateAiAnalysis($id)
    {
        // Step 1: Get classroom with evaluations
        $classroom = Classroom::findOrFail($id);

        $evaluations = ClassroomStudent::where('classroom_id', $id)
            ->whereNotNull('comment')
            ->whereNotNull('sentiment')
            ->get(['comment', 'rating', 'sentiment', 'sentiment_score']);

        if ($evaluations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No student evaluations available for this classroom.'
            ], 404);
        }

        // Step 2: Prepare evaluation content
        $evalText = $evaluations->map(function ($e) {
            return "Comment: {$e->comment}\nRating: {$e->rating}\nSentiment: {$e->sentiment} ({$e->sentiment_score}%)";
        })->implode("\n\n");

        // Step 3: Define schema for AI response
        $schema = new ObjectSchema(
            name: 'classroom_analysis',
            description: 'AI-generated analysis and recommendations based on student evaluations',
            properties: [
                new StringSchema('sentiment', 'The sentiment of the evaluation: Positive, Neutral, or Negative'),
                new StringSchema('analysis', 'Summarized analysis of evaluations'),
                new StringSchema('recommendation', 'Actionable recommendations for the professor or class improvements'),
            ],
            requiredFields: ['sentiment', 'analysis', 'recommendation']
        );

        // Step 4: Send to AI via Prism
        $response = Prism::structured()
            ->using(Provider::Gemini, 'gemini-2.0-flash')
            ->withSchema($schema)
            ->withPrompt("Here are student evaluations for classroom '{$classroom->name}':\n\n{$evalText}\n\nPlease provide an overall analysis and recommendations.")
            ->asStructured();

        // Step 5: Update the Classroom with AI output
        $classroom->update([
            'sentiment_analysis' => $response->structured['sentiment'],
            'ai_analysis' => $response->structured['analysis'],
            'ai_recommendation' => $response->structured['recommendation'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $classroom,
            'message' => 'AI analysis and recommendation generated successfully.'
        ]);
    }

}
