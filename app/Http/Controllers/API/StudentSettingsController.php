<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class StudentSettingsController extends Controller
{
    public function show()
    {
        return response()->json(Auth::user());
    }

    public function updateName(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $user = User::find(Auth::id());
        $user->name = $request->name;
        $user->save();

        return response()->json([
            'message' => 'Name updated successfully.',
            'user'    => $user,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'password'     => 'required|string|min:8|confirmed',
        ]);
        $user = User::find(Auth::id());

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Old password is incorrect.'], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
