<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('bidang_id')->nullable()->after('id');
            $table->string('no_telp', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('no_telp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bidang_id', 'no_telp', 'is_active']);
        });
    }
};
