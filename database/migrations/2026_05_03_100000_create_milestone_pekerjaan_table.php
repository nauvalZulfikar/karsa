<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('milestone_pekerjaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pekerjaan_id')->constrained('pekerjaan')->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->string('nama', 200);
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_target');
            $table->date('tanggal_selesai_aktual')->nullable();
            $table->decimal('progres_target_persen', 5, 2)->default(0);
            $table->enum('status', ['belum_mulai', 'sedang_berjalan', 'selesai', 'terlambat'])->default('belum_mulai');
            $table->enum('sumber', ['kontrak', 'generated_ai', 'manual'])->default('manual');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['pekerjaan_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_pekerjaan');
    }
};
