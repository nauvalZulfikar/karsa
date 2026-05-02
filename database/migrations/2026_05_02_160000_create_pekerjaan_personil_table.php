<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pekerjaan_personil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pekerjaan_id')->constrained('pekerjaan')->cascadeOnDelete();
            $table->foreignId('tenaga_ahli_id')->constrained('tenaga_ahli')->restrictOnDelete();
            $table->string('jabatan_kontrak', 150);
            $table->decimal('nilai_honor_kontrak', 15, 2)->nullable();
            $table->date('tanggal_mulai_tugas')->nullable();
            $table->date('tanggal_akhir_tugas')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['pekerjaan_id', 'tenaga_ahli_id'], 'unique_pekerjaan_tenaga_ahli');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pekerjaan_personil');
    }
};
