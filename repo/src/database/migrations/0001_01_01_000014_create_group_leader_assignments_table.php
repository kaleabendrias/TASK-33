<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_leader_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_area_id')->constrained('service_areas')->cascadeOnDelete();
            $table->string('location', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'service_area_id']);
            $table->index('service_area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_leader_assignments');
    }
};
