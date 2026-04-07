<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jti', 64)->unique();              // JWT ID — unique token identifier
            $table->string('device_fingerprint', 255)->nullable();
            $table->string('ip_address', 45)->nullable();      // IPv4/IPv6
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');                    // absolute 7-day cap
            $table->timestamp('last_active_at');                // for 30-min inactivity
            $table->boolean('is_revoked')->default(false);
            $table->string('revoked_by')->nullable();           // 'user', 'admin', 'system'
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_revoked']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
