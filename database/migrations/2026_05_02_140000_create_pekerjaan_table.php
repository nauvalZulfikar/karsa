<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pekerjaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidang_id')->constrained('bidang')->restrictOnDelete();
            $table->foreignId('jenis_pekerjaan_id')->nullable()->constrained('jenis_pekerjaan')->nullOnDelete();
            $table->foreignId('perusahaan_id')->nullable()->constrained('perusahaan')->nullOnDelete();
            $table->foreignId('status_pekerjaan_id')->nullable()->constrained('status_pekerjaan')->nullOnDelete();
            $table->year('tahun_anggaran')->default(2026);
            $table->string('nama_pekerjaan', 300);
            $table->decimal('nilai_pagu', 15, 2)->nullable();
            $table->decimal('nilai_kontrak', 15, 2)->nullable();
            $table->string('no_spk', 150)->nullable();
            $table->date('tanggal_spk')->nullable();
            $table->string('no_spmk', 150)->nullable();
            $table->date('tanggal_spmk')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->unsignedSmallInteger('hari_kerja')->nullable();
            $table->enum('satuan_waktu', ['hari_kerja', 'hari_kalender'])->default('hari_kerja');
            $table->unsignedTinyInteger('progres_persen')->default(0);
            $table->text('catatan')->nullable();
            $table->string('kickoff_dokumen_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['no_spk', 'bidang_id', 'tahun_anggaran'], 'unique_spk_bidang_tahun');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pekerjaan');
    }
};
