<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use App\Models\Professor;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Admin;


class ProfessorAuthenticationController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'        => 'required|string|min:4',
                'email'       => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    function ($attribute, $value, $fail) {
                        if (
                            User::where('email', $value)->exists()
                            || Professor::where('email', $value)->exists()
                            || Admin::where('email', $value)->exists()
                        ) {
                            $fail('The '.$attribute.' has already been taken.');
                        }
                    },
                ],
                'password'    => 'required|string|min:8',
                'institute_id'=> 'required|exists:institutes,id', // newly added
            ]);

            // Extra check (not strictly needed since above covers it)
            if (Professor::where('email', $validated['email'])->exists()) {
                return response()->json([
                    'response_code' => 422,
                    'status'        => 'error',
                    'message'       => 'A user with this email already exists.',
                ], 422);
            }

            $professor = new Professor([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // newly added
            $professor->institute()->associate($validated['institute_id']);
            $professor->save();

            // Generate 6-digit OTP
            $otp = rand(100000, 999999);

            EmailVerification::updateOrCreate(
                ['email' => $professor->email],
                [
                    'otp'        => $otp,
                    'verified'   => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Send OTP email
            Mail::raw("Your verification OTP is: $otp. It expires in 10 minutes.", function ($message) use ($professor) {
                $message->to($professor->email)
                        ->subject('Email Verification OTP');
            });

            return response()->json([
                'message' => 'OTP sent to your email.',
                'email'   => $professor->email
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'response_code' => 422,
                'status'        => 'error',
                'message'       => 'Validation failed',
                'errors'        => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Registration failed',
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $professor = Professor::where('email', $credentials['email'])->first();

        if (!$professor || !Hash::check($credentials['password'], $professor->password)) {
            return response()->json([
                'response_code' => 401,
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        if (!$professor->email_verified_at) {
            return response()->json([
                'response_code' => 401,
                'status'        => 'error',
                'message'       => 'Email not verified',
            ], 401);
        }

        // Delete old tokens & create new token
        $professor->tokens()->delete();
        $token = $professor->createToken('admin-auth-token')->plainTextToken;

        return response()->json([
            'response_code' => 200,
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $token,
            'token_type' => 'Bearer',
            'user_info' => [
                'id' => $professor->id,
                'name' => $professor->name,
                'email' => $professor->email,
            ]
        ]);
    }

    public function changePasswordProffesor(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:admins,email',
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Professor::where('email', $request->email)->first();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
            'success' => true,
        ]);
    }
}
