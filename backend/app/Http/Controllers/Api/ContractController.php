<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\NotificationService;
use App\Models\ReviewWindow;
use Illuminate\Http\Request;

class ContractController extends Controller
{
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
        return response()->json($contracts);
    }

    // View a single contract
    public function show(Request $request, $id)
    {
        $user     = $request->user();
        $role     = $user->getRoleNames()->first();
        $contract = Contract::findOrFail($id);

        // Access control first ✅
        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Load data based on role
        $contract->load([
            'property:id,title,type,governorate_id,city_id,neighborhood_id,street',
            'booking:id,start_date,end_date,status',
        ]);

        if ($role === 'admin') {
            $contract->load([
                'tenant:id,first_name',
                'host:id,first_name,last_name,phone',
            ]);
            $contract->makeHidden(['price']);
        } else {
            $contract->load([
                'tenant:id,first_name,last_name,phone',
                'host:id,first_name,last_name,phone',
            ]);
        }

        return response()->json($contract);
    }

    // Cancel a contract
    public function cancel(Request $request, $id)
    {
        $user     = $request->user();
        $role     = $user->getRoleNames()->first();
        $contract = Contract::findOrFail($id);

        // Only tenant or host can cancel
        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($contract->status !== 'active') {
            return response()->json([
                'message' => 'Only active contracts can be cancelled.',
            ], 403);
        }

        // Cancel contract
        $contract->update([
            'status'    => 'cancelled',
            'closed_at' => now(),
        ]);

        // Create review windows for both parties
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

        // Free up the property
        $contract->property->update(['availability' => 'available']);
        // Send review reminder to both parties
        NotificationService::send(
            $contract->tenant_id,
            'Review Reminder',
            'Your contract for "' . $contract->property->title . '" has ended. Please take a moment to leave a review.',
            'review_reminder',
            $contract->id
        );

        NotificationService::send(
            $contract->host_id,
            'Review Reminder',
            'Your contract for "' . $contract->property->title . '" has ended. Please take a moment to review the tenant.',
            'review_reminder',
            $contract->id
        );
        // Notify the other party
        if ($role === 'tenant') {
            // Notify host
            NotificationService::send(
                $contract->host_id,
                'Contract Cancelled',
                'The tenant has cancelled the contract for "' . $contract->property->title . '".',
                'contract_cancelled',
                $contract->id
            );
        } elseif ($role === 'host') {
            // Notify tenant
            NotificationService::send(
                $contract->tenant_id,
                'Contract Cancelled',
                'The host has cancelled the contract for "' . $contract->property->title . '".',
                'contract_cancelled',
                $contract->id
            );
        }

        return response()->json([
            'message'  => 'Contract cancelled successfully.',
            'contract' => $contract,
        ]);
    }
    // Download contract PDF
    public function downloadPdf(Request $request, $id)
    {
        $user     = $request->user();
        $role     = $user->getRoleNames()->first();
        $contract = Contract::findOrFail($id);

        // Access control

        if ($role === 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!$contract->pdf_path) {
            return response()->json(['message' => 'PDF not generated yet.'], 404);
        }

        $fullPath = storage_path('app/public/' . $contract->pdf_path);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'PDF file not found.'], 404);
        }

        return response()->download($fullPath, 'contract_' . $contract->id . '.pdf');
    }

    // Get PDF URL
    public function getPdfUrl(Request $request, $id)
    {
        $user     = $request->user();
        $role     = $user->getRoleNames()->first();
        $contract = Contract::findOrFail($id);

        // Access control
        if ($role === 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!$contract->pdf_path) {
            return response()->json(['message' => 'PDF not generated yet.'], 404);
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
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $contract = Contract::findOrFail($id);

        if ($contract->status === 'active') {
            return response()->json([
                'message' => 'Cannot archive an active contract.',
            ], 403);
        }

        $contract->delete();

        return response()->json([
            'message' => 'Contract archived successfully.',
        ]);
    }
}
