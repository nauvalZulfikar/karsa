<?php

namespace Database\Seeders;

use App\Models\Master\Bidang;
use App\Models\Master\Perusahaan;
use App\Models\Master\StatusPekerjaan;
use App\Models\Pekerjaan;
use Illuminate\Database\Seeder;

class PekerjaanSeeder extends Seeder
{
    public function run(): void
    {
        $bgId = Bidang::where('kode', 'BG')->first()->id;
        $jlId = Bidang::where('kode', 'JL')->first()->id;
        $drId = Bidang::where('kode', 'DR')->first()->id;

        $statusSelesai = StatusPekerjaan::where('kode', 'selesai')->first()->id;
        $statusProses = StatusPekerjaan::where('kode', 'proses_desain')->first()->id;
        $statusReview = StatusPekerjaan::where('kode', 'review_internal')->first()->id;

        $itergo = Perusahaan::where('nama', 'LIKE', '%ITERGO%')->first()?->id;
        $purnaKonsultan = Perusahaan::where('nama', 'LIKE', '%PURNA WAHANA LESTARI KONSULTAN%')->first()?->id;
        $purna = Perusahaan::where('nama', 'LIKE', '%PURNA WAHANA%')->whereNot('nama', 'LIKE', '%KONSULTAN%')->first()?->id;
        $adhi = Perusahaan::where('nama', 'LIKE', '%ADHI CITRABHUMI%')->first()?->id;

        $rows = [
            [
                'bidang_id' => $bgId,
                'nama_pekerjaan' => 'Kajian Geoteknik Stabilitas Tanah',
                'perusahaan_id' => $itergo,
                'nilai_pagu' => 99594750,
                'nilai_kontrak' => 99594750,
                'no_spk' => '602.1/10/SPK/Kajian.Geoteknik.Tanah/BG-DPUTR/2026',
                'no_spmk' => '602.1/10/SPMK/Kajian.Geoteknik.Tanah/BG-DPUTR/2026',
                'tanggal_spk' => '2026-01-05',
                'tanggal_spmk' => '2026-01-05',
                'tanggal_mulai' => '2026-01-05',
                'tanggal_akhir' => '2026-02-03',
                'hari_kerja' => 30,
                'status_pekerjaan_id' => $statusSelesai,
                'progres_persen' => 100,
                'tahun_anggaran' => 2026,
            ],
            [
                'bidang_id' => $bgId,
                'nama_pekerjaan' => 'Kajian Pemetaan Topografi',
                'perusahaan_id' => $purnaKonsultan,
                'nilai_pagu' => 99511500,
                'nilai_kontrak' => null,
                'no_spk' => '602.1/09/SPK/Kajian.Topografi/BG-DPUTR/2026',
                'no_spmk' => null,
                'tanggal_spk' => '2026-01-05',
                'tanggal_spmk' => null,
                'tanggal_mulai' => '2026-01-05',
                'tanggal_akhir' => '2026-02-03',
                'hari_kerja' => 30,
                'status_pekerjaan_id' => $statusSelesai,
                'progres_persen' => 100,
                'tahun_anggaran' => 2026,
            ],
            [
                'bidang_id' => $jlId,
                'nama_pekerjaan' => 'DED Jalan Kabupaten Wilayah Ciwidey',
                'perusahaan_id' => $purna,
                'nilai_pagu' => 99567000,
                'nilai_kontrak' => null,
                'no_spk' => '602/3/SPK/PR.0029.03/DPUTR/2026',
                'no_spmk' => null,
                'tanggal_spk' => '2026-02-02',
                'tanggal_spmk' => null,
                'tanggal_mulai' => '2026-02-02',
                'tanggal_akhir' => '2026-03-18',
                'hari_kerja' => 45,
                'status_pekerjaan_id' => $statusProses,
                'progres_persen' => 60,
                'tahun_anggaran' => 2026,
            ],
            [
                'bidang_id' => $jlId,
                'nama_pekerjaan' => 'DED Jalan Kabupaten Wilayah Soreang',
                'perusahaan_id' => $adhi,
                'nilai_pagu' => 99567000,
                'nilai_kontrak' => null,
                'no_spk' => '602/2/SPK/PR.0029.02/DPUTR/2026',
                'no_spmk' => null,
                'tanggal_spk' => '2026-01-19',
                'tanggal_spmk' => null,
                'tanggal_mulai' => '2026-01-19',
                'tanggal_akhir' => '2026-03-04',
                'hari_kerja' => 45,
                'status_pekerjaan_id' => $statusSelesai,
                'progres_persen' => 100,
                'tahun_anggaran' => 2026,
            ],
            [
                'bidang_id' => $drId,
                'nama_pekerjaan' => 'Penyusunan Outline pada Kawasan Genangan Wilayah Dayeuhkolot',
                'perusahaan_id' => $purnaKonsultan,
                'nilai_pagu' => 99335000,
                'nilai_kontrak' => null,
                'no_spk' => null,
                'no_spmk' => null,
                'tanggal_spk' => null,
                'tanggal_spmk' => null,
                'tanggal_mulai' => '2026-01-26',
                'tanggal_akhir' => '2026-02-24',
                'hari_kerja' => 30,
                'status_pekerjaan_id' => $statusSelesai,
                'progres_persen' => 100,
                'tahun_anggaran' => 2026,
            ],
            [
                'bidang_id' => $drId,
                'nama_pekerjaan' => 'Perencanaan Teknik Pembangunan Drainase Perkotaan Wilayah 1',
                'perusahaan_id' => $adhi,
                'nilai_pagu' => 99612500,
                'nilai_kontrak' => null,
                'no_spk' => null,
                'no_spmk' => null,
                'tanggal_spk' => null,
                'tanggal_spmk' => null,
                'tanggal_mulai' => '2026-01-26',
                'tanggal_akhir' => '2026-02-24',
                'hari_kerja' => 30,
                'status_pekerjaan_id' => $statusReview,
                'progres_persen' => 80,
                'tahun_anggaran' => 2026,
            ],
        ];

        foreach ($rows as $row) {
            Pekerjaan::firstOrCreate(
                [
                    'nama_pekerjaan' => $row['nama_pekerjaan'],
                    'bidang_id' => $row['bidang_id'],
                    'tahun_anggaran' => $row['tahun_anggaran'],
                ],
                $row
            );
        }
    }
}
