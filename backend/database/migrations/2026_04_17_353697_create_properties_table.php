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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->onDelete('set null');
            $table->foreignId('city_id')->nullable()->constrained('cities')->onDelete('set null');
            $table->foreignId('neighborhood_id')->nullable()->constrained('neighborhoods')->onDelete('set null');
            $table->string('street')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['apartment', 'villa', 'land', 'chalet', 'commercial', 'parking']);
            $table->decimal('price', 10, 2);
            $table->decimal('area_m2', 8, 2)->nullable();
            $table->integer('rooms')->nullable();
            $table->enum('damage_status', ['intact', 'partial', 'renovated']);
            $table->boolean('has_water')->default(false);
            $table->boolean('has_electricity')->default(false);
            $table->boolean('is_ready')->default(false);
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->enum('availability', ['available', 'not_available', 'booked'])->default('not_available');
            $table->softDeletes();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
