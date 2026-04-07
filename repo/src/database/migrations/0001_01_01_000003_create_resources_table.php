<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('service_area_id')->constrained('service_areas')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->decimal('capacity_hours', 8, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['service_area_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
