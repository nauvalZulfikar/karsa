<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pekerjaan_id')->constrained('pekerjaan')->cascadeOnDelete();
            $table->enum('tipe', [
                'kak', 'kontrak', 'addendum', 'spmk', 'bast',
                'laporan_mingguan', 'laporan_akhir', 'foto_progress',
                'gambar_kerja', 'lainnya'
            ]);
            $table->string('nama_dokumen');
            $table->string('versi', 20)->default('1.0');
            $table->string('file_path');
            $table->string('file_original_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen');
    }
};
