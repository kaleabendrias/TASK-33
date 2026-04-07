<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_items', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['lab', 'room', 'workstation', 'equipment', 'consumable']);
            $table->string('name', 255);
            $table->string('location', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('service_area_id')->nullable()->constrained('service_areas')->nullOnDelete();
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);     // for consumables
            $table->integer('capacity')->default(1);               // concurrent slots
            $table->integer('stock')->nullable();                   // for consumables
            $table->decimal('tax_rate', 5, 4)->default(0.0000);    // e.g. 0.0800 = 8%
            $table->boolean('is_active')->default(true);
            $table->string('image_path', 500)->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('service_area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_items');
    }
};
