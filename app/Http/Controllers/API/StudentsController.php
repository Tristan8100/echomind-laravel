<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ClassroomStudent;

class StudentsController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount([
            // classrooms count (excluding archived)
            'classrooms as classrooms_count' => function ($q) {
                $q->where('status', '!=', 'archived');
            },

            // ratings count
            'classroomStudents as ratings_count' => function ($q) {
                $q->whereNotNull('rating');
            },
        ]);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $students = $query->paginate(10);

        return response()->json($students);
    }

    public function show($id)
    {
        $student = User::withCount([
            // classrooms count (excluding archived)
            'classrooms as classrooms_count' => function ($query) {
                $query->where('status', '!=', 'archived');
            },

            // ratings count
            'classroomStudents as ratings_count' => function ($query) {
                $query->whereNotNull('rating');
            },
        ])->findOrFail($id);

        return response()->json($student);
    }

    public function studentClassroom($id)
    {
        $enrollments = ClassroomStudent::with('classroom.professor')
            ->where('student_id', $id)
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

}
