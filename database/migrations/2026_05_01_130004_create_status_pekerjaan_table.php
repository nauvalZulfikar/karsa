<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pekerjaan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->string('kode', 30)->unique();
            $table->string('warna', 30)->default('gray');
            $table->integer('urutan')->default(0);
            $table->string('keterangan', 200)->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_pekerjaan');
    }
};
