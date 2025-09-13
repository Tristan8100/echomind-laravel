<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClassroomStudent;
use App\Models\Classroom;
use Illuminate\Support\Facades\Auth;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

class ClassroomStudentController extends Controller
{
    /**
     * Show all students in a classroom.
     */
    public function index($classroomId)
    {
        $students = ClassroomStudent::with('student')
            ->where('classroom_id', $classroomId)
            ->get();

        return response()->json($students, 200);
    }

    /**
     * Enroll a student into a classroom.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'student_id'   => 'required|exists:users,id',
        ]);

        $enrollment = ClassroomStudent::create($validated);

        return response()->json($enrollment, 201);
    }

    public function enroll(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $studentId = Auth::id(); // logged-in student
        $code = $request->code;

        // Find classroom by code
        $classroom = Classroom::where('code', $code)->first();

        if (!$classroom) {
            return response()->json(['message' => 'Invalid classroom code.'], 404);
        }

        // Check if already enrolled
        $alreadyEnrolled = ClassroomStudent::where('classroom_id', $classroom->id)
            ->where('student_id', $studentId)
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json(['message' => 'You are already enrolled in this classroom.'], 400);
        }

        // Enroll student
        $enrollment = ClassroomStudent::create([
            'classroom_id' => $classroom->id,
            'student_id'   => $studentId,
        ]);

        return response()->json([
            'message' => 'Successfully enrolled in the classroom.',
            'classroom' => $classroom,
            'enrollment' => $enrollment
        ], 201);
    }

    public function myClassrooms()
    {
        $studentId = Auth::id();

        $enrollments = ClassroomStudent::with('classroom.professor')
            ->where('student_id', $studentId)
            ->get();

        return response()->json([
            'classrooms' => $enrollments->map(function ($enrollment) {
                return [
                    'id' => $enrollment->classroom->id,
                    'name' => $enrollment->classroom->name,
                    'subject' => $enrollment->classroom->subject,
                    'description' => $enrollment->classroom->description,
                    'image' => $enrollment->classroom->image,
                    'code' => $enrollment->classroom->code,
                    'professor' => [
                        'id' => $enrollment->classroom->professor->id,
                        'name' => $enrollment->classroom->professor->name,
                        'email' => $enrollment->classroom->professor->email,
                        'image' => $enrollment->classroom->professor->image,
                    ],
                ];
            }),
        ]);
    }

    /**
     * Remove a student from a classroom.
     */
    public function destroy($id)
    {
        $classroomStudent = ClassroomStudent::find($id);

        if (!$classroomStudent) {
            return response()->json(['message' => 'Enrollment not found'], 404);
        }

        $classroomStudent->delete();

        return response()->json(['message' => 'Student removed from classroom'], 200);
    }

    public function evaluate(Request $request, $classroomId)
    {
        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'required|string',
        ]);

        $studentId = Auth::id();

        // Find the enrollment record for this student in this classroom
        $classroomStudent = ClassroomStudent::where('classroom_id', $classroomId)
            ->where('student_id', $studentId)
            ->firstOrFail();

        // Define schema for AI response
        $schema = new ObjectSchema(
            name: 'professor_evaluation',
            description: 'A structured review of user evaluation experience',
            properties: [
                new StringSchema('sentiment', 'The sentiment of the evaluation: Positive, Neutral, or Negative'),],
            requiredFields: ['sentiment']
        );

        // Call AI with the student's comment
        $response = Prism::structured()
            ->using(Provider::Gemini, 'gemini-2.0-flash')
            ->withSchema($schema)
            ->withPrompt($validated['comment'])
            ->asStructured();

        // Update evaluation fields directly
        $classroomStudent->update([
            'rating'          => $validated['rating'],
            'comment'         => $validated['comment'],
            'sentiment'       => $response->structured['sentiment'],
        ]);

        return response()->json([
            'message' => 'Evaluation saved successfully.',
            'data'    => $classroomStudent
        ], 200);
    }

    
}
