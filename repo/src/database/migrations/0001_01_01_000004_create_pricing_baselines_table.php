<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_area_id')->constrained('service_areas')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->decimal('hourly_rate', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['service_area_id', 'effective_from']);
            $table->index(['role_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_baselines');
    }
};
