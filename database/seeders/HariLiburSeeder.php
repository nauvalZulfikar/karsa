<?php

namespace Database\Seeders;

use App\Models\Master\HariLibur;
use Illuminate\Database\Seeder;

class HariLiburSeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['tanggal' => '2026-01-01', 'nama' => 'Tahun Baru Masehi'],
            ['tanggal' => '2026-01-27', 'nama' => 'Isra Miraj Nabi Muhammad SAW'],
            ['tanggal' => '2026-01-28', 'nama' => 'Cuti Bersama Isra Miraj', 'is_cuti_bersama' => true],
            ['tanggal' => '2026-02-05', 'nama' => 'Tahun Baru Imlek'],
            ['tanggal' => '2026-03-19', 'nama' => 'Hari Suci Nyepi'],
            ['tanggal' => '2026-03-20', 'nama' => 'Cuti Bersama Nyepi', 'is_cuti_bersama' => true],
            ['tanggal' => '2026-04-03', 'nama' => 'Wafat Isa Almasih'],
            ['tanggal' => '2026-04-20', 'nama' => 'Hari Raya Idul Fitri'],
            ['tanggal' => '2026-04-21', 'nama' => 'Hari Raya Idul Fitri'],
            ['tanggal' => '2026-04-22', 'nama' => 'Cuti Bersama Idul Fitri', 'is_cuti_bersama' => true],
            ['tanggal' => '2026-04-23', 'nama' => 'Cuti Bersama Idul Fitri', 'is_cuti_bersama' => true],
            ['tanggal' => '2026-04-24', 'nama' => 'Cuti Bersama Idul Fitri', 'is_cuti_bersama' => true],
            ['tanggal' => '2026-05-01', 'nama' => 'Hari Buruh Internasional'],
            ['tanggal' => '2026-05-12', 'nama' => 'Kenaikan Isa Almasih'],
            ['tanggal' => '2026-05-13', 'nama' => 'Cuti Bersama Kenaikan Isa Almasih', 'is_cuti_bersama' => true],
            ['tanggal' => '2026-05-29', 'nama' => 'Hari Raya Waisak'],
            ['tanggal' => '2026-06-01', 'nama' => 'Hari Lahir Pancasila'],
            ['tanggal' => '2026-06-06', 'nama' => 'Hari Raya Idul Adha'],
            ['tanggal' => '2026-06-26', 'nama' => 'Tahun Baru Islam'],
            ['tanggal' => '2026-08-17', 'nama' => 'HUT Kemerdekaan Republik Indonesia'],
            ['tanggal' => '2026-09-05', 'nama' => 'Maulid Nabi Muhammad SAW'],
            ['tanggal' => '2026-12-25', 'nama' => 'Hari Raya Natal'],
            ['tanggal' => '2026-12-26', 'nama' => 'Cuti Bersama Natal', 'is_cuti_bersama' => true],
        ];

        foreach ($holidays as $holiday) {
            HariLibur::firstOrCreate(
                ['tanggal' => $holiday['tanggal']],
                [
                    'nama' => $holiday['nama'],
                    'is_cuti_bersama' => $holiday['is_cuti_bersama'] ?? false,
                ]
            );
        }
    }
}
