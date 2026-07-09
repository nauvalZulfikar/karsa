<?php

use App\Models\Dokumen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_dokumen', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('color')->default('gray');
            $table->integer('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed dari default di model (sudah termasuk Notulensi Rapat).
        $now = now();
        $urutan = 0;
        $rows = [];
        foreach (Dokumen::$tipeOptions as $key => $label) {
            $rows[] = [
                'key'        => $key,
                'label'      => $label,
                'color'      => Dokumen::$tipeColors[$key] ?? 'gray',
                'urutan'     => $urutan++,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('jenis_dokumen')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_dokumen');
    }
};
