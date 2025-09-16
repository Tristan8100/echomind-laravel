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

class ProfessorSettingsController extends Controller
{
    public function show()
    {
        $professor = Professor::find(Auth::id());
        return response()->json($professor);
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
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $manager = new ImageManager(new Driver());

        // Optimize image
        $image = $manager->read($request->file('image'))
            ->resize(800, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->toJpeg(80);

        // Generate filename & ensure directory
        $filename = 'professor-' . Str::slug($professor->name) . '-' . time() . '.jpg';
        $uploadPath = public_path('uploads/professors');
        File::ensureDirectoryExists($uploadPath);
        $imagePath = $uploadPath . '/' . $filename;

        // Save optimized image
        $image->save($imagePath);

        // Delete old image if exists
        if ($professor->image && File::exists(public_path($professor->image))) {
            File::delete(public_path($professor->image));
        }

        // Store relative path in DB
        $professor->image = '/uploads/professors/' . $filename;
        $professor->save();

        return response()->json([
            'message' => 'Profile photo updated successfully',
            'image'   => $professor->image,
        ], 200);
    }
}
