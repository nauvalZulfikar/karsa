<?php

namespace Database\Seeders;

use App\Models\Master\Perusahaan;
use Illuminate\Database\Seeder;

class PerusahaanSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['nama' => 'PT. ITERGO BUANA UTAMA', 'jenis' => 'PT'],
            ['nama' => 'PT. PURNA WAHANA LESTARI KONSULTAN', 'jenis' => 'PT'],
            ['nama' => 'CV. TACIBA SHIGOTO NUSANTARA', 'jenis' => 'CV'],
            ['nama' => 'PT. MARTHA TRIA SELARAS', 'jenis' => 'PT'],
            ['nama' => 'PT. GANESA INOVASI', 'jenis' => 'PT'],
            ['nama' => 'PT. ADHI CITRABHUMI UTAMA', 'jenis' => 'PT'],
            ['nama' => 'PT. JASAPLAN REKANAN UTAMA', 'jenis' => 'PT'],
            ['nama' => 'PT. HERANDAS ENGINEER SYSTEM', 'jenis' => 'PT'],
            ['nama' => 'PT. BINARTHAMA KHARISMA', 'jenis' => 'PT'],
            ['nama' => 'PT. MULTI PRANATA MANDIRI NUSANTARA', 'jenis' => 'PT'],
            ['nama' => 'PT. GANESA PRATAMA CONSULTAN', 'jenis' => 'PT'],
            ['nama' => 'PT. DWINA KARYA UTAMA', 'jenis' => 'PT'],
            ['nama' => 'PT. PRIMAREKA CIPTA MANDIRI', 'jenis' => 'PT'],
            ['nama' => 'PT. PURNA WAHANA LESTARI', 'jenis' => 'PT'],
            ['nama' => 'PT. ADHI CITRABHUMI', 'jenis' => 'PT'],
        ];

        foreach ($rows as $row) {
            Perusahaan::firstOrCreate(
                ['nama' => $row['nama']],
                ['jenis' => $row['jenis'], 'is_blacklisted' => false]
            );
        }
    }
}
