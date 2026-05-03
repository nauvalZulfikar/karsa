<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('realisasi_pengadaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rencana_pengadaan_id')->constrained('rencana_pengadaan')->cascadeOnDelete();
            $table->foreignId('pekerjaan_id')->constrained('pekerjaan')->cascadeOnDelete();
            $table->foreignId('perusahaan_id')->constrained('perusahaan')->cascadeOnDelete();
            $table->date('tanggal_realisasi');
            $table->decimal('volume_beli', 15, 3);
            $table->decimal('harga_aktual', 15, 2);
            $table->decimal('volume_dipakai', 15, 3);
            $table->decimal('volume_sisa', 15, 3)->default(0);
            $table->string('foto_invoice_path')->nullable();
            $table->string('foto_material_path')->nullable();
            $table->text('catatan_vendor')->nullable();
            $table->enum('status', ['submitted', 'verified', 'rejected'])->default('submitted');
            $table->text('catatan_pptk')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_pengadaan');
    }
};
