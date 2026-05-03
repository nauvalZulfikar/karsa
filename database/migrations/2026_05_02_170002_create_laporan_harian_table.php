<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pekerjaan_id')->constrained('pekerjaan')->cascadeOnDelete();
            $table->foreignId('perusahaan_id')->constrained('perusahaan')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('jenis', ['masuk', 'pulang']);
            $table->string('foto_original_path')->nullable();
            $table->string('foto_stamped_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('catatan')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('alasan_rejected')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->date('tanggal_laporan');
            $table->timestamps();
            $table->unique(['pekerjaan_id', 'perusahaan_id', 'user_id', 'jenis', 'tanggal_laporan'], 'unique_laporan_per_hari');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_harian');
    }
};
