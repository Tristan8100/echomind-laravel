<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use App\Models\Professor;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;

class ProfessorSettingsController extends Controller
{
    public function show()
    {
        $professor = Professor::find(Auth::id());
        return response()->json($professor);
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $instituteId = $request->get('institute_id');

        $query = Professor::with('institute')
            ->withCount([
                'classrooms as active_classrooms_count' => function ($query) {
                    $query->where('status', 'active');
                }
            ])
            ->selectRaw('(SELECT AVG(rating) 
                        FROM classroom_students 
                        JOIN classrooms ON classrooms.id = classroom_students.classroom_id 
                        WHERE classrooms.prof_id = professors.id) as avg_rating');

        if (!empty($instituteId) && $instituteId !== 'all') {
            $query->where('institute_id', $instituteId);
        }

        $professors = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $professors
        ]);
    }

    // Show a specific professor
    public function showProf($id)
    {
        $professor = Professor::with([
                'institute',
                'classrooms' => function ($query) {
                    $query->withCount('students'); // add student count per classroom
                }
            ])
            ->withCount([
                'classrooms as active_classrooms_count' => function ($query) {
                    $query->where('status', 'active');
                }
            ])
            ->select('professors.*')
            ->selectRaw('(SELECT ROUND(AVG(rating),2) 
                        FROM classroom_students 
                        JOIN classrooms ON classrooms.id = classroom_students.classroom_id 
                        WHERE classrooms.prof_id = professors.id) as avg_rating')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $professor
        ]);
    }



    public function updateName(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $professor = Professor::find(Auth::id());
        $professor->name = $request->name;
        $professor->save();

        return response()->json(['message' => 'Name updated successfully.']);
    }

    public function updateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:professors,email,' . Auth::id(),
        ]);

        $professor = Professor::find(Auth::id());
        $professor->email = $request->email;
        $professor->save();

        return response()->json(['message' => 'Email updated successfully.']);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'password'     => 'required|string|min:8|confirmed',
        ]);

        $professor = Professor::find(Auth::id());

        if (!$professor) {
            return response()->json(['message' => 'Professor not found'], 404);
        }

        // Check old password
        if (!Hash::check($request->old_password, $professor->password)) {
            return response()->json(['message' => 'Old password is incorrect.'], 422);
        }

        // Update password
        $professor->password = Hash::make($request->password);
        $professor->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function updatePhoto(Request $request)
    {
        $professor = Professor::find(Auth::id());

        if (!$professor) {
            return response()->json(['message' => 'Professor not found'], 404);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $manager = new ImageManager(new Driver());

        // Optimize image
        $image = $manager->read($request->file('image')->getRealPath())
            ->resize(800, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->toJpeg(80);

        $cloudinary = new Cloudinary();

        // Delete old photo
        if ($professor->image && str_starts_with($professor->image, 'https://res.cloudinary.com')) {
            try {
                $publicId = pathinfo(parse_url($professor->image, PHP_URL_PATH), PATHINFO_FILENAME);
                $cloudinary->uploadApi()->destroy('echomind/professor/' . $publicId);
            } catch (\Exception $e) {
                Log::error("Failed to delete old professor photo for ID {$professor->id}: " . $e->getMessage());
            }
        }

        // Upload new optimized image to Cloudinary
        try {
            $upload = $cloudinary->uploadApi()->upload($image->toDataUri(), [
                'folder' => 'echomind/professor',
                'public_id' => 'professor_' . Str::uuid(),
                'overwrite' => true,
            ]);

            $professor->image = $upload['secure_url'];
            $professor->save();

            return response()->json([
                'message' => 'Profile photo updated successfully',
                'image' => $professor->image,
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to upload professor photo for ID {$professor->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to upload image.'], 500);
        }
    }
}
