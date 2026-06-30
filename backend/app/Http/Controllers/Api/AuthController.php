<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:50',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'role'       => 'required|in:tenant,host',
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);
        event(new Registered($user));

        $verificationPath = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
            false
        );

        return response()->json([
            'message' => 'تم إنشاء الحساب. يرجى تفعيل بريدك الإلكتروني.',
            'verification_url' => rtrim(config('app.url'), '/') . $verificationPath,
        ], 201);
    }

    public function login(Request $request)  {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
        }

        $passwordMatches = Hash::check($credentials['password'], $user->password);

        if (!$passwordMatches && hash_equals((string) $user->password, $credentials['password'])) {
            $user->forceFill([
                'password' => Hash::make($credentials['password']),
            ])->save();
            $passwordMatches = true;
        }

        if (!$passwordMatches) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json(['message' => 'يرجى تفعيل بريدك الإلكتروني أولاً.'], 403);
        }
        $user->tokens()->delete();
        $token = $user->createToken(
         'auth_token',
          ['*'],
          now()->addDays(30)
          )->plainTextToken;
        
        $role  = $user->getRoleNames()->first();
        return response()->json([
            'token'               => $token,
            'role'                => $role,
            'user'                => $user,
            'is_profile_complete' => $user->isProfileComplete(),
        ]);
    }

    public function adminLogin(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
        }

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'غير مصرح لك بالدخول.'], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json(['message' => 'يرجى تفعيل بريدك الإلكتروني أولاً.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken(
            'admin_auth_token',
            ['*'],
            now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'token'               => $token,
            'role'                => 'admin',
            'user'                => $user,
            'is_profile_complete' => $user->isProfileComplete(),
        ]);
    }

    public function logout(Request $request)
    {
       
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user'                => $user,
            'role'                => $user->getRoleNames()->first(),
            'is_profile_complete' => $user->isProfileComplete(),
        ]);
    }
}
