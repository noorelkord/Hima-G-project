<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\NotificationService;
use App\Models\ReviewWindow;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireContracts extends Command
{
    protected $signature   = 'contracts:expire';
    protected $description = 'Automatically expire contracts that have passed their end date';

    public function handle()
    {
        $today = Carbon::today();

        $expiredContracts = Contract::where('status', 'active')
            ->where('end_date', '<', $today)
            ->get();

        foreach ($expiredContracts as $contract) {
            // Update contract status
            $contract->update([
                'status'    => 'expired',
                'closed_at' => now(),
            ]);

            // Free up the property
            $contract->property->update(['availability' => 'available']);

            // Notify tenant
            NotificationService::send(
                $contract->tenant_id,
                'انتهى العقد',
                'انتهى عقدك لـ «' . $contract->property->title . '».',
                'contract_expired',
                $contract->id
            );

            // Notify host
            NotificationService::send(
                $contract->host_id,
                'انتهى العقد',
                'انتهى العقد لـ «' . $contract->property->title . '».',
                'contract_expired',
                $contract->id
            );

            // Send review reminder to both parties
            NotificationService::send(
                $contract->tenant_id,
                'تذكير بالتقييم',
                'انتهى عقدك لـ «' . $contract->property->title . '». يرجى تقييم تجربتك.',
                'review_reminder',
                $contract->id
            );

            NotificationService::send(
                $contract->host_id,
                'تذكير بالتقييم',
                'انتهى عقدك لـ «' . $contract->property->title . '». يرجى تقييم المستأجر.',
                'review_reminder',
                $contract->id
            );

            // Create review windows for both parties ← أضف هنا
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
        }

        $this->info("Expired {$expiredContracts->count()} contracts.");
    }
}
