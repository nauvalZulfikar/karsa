<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perusahaan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 200);
            $table->string('singkatan', 50)->nullable();
            $table->enum('jenis', ['PT', 'CV', 'Perorangan', 'Lainnya']);
            $table->string('npwp', 30)->nullable()->unique();
            $table->text('alamat')->nullable();
            $table->string('no_telp', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('pic_nama', 100)->nullable();
            $table->string('pic_telp', 20)->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perusahaan');
    }
};
