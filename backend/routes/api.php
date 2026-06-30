<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\Host\PropertyImageController;
use App\Http\Controllers\Api\Tenant\FavoriteController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Host\PropertyController as HostPropertyController;
use App\Http\Controllers\Api\Host\BookingController as HostBookingController;
use App\Http\Controllers\Api\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Api\Tenant\BookingController as TenantBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

// =====================
// Public routes
// =====================
Route::get('/', function () {
    return response()->json([
        'name' => 'Hima API',
        'status' => 'running',
        'frontend_url' => config('app.frontend_url'),
        'public_endpoints' => [
            'properties' => url('/api/properties'),
            'governorates' => url('/api/governorates'),
            'login' => url('/api/login'),
            'register' => url('/api/register'),
        ],
    ]);
});

Route::post('/register',        [AuthController::class, 'register']);
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink']);
Route::post('/reset-password',  [PasswordResetController::class, 'reset']);

// Public property search
Route::get('/properties',              [PropertyController::class, 'index']);
Route::get('/properties/{id}',         [PropertyController::class, 'show']);
Route::get('/properties/{id}/whatsapp',[PropertyController::class, 'whatsappLink']);
Route::get('/properties/{id}/reviews', [ReviewController::class, 'propertyReviews']);
Route::get('/users/{id}/reviews',      [ReviewController::class, 'userReviews']);

// Locations
Route::get('/governorates',                  [LocationController::class, 'governorates']);
Route::get('/governorates/{id}/cities',      [LocationController::class, 'cities']);
Route::get('/cities/{id}/neighborhoods',     [LocationController::class, 'neighborhoods']);

// Email verification
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {

    $user = User::find($id);

    if (!$user) {
        return redirect(
            rtrim(config('app.frontend_url'), '/') . '/tenant-login.html?error=user_not_found'
        );
    }

    $role     = $user->getRoleNames()->first();
    $loginPage = $role === 'host' ? 'host-login.html' : 'tenant-login.html';
    $base      = rtrim(config('app.frontend_url'), '/');

    if (!$request->hasValidRelativeSignature()) {
        return redirect("{$base}/{$loginPage}?error=invalid_link");
    }

    if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
        return redirect("{$base}/{$loginPage}?error=invalid_link");
    }

    if ($user->hasVerifiedEmail()) {
        return redirect("{$base}/{$loginPage}?verified=already");
    }

    $user->markEmailAsVerified();
    event(new Verified($user));

    return redirect("{$base}/{$loginPage}?verified=success");

})->name('verification.verify');

Route::post('/email/resend', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return response()->json(['message' => 'تم إعادة إرسال رابط التفعيل.']);
})->middleware(['auth:sanctum', 'throttle:6,1']);

Route::post('/email/resend-public', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'سيتم إرسال رابط التفعيل إذا كان الحساب موجوداً.']);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'البريد الإلكتروني مفعّل مسبقاً.'], 422);
    }

    $user->sendEmailVerificationNotification();

    return response()->json(['message' => 'تم إعادة إرسال رابط التفعيل.']);
})->middleware('throttle:6,1');

// =====================
// Protected routes
// =====================
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Profile management
    Route::post('/profile/complete',       [ProfileController::class, 'complete']);
    Route::put('/profile',                 [ProfileController::class, 'update']);
    Route::put('/profile/change-password', [ProfileController::class, 'changePassword']);
    Route::put('/profile/change-email',    [ProfileController::class, 'changeEmail']);

    // Contracts
    Route::get('/contracts',               [ContractController::class, 'index']);
    Route::get('/contracts/{id}',          [ContractController::class, 'show']);
    Route::patch('/contracts/{id}/cancel', [ContractController::class, 'cancel']);
    Route::delete('/contracts/{id}',       [ContractController::class, 'destroy']);
    Route::get('/contracts/{id}/pdf',      [ContractController::class, 'getPdfUrl']);
    Route::get('/contracts/{id}/download', [ContractController::class, 'downloadPdf']);

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Notifications
    Route::get('/notifications',                 [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',    [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{id}/read',     [NotificationController::class, 'markAsRead']);

    // =====================
    // Host routes
    // =====================
    Route::middleware('role:host')->prefix('host')->group(function () {
        // Properties
        Route::get('/properties',                             [HostPropertyController::class, 'index']);
        Route::post('/properties',                            [HostPropertyController::class, 'store']);
        Route::get('/properties/{id}',                        [HostPropertyController::class, 'show']);
        Route::put('/properties/{id}',                        [HostPropertyController::class, 'update']);
        Route::delete('/properties/{id}',                     [HostPropertyController::class, 'destroy']);
        Route::patch('/properties/{id}/availability',         [HostPropertyController::class, 'toggleAvailability']);

        // Property Images
        Route::get('/properties/{propertyId}/images',                      [PropertyImageController::class, 'index']);
        Route::post('/properties/{propertyId}/images',                     [PropertyImageController::class, 'store']);
        Route::patch('/properties/{propertyId}/images/{imageId}/main',     [PropertyImageController::class, 'setMain']);
        Route::delete('/properties/{propertyId}/images/{imageId}',         [PropertyImageController::class, 'destroy']);

        // Bookings
        Route::get('/bookings',               [HostBookingController::class, 'index']);
        Route::patch('/bookings/{id}/accept', [HostBookingController::class, 'accept']);
        Route::patch('/bookings/{id}/reject', [HostBookingController::class, 'reject']);
    });

    // =====================
    // Admin routes
    // =====================
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Properties
        Route::get('/properties/pending',       [AdminPropertyController::class, 'pending']);
        Route::get('/properties',               [AdminPropertyController::class, 'index']);
        Route::patch('/properties/{id}/accept', [AdminPropertyController::class, 'accept']);
        Route::patch('/properties/{id}/reject', [AdminPropertyController::class, 'reject']);
        Route::delete('/properties/{id}',       [AdminPropertyController::class, 'destroy']);

        // Bookings
        Route::delete('/bookings/stale', [AdminBookingController::class, 'archiveStale']);
        Route::get('/bookings',          [AdminBookingController::class, 'index']);
        Route::get('/bookings/{id}',     [AdminBookingController::class, 'show']);
    });

    // =====================
    // Tenant routes
    // =====================
    Route::middleware('role:tenant')->prefix('tenant')->group(function () {
        // Bookings
        Route::get('/bookings',         [TenantBookingController::class, 'index']);
        Route::post('/bookings',        [TenantBookingController::class, 'store']);
        Route::get('/bookings/{id}',    [TenantBookingController::class, 'show']);
        Route::put('/bookings/{id}',    [TenantBookingController::class, 'update']);
        Route::delete('/bookings/{id}', [TenantBookingController::class, 'cancel']); 

        // Favorites
        Route::get('/favorites',                 [FavoriteController::class, 'index']);
        Route::post('/favorites',                [FavoriteController::class, 'store']);
        Route::delete('/favorites/{propertyId}', [FavoriteController::class, 'destroy']);
    });
});
