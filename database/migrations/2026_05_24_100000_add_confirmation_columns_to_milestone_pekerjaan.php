<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('milestone_pekerjaan', function (Blueprint $table) {
            $table->text('alasan_penolakan')->nullable()->after('catatan');
            $table->unsignedBigInteger('confirmed_by')->nullable()->after('alasan_penolakan');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');

            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('milestone_pekerjaan', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['alasan_penolakan', 'confirmed_by', 'confirmed_at']);
        });
    }
};
