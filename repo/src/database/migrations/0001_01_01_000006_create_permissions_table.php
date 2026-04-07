<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Feature-level permissions (button-level granularity)
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();        // e.g. "resources.create", "pricing.approve"
            $table->string('description', 500)->nullable();
            $table->string('group', 100)->nullable();      // UI grouping: "resources", "pricing", etc.
            $table->timestamps();
        });

        // Pivot: which roles hold which permissions
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['user', 'staff', 'group-leader', 'admin']);
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role', 'permission_id']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
