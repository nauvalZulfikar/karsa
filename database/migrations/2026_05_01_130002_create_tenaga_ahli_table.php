<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenaga_ahli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perusahaan_id')->nullable()->constrained('perusahaan')->nullOnDelete();
            $table->string('nama', 150);
            $table->string('nik', 20)->nullable()->unique();
            $table->string('npwp', 30)->nullable();
            $table->string('jabatan_keahlian', 100)->nullable();
            $table->string('sertifikasi', 200)->nullable();
            $table->text('alamat')->nullable();
            $table->string('email', 100)->nullable();
            $table->string('no_telp', 20)->nullable();
            $table->string('foto_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenaga_ahli');
    }
};
