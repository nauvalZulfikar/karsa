<?php

namespace Database\Seeders;

use App\Models\Master\Bidang;
use Illuminate\Database\Seeder;

class BidangSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['kode' => 'BG', 'nama' => 'Bangunan Gedung'],
            ['kode' => 'JL', 'nama' => 'Jalan'],
            ['kode' => 'DR', 'nama' => 'Drainase'],
            ['kode' => 'IR', 'nama' => 'Irigasi'],
            ['kode' => 'UM', 'nama' => 'UMPEG'],
            ['kode' => 'TR', 'nama' => 'TARU'],
            ['kode' => 'JK', 'nama' => 'JAKON'],
        ];

        foreach ($rows as $row) {
            Bidang::firstOrCreate(
                ['kode' => $row['kode']],
                ['nama' => $row['nama'], 'is_active' => true]
            );
        }
    }
}
