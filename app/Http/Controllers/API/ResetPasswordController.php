<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\User;
use App\Models\Professor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    protected function findUserOrProfessor($email)
    {
        $user = User::where('email', $email)->first();
        if ($user) return ['type' => 'user', 'model' => $user];

        $prof = Professor::where('email', $email)->first();
        if ($prof) return ['type' => 'professor', 'model' => $prof];

        return null;
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $account = $this->findUserOrProfessor($request->email);
        if (!$account) {
            return response()->json(['message' => 'Email not found in our records.'], 404);
        }

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Create or update the OTP record
        PasswordReset::updateOrCreate(
            ['email' => $request->email],
            [
                'code' => $otp,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Send OTP email
        $userEmail = $request->email;
        Mail::raw("Your password reset OTP is: $otp. It expires in 10 minutes.", function ($message) use ($userEmail) {
            $message->to($userEmail)
                ->subject('Email Verification OTP');
        });

        return response()->json(['message' => 'OTP sent to your email.', 'email' => $request->email]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
        ]);

        $record = PasswordReset::where('email', $request->email)->first();

        if (!$record || trim((string)$record->code) !== trim((string)$request->otp)) {
            return response()->json(['message' => 'Invalid OTP or email.'], 400);
        }

        // Check if OTP is expired (10 minutes)
        if ($record->updated_at->diffInMinutes(now()) > 10) {
            return response()->json(['message' => 'OTP has expired.'], 400);
        }

        $token = Str::random(60);
        $hash = bcrypt($token);
        PasswordReset::updateOrCreate(
            ['email' => $request->email],
            ['token' => $hash]
        );

        return response()->json(['message' => 'OTP verified successfully.', 'token' => $token]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'email' => 'required|email',
        ]);

        $value = PasswordReset::where('email', $request->email)->first();
        if (!$value) {
            return response()->json(['message' => 'Invalid email.'], 400);
        }

        if (!Hash::check($request->token, $value->token)) {
            return response()->json(['message' => 'Invalid token.'], 400);
        }

        // Find user or professor
        $account = $this->findUserOrProfessor($request->email);
        if (!$account) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        // Update password
        $account['model']->update([
            'password' => bcrypt($request->password),
        ]);

        return response()->json([
            'message' => 'Password reset successfully for ' . $account['type'] . '.'
        ]);
    }
}