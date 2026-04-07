<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 100);           // polymorphic owner
            $table->unsignedBigInteger('attachable_id');
            $table->string('original_filename', 500);
            $table->string('stored_path', 1000);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256_fingerprint', 64);         // hex-encoded SHA-256
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('sha256_fingerprint');
            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
