<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClassroomStudent;
use App\Models\Classroom;
use Illuminate\Support\Facades\Auth;

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

    
}
