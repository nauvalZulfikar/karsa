<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('termin_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pekerjaan_id')->constrained('pekerjaan')->cascadeOnDelete();
            $table->unsignedTinyInteger('nomor_termin');
            $table->string('nama_termin', 100);
            $table->decimal('nilai_termin', 15, 2);
            $table->decimal('persen_progres_syarat', 5, 2)->default(0);
            $table->date('tanggal_pengajuan')->nullable();
            $table->date('tanggal_persetujuan')->nullable();
            $table->date('tanggal_bayar')->nullable();
            $table->enum('status', ['draft', 'diajukan', 'disetujui', 'dibayar', 'ditolak'])->default('draft');
            $table->text('catatan_pptk')->nullable();
            $table->text('catatan_ppk')->nullable();
            $table->string('dokumen_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pekerjaan_id', 'nomor_termin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termin_pembayaran');
    }
};
