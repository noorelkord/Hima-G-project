<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class PasswordResetController extends Controller {
    // Send reset link to email
    public function sendLink(Request $request) {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);
        $status = Password::sendResetLink(
            $request->only('email')
        );
        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.',
            ]);
        }
        return response()->json([
            'message' => 'تعذر إرسال رابط إعادة التعيين.',
        ], 400);
    }
    // Reset password using token
    public function reset(Request $request) {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
        ]);
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );
        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح.',
            ]);
        }
        return response()->json([
            'message' => 'رمز غير صالح أو منتهي الصلاحية.',
        ], 400);
    }
}
