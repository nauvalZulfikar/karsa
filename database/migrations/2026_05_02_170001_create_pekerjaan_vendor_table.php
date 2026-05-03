<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pekerjaan_vendor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pekerjaan_id')->constrained('pekerjaan')->cascadeOnDelete();
            $table->foreignId('perusahaan_id')->constrained('perusahaan')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pekerjaan_id', 'perusahaan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pekerjaan_vendor');
    }
};
