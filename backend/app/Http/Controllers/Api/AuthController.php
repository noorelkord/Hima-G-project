<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'password'   => $data['password'],
        ]);

        $user->assignRole($data['role']);
        event(new Registered($user));

        return response()->json([
            'message' => 'تم إنشاء الحساب. يرجى تفعيل بريدك الإلكتروني.',
        ], 201);
    }

    public function login(Request $request)  {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);
        if (!Auth::guard('web')->attempt($credentials)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
        }
        
        $user = Auth::guard('web')->user();

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
