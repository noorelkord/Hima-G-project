<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Contract Expiry Reminder Settings
    |--------------------------------------------------------------------------
    |
    | long_duration_days:    الحد الفاصل بين العقد الطويل والمتوسط بالأيام
    | medium_duration_days:  الحد الفاصل بين العقد المتوسط والقصير بالأيام
    | long_reminder_days:    كم يوماً قبل الانتهاء نذكّر في العقد الطويل
    | medium_reminder_days:  كم يوماً قبل الانتهاء نذكّر في العقد المتوسط
    |
    | مثال بالقيم الافتراضية:
    | عقد > 60 يوم  → تذكير قبل 30 يوم
    | عقد 30-60 يوم → تذكير قبل 15 يوم
    | عقد < 30 يوم  → لا تذكير
    |
    */

    'long_duration_days'   => env('CONTRACT_LONG_DURATION_DAYS', 60),
    'medium_duration_days' => env('CONTRACT_MEDIUM_DURATION_DAYS', 30),
    'long_reminder_days'   => env('CONTRACT_LONG_REMINDER_DAYS', 30),
    'medium_reminder_days' => env('CONTRACT_MEDIUM_REMINDER_DAYS', 15),
];