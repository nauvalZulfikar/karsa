<?php

namespace Database\Seeders;

use App\Models\Master\JenisPekerjaan;
use Illuminate\Database\Seeder;

class JenisPekerjaanSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['urutan' => 1, 'nama' => 'DED (Detail Engineering Design)'],
            ['urutan' => 2, 'nama' => 'Perencanaan Teknis'],
            ['urutan' => 3, 'nama' => 'Survey Kondisi'],
            ['urutan' => 4, 'nama' => 'Kajian Teknis'],
            ['urutan' => 5, 'nama' => 'Review Desain'],
            ['urutan' => 6, 'nama' => 'Pengawasan'],
            ['urutan' => 7, 'nama' => 'Perencanaan Interior'],
            ['urutan' => 8, 'nama' => 'Perencanaan Landscape'],
        ];

        foreach ($rows as $row) {
            JenisPekerjaan::firstOrCreate(
                ['nama' => $row['nama']],
                ['urutan' => $row['urutan'], 'is_active' => true]
            );
        }
    }
}
