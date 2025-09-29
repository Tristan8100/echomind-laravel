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
use Prism\Prism\Schema\EnumSchema;
use App\Models\ClassroomStudent;


use Prism\Prism\ValueObjects\Media\Image;

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
            ->withCount('students') // adds students_count column
            ->where('prof_id', $profId)
            ->where('status', 'active')
            ->get();

        return response()->json($classrooms, 200);
    }

    public function authIndexAdmin($id) 
    {
        $classrooms = Classroom::with('professor')
            ->withCount('students') // adds students_count column
            ->where('prof_id', $id)
            ->where('status', 'active')
            ->get();

        return response()->json($classrooms, 200);
    }

    public function authArchived() 
    {
        $profId = Auth::id();

        if (!$profId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $classrooms = Classroom::with('professor')
            ->withCount('students')
            ->where('prof_id', $profId)
            ->where('status', 'archived')
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

    public function showStudents($classroomId)
    {
        // Ensure the classroom belongs to the logged-in professor
        $classroom = Classroom::where('id', $classroomId)
            ->where('prof_id', Auth::id())
            ->with(['students.student'])
            ->firstOrFail();

        // Map student data with name and email
        $students = $classroom->students->map(function($cs) {
            return [
                'id' => $cs->student->id,
                'name' => $cs->student->name,
                'email' => $cs->student->email,
            ];
        });

        return response()->json([
            'classroom' => $classroom,
            'students' => $students,
        ]);
    }

    public function showEvaluations($classroomId)
    {
        $classroom = Classroom::where('id', $classroomId)
            ->where('prof_id', Auth::id())
            ->with('students') // eager load classroom_students
            ->firstOrFail();

        $evaluations = $classroom->students
            ->filter(function ($cs) {
                // Only include if rating or comment is not null
                return !is_null($cs->rating) || !is_null($cs->comment);
            })
            ->map(function ($cs) {
                return [
                    'rating' => $cs->rating,
                    'comment' => $cs->comment,
                    'sentiment' => $cs->sentiment,
                    'sentiment_score' => $cs->sentiment_score,
                ];
            });

        return response()->json([
            'classroom' => $classroom->name,
            'evaluations' => $evaluations,
        ]);
    }

    public function getEnrolledClassrooms(Request $request)
    {
        $request->validate([
            'status' => 'sometimes|in:active,archived',
        ]);

        $studentId = Auth::id();

        // Fetch enrollments with classroom and professor
        $enrollments = ClassroomStudent::with(['classroom.professor'])
            ->where('student_id', $studentId)
            ->whereHas('classroom', function ($q) use ($request) {
                $q->where('status', $request->status ?? 'active');
            })
            ->get();

        // Map classrooms
        $classrooms = $enrollments->map(function ($cs) {
            return [
                'id' => $cs->classroom->id,
                'name' => $cs->classroom->name,
                'subject' => $cs->classroom->subject,
                'description' => $cs->classroom->description,
                'image' => $cs->classroom->image,
                'code' => $cs->classroom->code,
                'professor' => $cs->classroom->professor
                    ? [
                        'id' => $cs->classroom->professor->id,
                        'name' => $cs->classroom->professor->name,
                        'email' => $cs->classroom->professor->email,
                        'image' => $cs->classroom->professor->image,
                    ]
                    : null,
                // Include student evaluation info
                'evaluated' => $cs->rating !== null || $cs->comment !== null || $cs->sentiment !== null,
                'evaluated_at' => $cs->updated_at,
                'rating' => $cs->rating,
                'comment' => $cs->comment,
                'sentiment' => $cs->sentiment,
            ];
        });

        return response()->json([
            'success' => true,
            'classrooms' => $classrooms
        ]);
    }

    public function archiveClassroom($id)
    {
        $classroom = Classroom::where('id', $id)
            ->where('prof_id', Auth::id())
            ->firstOrFail();

        $classroom->update(['status' => 'archived']);

        return response()->json(['message' => 'Classroom archived successfully'], 200);
    }

    public function activateClassroom($id)
    {
        $classroom = Classroom::where('id', $id)
            ->where('prof_id', Auth::id())
            ->firstOrFail();

        $classroom->update(['status' => 'active']);

        return response()->json(['message' => 'Classroom archived successfully'], 200);
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
                new EnumSchema(
                name: 'sentiment',
                description: 'The greatest sentiment of the evaluations: Positive, Neutral, or Negative',
                options: ['Positive', 'Neutral', 'Negative']
                ),
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

    public function getImage($id)
{
    $classroom = Classroom::findOrFail($id);
    $path = public_path($classroom->image);

    if (!File::exists($path)) {
        return response()->json(['message' => 'Image not found'], 404);
    }

    // open original file
    $originalImage = fopen($path, 'r');

    // Ask Gemini to edit the image
    $response = Prism::image()
        ->using(Provider::Gemini, 'gemini-2.0-flash-preview-image-generation') // image editing model
        ->withPrompt('this is our wedding venue, I dont have an idea how to style it, perhaps you edit this venue picture to make it look like a wedding venue')
        ->withProviderOptions([
            'image' => $originalImage,
            'image_mime_type' => mime_content_type($path), // detect mime type automatically
        ])
        ->generate();

    // save new image next to original
    $newFilename = 'edited-' . basename($classroom->image);
    $newPath = public_path('uploads/' . $newFilename);

    file_put_contents($newPath, base64_decode($response->firstImage()->base64));

    return response()->json([
        'original' => asset($classroom->image),
        'edited'   => asset('uploads/' . $newFilename),
    ]);
}

}
