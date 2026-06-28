<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Review;
use App\Models\ReviewWindow;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendReviewReminders extends Command
{
    protected $signature   = 'reviews:send-reminders';
    protected $description = 'Send review reminders to parties with open review windows';

    public function handle()
    {
        $reminderDays = config('reviews.reminder_days', 30);
        $maxReminders = config('reviews.max_reminders', 2);

        $windows = ReviewWindow::where('status', 'open')
            ->where(function ($q) use ($reminderDays) {
                $q->whereNull('last_reminded_at')
                  ->orWhere('last_reminded_at', '<=', now()->subDays($reminderDays));
            })
            ->with('contract.property')
            ->get();

        $count = 0;

        foreach ($windows as $window) {

            // تجنب إرسال تذكيرين في نفس اليوم
            if ($window->last_reminded_at?->isToday()) {
                continue;
            }

            // هل قيّم بالفعل؟
            $hasReviewed = Review::where('contract_id', $window->contract_id)
                ->where('reviewer_id', $window->user_id)
                ->exists();

            if ($hasReviewed) {
                $window->update(['status' => 'closed']);
                continue;
            }

            // هل انتهت مرات التذكير؟
            if ($window->reminders_sent >= $maxReminders) {
                $window->update(['status' => 'closed']);
                continue;
            }

            // أرسل التذكير
            NotificationService::send(
                $window->user_id,
                'تذكير بالتقييم',
                'لا يزال لديك تقييم معلق لـ "' . $window->contract->property->title . '". يرجى مشاركة تجربتك.',
                'review_reminder',
                $window->contract_id
            );

            // حدّث السجل
            $window->update([
                'reminders_sent'   => $window->reminders_sent + 1,
                'last_reminded_at' => now(),
            ]);

            $count++;
        }

        $this->info("Sent {$count} review reminders.");
    }
}