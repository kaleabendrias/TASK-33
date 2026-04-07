<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('bookable_item_id')->constrained('bookable_items')->cascadeOnDelete();
            $table->date('booking_date');
            $table->time('start_time')->nullable();      // null for consumables / full-day
            $table->time('end_time')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('line_tax', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index(['bookable_item_id', 'booking_date']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_line_items');
    }
};
