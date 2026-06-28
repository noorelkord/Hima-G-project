<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('bookings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
        $table->date('start_date');
        $table->date('end_date');
        $table->decimal('price', 10, 2);
        $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
        $table->softDeletes();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
