<?php

namespace Database\Seeders;

use App\Models\Master\StatusPekerjaan;
use Illuminate\Database\Seeder;

class StatusPekerjaanSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['urutan' => 1, 'kode' => 'belum_mulai', 'nama' => 'Belum Mulai', 'warna' => 'gray', 'is_final' => false],
            ['urutan' => 2, 'kode' => 'proses_desain', 'nama' => 'Proses Desain', 'warna' => 'info', 'is_final' => false],
            ['urutan' => 3, 'kode' => 'review_internal', 'nama' => 'Review Internal', 'warna' => 'warning', 'is_final' => false],
            ['urutan' => 4, 'kode' => 'selesai', 'nama' => 'Selesai', 'warna' => 'success', 'is_final' => true],
            ['urutan' => 5, 'kode' => 'terlambat', 'nama' => 'Terlambat', 'warna' => 'danger', 'is_final' => false],
        ];

        foreach ($rows as $row) {
            StatusPekerjaan::firstOrCreate(
                ['kode' => $row['kode']],
                [
                    'nama' => $row['nama'],
                    'warna' => $row['warna'],
                    'urutan' => $row['urutan'],
                    'is_final' => $row['is_final'],
                ]
            );
        }
    }
}
