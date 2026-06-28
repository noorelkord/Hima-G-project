<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->date('expiry_reminder_date')->nullable()->after('closed_at');
            $table->boolean('expiry_reminder_sent')->default(false)->after('expiry_reminder_date');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['expiry_reminder_date', 'expiry_reminder_sent']);
        });
    }
};