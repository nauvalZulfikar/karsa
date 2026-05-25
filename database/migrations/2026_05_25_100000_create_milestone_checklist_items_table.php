<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('milestone_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_pekerjaan_id')
                ->constrained('milestone_pekerjaan')
                ->cascadeOnDelete();
            $table->string('tipe', 50); // kegiatan | deliverable (string, SQLite compatible)
            $table->string('nama', 500);
            $table->boolean('is_done_vendor')->default(false);
            $table->timestamp('vendor_done_at')->nullable();
            $table->boolean('is_done_admin')->default(false);
            $table->timestamp('admin_done_at')->nullable();
            $table->unsignedBigInteger('admin_done_by')->nullable();
            $table->timestamps();

            $table->foreign('admin_done_by')->references('id')->on('users')->nullOnDelete();
            $table->index('milestone_pekerjaan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_checklist_items');
    }
};
