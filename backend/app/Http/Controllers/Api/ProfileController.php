<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    // Complete the profile info for the first time 
    public function complete(Request $request) {
        $user = $request->user();
        // if the info is already completed 
        if ($user->isProfileComplete()) {
            return response()->json([
                'message' => 'الملف الشخصي مكتمل مسبقاً.',
            ], 200);
        }
        $data = $request->validate([
            'second_name' => 'required|string|max:50',
            'third_name'  => 'required|string|max:50',
            'last_name'   => 'required|string|max:50',
            'national_id' => ['required', 'string', 'regex:/^4[0-9]{8}$/', 'unique:users,national_id,' . $user->id],
            'phone'       => ['required', 'string', 'regex:/^\+(970|972)[0-9]{9}$/'],
        ], $this->validationMessages());
        $user->update($data);
        return response()->json([
            'message'             => 'تم إكمال الملف الشخصي بنجاح.',
            'user'                => $user,
            'is_profile_complete' => $user->isProfileComplete(),
        ]);
    }

    // update the profile
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name'  => 'sometimes|string|max:50',
            'second_name' => 'sometimes|string|max:50',
            'third_name'  => 'sometimes|string|max:50',
            'last_name'   => 'sometimes|string|max:50',
            'national_id' => ['sometimes', 'string', 'regex:/^4[0-9]{8}$/', 'unique:users,national_id,' . $user->id],
            'phone'       => ['sometimes', 'string', 'regex:/^\+(970|972)[0-9]{9}$/'],
        ], $this->validationMessages());

        $user->update($data);

        return response()->json([
            'message'             => 'تم تحديث الملف الشخصي بنجاح.',
            'user'                => $user,
            'is_profile_complete' => $user->isProfileComplete(),
        ]);
    }

    // change the password
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الحالية غير صحيحة.',
            ], 403);
        }

        $user->update(['password' => Hash::make($request->password)]);

        $user->tokens()->delete();

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح.']);
    }

    // change the email
    public function changeEmail(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'email'            => 'required|email|unique:users,email',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الحالية غير صحيحة.',
            ], 403);
        }

        $user->update([
            'email'             => $request->email,
            'email_verified_at' => null,
        ]);

        $user->sendEmailVerificationNotification();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'تم تحديث البريد الإلكتروني. يرجى تفعيل بريدك الجديد.',
        ]);
    }

    private function validationMessages(): array
    {
        return [
            'phone.regex'       => 'رقم الهاتف يجب أن يبدأ بـ +970 أو +972 ويتكون من 13 رقماً (مثال: +970599123456).',
            'national_id.regex' => 'رقم الهوية يجب أن يتكون من 9 أرقام ويبدأ بالرقم 4.',
            'password.min'      => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed'=> 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
