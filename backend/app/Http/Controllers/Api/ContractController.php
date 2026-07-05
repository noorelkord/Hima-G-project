<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ReviewWindow;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ContractController extends Controller
{

    // ─────────────────────────────────────────────
    // نفس منطق ContractPdfService
    // ─────────────────────────────────────────────
    private static function calcDurationAndTotal(string $startDate, string $endDate, float $price): array
    {
        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);
        $diff  = $start->diff($end);
        $years  = $diff->y;
        $months = $diff->m;
        $days   = $diff->d;
        $totalMonths = ($years * 12) + $months;
        $dailyRate   = $price / 30;
        $total       = round(($totalMonths * $price) + ($days * $dailyRate), 2);
        return [
            'duration_text' => self::formatDuration($years, $months, $days),
            'total'         => $total,
        ];
    }

    private static function formatDuration(int $years, int $months, int $days): string
    {
        $parts = [];
        if ($years > 0)  $parts[] = $years === 1  ? 'سنة'      : $years  . ' سنوات';
        if ($months > 0) $parts[] = $months === 1 ? 'شهر'      : $months . ' أشهر';
        if ($days > 0)   $parts[] = $days === 1   ? 'يوم واحد' : $days   . ' يوم';
        return empty($parts) ? 'يوم واحد' : implode(' و', $parts);
    }

    // List contracts based on role
    public function index(Request $request)
    {
        $user  = $request->user();
        $role  = $user->getRoleNames()->first();
        $query = Contract::with('property:id,title,type,governorate_id,city_id,neighborhood_id,street');

        if ($role === 'tenant') {
            $query->where('tenant_id', $user->id)
                ->with('host:id,first_name,last_name,phone');
        } elseif ($role === 'host') {
            $query->where('host_id', $user->id)
                ->with('tenant:id,first_name,last_name,phone');
        } elseif ($role === 'admin') {
            $query->with('tenant:id,first_name')
                ->with('host:id,first_name,last_name');
        }

        $contracts = $query->latest()->get();

        if ($role === 'admin') {
            $contracts->makeHidden(['price']);
        }

        // Add can_review flag for each contract
        if (in_array($role, ['tenant', 'host'])) {
            $contracts = $contracts->map(function ($contract) use ($user) {
                $window = ReviewWindow::where('contract_id', $contract->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'open')
                    ->exists();
                $contract->can_review = $window;
                return $contract;
            });
        }

        return response()->json($contracts);
    }

    // View a single contract
    public function show(Request $request, $id)
    {
        $user     = $request->user();
        $role     = $user->getRoleNames()->first();
        $contract = Contract::findOrFail($id);

        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $contract->load([
            'property:id,title,type,governorate_id,city_id,neighborhood_id,street',
            'property.governorate:id,name',
            'property.city:id,name',
            'property.neighborhood:id,name',
            'booking:id,start_date,end_date,status',
        ]);

        if ($role === 'admin') {
            $contract->load([
                'tenant:id,first_name',
                'host:id,first_name,last_name,phone,national_id',
            ]);
            $contract->makeHidden(['price']);
        } else {
            $contract->load([
                'tenant:id,first_name,last_name,phone',
                'host:id,first_name,last_name,phone',
            ]);
        }

        // Add can_review flag
        if (in_array($role, ['tenant', 'host'])) {
            $window = ReviewWindow::where('contract_id', $contract->id)
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->exists();
            $contract->can_review = $window;
        }

        // ✅ إضافة duration_text للعقد
        if ($contract->start_date && $contract->end_date) {
            $price = in_array($role, ['tenant', 'host']) ? (float) $contract->price : 0;
            $calc  = self::calcDurationAndTotal(
                (string) $contract->start_date,
                (string) $contract->end_date,
                $price
            );
            $contract->duration_text = $calc['duration_text'];
            if ($role !== 'admin') {
                $contract->total = $calc['total'];
            }
        }

        return response()->json($contract);
    }

    // Cancel a contract
    public function cancel(Request $request, $id)
    {
        $user     = $request->user();
        $role     = $user->getRoleNames()->first();
        $contract = Contract::findOrFail($id);

        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if ($contract->status !== 'active') {
            return response()->json([
                'message' => 'يمكن إلغاء العقود النشطة فقط.',
            ], 403);
        }

        $contract->update([
            'status'    => 'cancelled',
            'closed_at' => now(),
        ]);

        ReviewWindow::create([
            'contract_id'      => $contract->id,
            'user_id'          => $contract->tenant_id,
            'role'             => 'tenant',
            'reminders_sent'   => 1,
            'last_reminded_at' => now(),
        ]);

        ReviewWindow::create([
            'contract_id'      => $contract->id,
            'user_id'          => $contract->host_id,
            'role'             => 'host',
            'reminders_sent'   => 1,
            'last_reminded_at' => now(),
        ]);

        $contract->property->update(['availability' => 'available']);

        NotificationService::send(
            $contract->tenant_id,
            'تذكير بالتقييم',
            'انتهى عقدك لـ "' . $contract->property->title . '". يرجى ترك تقييمك.',
            'review_reminder',
            $contract->id
        );

        NotificationService::send(
            $contract->host_id,
            'تذكير بالتقييم',
            'انتهى عقدك لـ "' . $contract->property->title . '". يرجى تقييم المستأجر.',
            'review_reminder',
            $contract->id
        );

        if ($role === 'tenant') {
            NotificationService::send(
                $contract->host_id,
                'تم إلغاء العقد',
                'قام المستأجر بإلغاء العقد لـ "' . $contract->property->title . '".',
                'contract_cancelled',
                $contract->id
            );
        } elseif ($role === 'host') {
            NotificationService::send(
                $contract->tenant_id,
                'تم إلغاء العقد',
                'قام المضيف بإلغاء العقد لـ "' . $contract->property->title . '".',
                'contract_cancelled',
                $contract->id
            );
        }

        return response()->json([
            'message'  => 'تم إلغاء العقد بنجاح.',
            'contract' => $contract,
        ]);
    }

    // Download contract PDF
    public function downloadPdf(Request $request, $id)
    {
        $user     = $request->user();
        $role     = $user->getRoleNames()->first();
        $contract = Contract::findOrFail($id);

        if ($role === 'admin') {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }
        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if (!$contract->pdf_path) {
            return response()->json(['message' => 'لم يتم إنشاء ملف PDF بعد.'], 404);
        }

        $fullPath = storage_path('app/public/' . $contract->pdf_path);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'ملف PDF غير موجود.'], 404);
        }

        return response()->download($fullPath, 'contract_' . $contract->id . '.pdf');
    }

    // Get PDF URL
    public function getPdfUrl(Request $request, $id)
    {
        $user     = $request->user();
        $role     = $user->getRoleNames()->first();
        $contract = Contract::findOrFail($id);

        if ($role === 'admin') {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }
        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if (!$contract->pdf_path) {
            return response()->json(['message' => 'لم يتم إنشاء ملف PDF بعد.'], 404);
        }

        return response()->json([
            'pdf_url' => asset('storage/' . $contract->pdf_path),
        ]);
    }

    // Admin archives old inactive contracts
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $contract = Contract::findOrFail($id);

        if ($contract->status === 'active') {
            return response()->json([
                'message' => 'لا يمكن أرشفة عقد نشط.',
            ], 403);
        }

        $contract->delete();

        return response()->json([
            'message' => 'تم أرشفة العقد بنجاح.',
        ]);
    }
}
