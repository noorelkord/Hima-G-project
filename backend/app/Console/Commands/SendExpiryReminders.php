<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendExpiryReminders extends Command
{
    protected $signature   = 'contracts:send-expiry-reminders';
    protected $description = 'Send expiry reminders to tenants and hosts before contract end date';

    public function handle()
    {
        $contracts = Contract::where('status', 'active')
            ->whereDate('expiry_reminder_date', today())
            ->where('expiry_reminder_sent', false)
            ->get();

        $count = 0;

        foreach ($contracts as $contract) {

            // Notify tenant
            NotificationService::send(
                $contract->tenant_id,
                'تذكير بانتهاء العقد',
                'عقد الإيجار الخاص بك لـ "' . $contract->property->title . '" ينتهي قريباً في ' . $contract->end_date->format('Y-m-d') . '. يرجى اتخاذ الترتيبات اللازمة.',
                'contract_expiry_reminder',
                $contract->id
            );

            // Notify host
            NotificationService::send(
                $contract->host_id,
                'تذكير بانتهاء العقد',
                'عقد الإيجار لـ "' . $contract->property->title . '" ينتهي قريباً في ' . $contract->end_date->format('Y-m-d') . '. يرجى اتخاذ الترتيبات اللازمة.',
                'contract_expiry_reminder',
                $contract->id
            );

            // Mark reminder as sent
            $contract->update(['expiry_reminder_sent' => true]);

            $count++;
        }

        $this->info("Sent {$count} contract expiry reminders.");
    }
}