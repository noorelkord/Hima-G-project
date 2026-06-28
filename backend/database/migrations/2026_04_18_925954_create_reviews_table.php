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
    Schema::create('reviews', function (Blueprint $table) {
        $table->id();
        $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
        $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('reviewee_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('property_id')->nullable()->constrained('properties')->onDelete('cascade');
        $table->integer('rating')->between(1, 5);
        $table->text('comment')->nullable();
        $table->enum('type', ['tenant_to_host', 'host_to_tenant']);
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
