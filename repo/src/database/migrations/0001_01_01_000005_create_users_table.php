<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('password');                          // bcrypt hash
            $table->string('full_name', 255);
            $table->text('email_encrypted')->nullable();         // AES-256-CBC encrypted
            $table->text('phone_encrypted')->nullable();         // AES-256-CBC encrypted
            $table->string('email_hash', 64)->nullable()->index();  // SHA-256 for lookups
            $table->string('phone_hash', 64)->nullable()->index();  // SHA-256 for lookups
            $table->enum('role', ['user', 'staff', 'group-leader', 'admin'])->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamps();

            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
