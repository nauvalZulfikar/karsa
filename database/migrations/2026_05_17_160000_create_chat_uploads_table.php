<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('abs_path', 500);
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->boolean('is_scanned_pdf')->default(false);
            $table->text('ocr_text')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_uploads');
    }
};
