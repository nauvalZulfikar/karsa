<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_uploads', function (Blueprint $table) {
            $table->timestamp('organized_at')->nullable()->after('ocr_text');
            $table->foreignId('dokumen_id')->nullable()->after('organized_at')
                ->constrained('dokumen')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dokumen_id');
            $table->dropColumn('organized_at');
        });
    }
};
