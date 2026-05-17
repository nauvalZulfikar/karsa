<?php

namespace Database\Seeders;

use App\Models\Dokumen;
use App\Models\LaporanHarian;
use App\Models\Master\Bidang;
use App\Models\Master\Perusahaan;
use App\Models\Master\StatusPekerjaan;
use App\Models\Master\TenagaAhli;
use App\Models\MilestonePekerjaan;
use App\Models\Pekerjaan;
use App\Models\PekerjaanPersonil;
use App\Models\PekerjaanVendor;
use App\Models\RealisasiPengadaan;
use App\Models\RencanaPengadaan;
use App\Models\TerminPembayaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * DemoSeeder — isolated demo dataset untuk training, screenshot, dan onboarding.
 *
 * Karakteristik:
 * - Test admin terpisah (demo@karta.test) tidak menyentuh admin@dputr.go.id production-real
 * - Semua tanggal relatif terhadap konstanta TODAY (default 2026-05-05)
 *   sehingga screenshot stabil; tidak bergantung now()/today()
 * - Cover semua role: super_admin, admin_bidang, pptk, ppk, viewer, vendor
 * - Cover semua fitur: Pekerjaan, Personil, Termin (5 status), Realisasi (3 status),
 *   LaporanHarian, Milestone, Dokumen
 *
 * Run: php artisan db:seed --class=DemoSeeder
 * Reset hanya demo data: lihat method clearDemoData() di bawah
 */
class DemoSeeder extends Seeder
{
    /** Konstanta tanggal "hari ini" untuk semua data demo. Stabil → screenshot tidak basi. */
    public const TODAY = '2026-05-05';

    /** Email prefix demo — semua user demo berakhir @karta.test */
    public const DEMO_DOMAIN = '@karta.test';

    public function run(): void
    {
        $this->command->info('🌱 DemoSeeder — TODAY = ' . self::TODAY);

        // Pastikan roles ada (idempotent)
        (new RoleAndPermissionSeeder())->run();

        // Pastikan master data ada (idempotent)
        (new BidangSeeder())->run();
        (new PerusahaanSeeder())->run();
        (new JenisPekerjaanSeeder())->run();
        (new StatusPekerjaanSeeder())->run();
        (new HariLiburSeeder())->run();

        $this->seedDemoUsers();
        $this->seedDemoTenagaAhli();
        $this->seedDemoPekerjaan();

        $this->command->info('✅ DemoSeeder selesai. Login info: ' . PHP_EOL .
            '   Super Admin : superadmin@karta.test / demo123' . PHP_EOL .
            '   Admin Bidang: adminbidang@karta.test / demo123' . PHP_EOL .
            '   PPTK        : pptk@karta.test / demo123' . PHP_EOL .
            '   PPK         : ppk@karta.test / demo123' . PHP_EOL .
            '   Viewer      : viewer@karta.test / demo123' . PHP_EOL .
            '   Vendor      : vendor@karta.test / demo123 (vendor portal)');
    }

    private function seedDemoUsers(): void
    {
        $bidangBg  = Bidang::where('kode', 'BG')->first();
        $vendorPt  = Perusahaan::where('nama', 'PT. ITERGO BUANA UTAMA')->first();

        $users = [
            ['superadmin', 'Demo Super Admin', 'super_admin', null, null],
            ['adminbidang', 'Demo Admin Bidang BG', 'admin_bidang', $bidangBg?->id, null],
            ['pptk', 'Demo PPTK', 'pptk', $bidangBg?->id, null],
            ['ppk', 'Demo PPK', 'ppk', $bidangBg?->id, null],
            ['viewer', 'Demo Viewer', 'viewer', null, null],
            ['vendor', 'Demo Vendor (Itergo)', 'vendor', null, $vendorPt?->id],
        ];

        foreach ($users as [$prefix, $name, $role, $bidangId, $perusahaanId]) {
            $user = User::firstOrCreate(
                ['email' => $prefix . self::DEMO_DOMAIN],
                [
                    'name'          => $name,
                    'password'      => bcrypt('demo123'),
                    'is_active'     => true,
                    'bidang_id'     => $bidangId,
                    'perusahaan_id' => $perusahaanId,
                ]
            );
            $user->syncRoles([$role]);
        }
    }

    private function seedDemoTenagaAhli(): void
    {
        $itergo = Perusahaan::where('nama', 'PT. ITERGO BUANA UTAMA')->first();
        $purna  = Perusahaan::where('nama', 'PT. PURNA WAHANA LESTARI KONSULTAN')->first();

        $personil = [
            [$itergo?->id, 'Ir. Budi Hartono, M.T.', '3273010101800001', 'Team Leader Geoteknik', 'SKA Geoteknik Utama'],
            [$itergo?->id, 'Eka Prasetya, S.T.',     '3273010101900002', 'Drafter CAD',           'SKT Juru Gambar'],
            [$purna?->id,  'Dr. Hendra Suryana',     '3273010101750003', 'Ahli Topografi',        'SKA Geodesi Madya'],
            [$purna?->id,  'Ratna Dewi, S.T., M.Sc.', '3273010101820004', 'Ahli Hidrologi',        'SKA SDA Madya'],
        ];

        foreach ($personil as [$perusahaanId, $nama, $nik, $jabatan, $sertif]) {
            if (! $perusahaanId) continue;
            TenagaAhli::firstOrCreate(
                ['nik' => $nik],
                [
                    'perusahaan_id'    => $perusahaanId,
                    'nama'             => $nama,
                    'jabatan_keahlian' => $jabatan,
                    'sertifikasi'      => $sertif,
                    'no_telp'          => '0812' . substr($nik, -8),
                    'is_active'        => true,
                ]
            );
        }
    }

    private function seedDemoPekerjaan(): void
    {
        $today = Carbon::parse(self::TODAY);

        $bidangBg = Bidang::where('kode', 'BG')->first();
        $bidangJl = Bidang::where('kode', 'JL')->first();
        $bidangDr = Bidang::where('kode', 'DR')->first();

        $statusProses  = StatusPekerjaan::where('kode', 'proses_desain')->first();
        $statusSelesai = StatusPekerjaan::where('kode', 'selesai')->first();
        $statusReview  = StatusPekerjaan::where('kode', 'review_internal')->first();

        $itergo = Perusahaan::where('nama', 'PT. ITERGO BUANA UTAMA')->first();
        $purna  = Perusahaan::where('nama', 'PT. PURNA WAHANA LESTARI KONSULTAN')->first();
        $adhi   = Perusahaan::where('nama', 'PT. ADHI CITRABHUMI UTAMA')->first();

        $superAdmin = User::where('email', 'superadmin' . self::DEMO_DOMAIN)->first();
        $pptk       = User::where('email', 'pptk' . self::DEMO_DOMAIN)->first();
        $ppk        = User::where('email', 'ppk' . self::DEMO_DOMAIN)->first();
        $vendor     = User::where('email', 'vendor' . self::DEMO_DOMAIN)->first();

        // ────────────────────────────────────────────────────────────
        // PROYEK 1 — DEMO: Aktif sehat (TODAY-30 → TODAY+30, 50% progres)
        //   Punya: 2 personil, 3 termin (draft/diajukan/disetujui),
        //          5 laporan harian, 2 milestone, 2 rencana+realisasi, 1 dokumen kontrak
        // ────────────────────────────────────────────────────────────
        $proyek1 = Pekerjaan::updateOrCreate(
            [
                'nama_pekerjaan' => '[DEMO] Pembangunan Gedung Posyandu Soreang',
                'bidang_id'      => $bidangBg?->id,
                'tahun_anggaran' => 2026,
            ],
            [
                'jenis_pekerjaan_id'  => null,
                'perusahaan_id'       => $itergo?->id,
                'status_pekerjaan_id' => $statusProses?->id,
                'nilai_pagu'          => 850_000_000,
                'nilai_kontrak'       => 798_500_000,
                'no_spk'              => 'DEMO/01/SPK/BG-DPUTR/2026',
                'no_spmk'             => 'DEMO/01/SPMK/BG-DPUTR/2026',
                'tanggal_spk'         => $today->copy()->subDays(30)->toDateString(),
                'tanggal_spmk'        => $today->copy()->subDays(30)->toDateString(),
                'tanggal_mulai'       => $today->copy()->subDays(30)->toDateString(),
                'tanggal_akhir'       => $today->copy()->addDays(30)->toDateString(),
                'hari_kerja'          => 60,
                'satuan_waktu'        => 'hari_kalender',
                'progres_persen'      => 50,
                'catatan'             => 'Demo proyek aktif sehat — 50% progres, deadline aman.',
                'created_by'          => $superAdmin?->id,
                'updated_by'          => $superAdmin?->id,
            ]
        );

        // Vendor relation
        if ($itergo) {
            PekerjaanVendor::firstOrCreate([
                'pekerjaan_id' => $proyek1->id,
                'perusahaan_id' => $itergo->id,
            ]);
        }

        // Personil
        $tlGeo = TenagaAhli::where('nik', '3273010101800001')->first();
        $cad   = TenagaAhli::where('nik', '3273010101900002')->first();
        if ($tlGeo) {
            PekerjaanPersonil::firstOrCreate(
                ['pekerjaan_id' => $proyek1->id, 'tenaga_ahli_id' => $tlGeo->id],
                [
                    'jabatan_kontrak'      => 'Team Leader',
                    'nilai_honor_kontrak'  => 18_000_000,
                    'tanggal_mulai_tugas'  => $today->copy()->subDays(30)->toDateString(),
                    'tanggal_akhir_tugas'  => $today->copy()->addDays(30)->toDateString(),
                    'is_active'            => true,
                ]
            );
        }
        if ($cad) {
            PekerjaanPersonil::firstOrCreate(
                ['pekerjaan_id' => $proyek1->id, 'tenaga_ahli_id' => $cad->id],
                [
                    'jabatan_kontrak'      => 'Drafter',
                    'nilai_honor_kontrak'  => 9_000_000,
                    'tanggal_mulai_tugas'  => $today->copy()->subDays(30)->toDateString(),
                    'tanggal_akhir_tugas'  => $today->copy()->addDays(30)->toDateString(),
                    'is_active'            => true,
                ]
            );
        }

        // 3 Termin: status berbeda untuk demo workflow
        TerminPembayaran::updateOrCreate(
            ['pekerjaan_id' => $proyek1->id, 'nomor_termin' => 1],
            [
                'nama_termin'           => 'Termin I — Mobilisasi 30%',
                'nilai_termin'          => 239_550_000,
                'persen_progres_syarat' => 30,
                'tanggal_pengajuan'     => $today->copy()->subDays(20)->toDateString(),
                'tanggal_persetujuan'   => $today->copy()->subDays(18)->toDateString(),
                'tanggal_bayar'         => $today->copy()->subDays(15)->toDateString(),
                'status'                => 'dibayar',
                'catatan_pptk'          => 'Mobilisasi sesuai rencana.',
                'catatan_ppk'           => 'Disetujui — bukti mobilisasi lengkap.',
                'approved_by'           => $ppk?->id,
                'created_by'            => $pptk?->id,
            ]
        );
        TerminPembayaran::updateOrCreate(
            ['pekerjaan_id' => $proyek1->id, 'nomor_termin' => 2],
            [
                'nama_termin'           => 'Termin II — Pengecoran 50%',
                'nilai_termin'          => 159_700_000,
                'persen_progres_syarat' => 50,
                'tanggal_pengajuan'     => $today->copy()->subDays(3)->toDateString(),
                'tanggal_persetujuan'   => null,
                'tanggal_bayar'         => null,
                'status'                => 'diajukan',
                'catatan_pptk'          => 'Progres pengecoran sudah 50%, foto terlampir.',
                'catatan_ppk'           => null,
                'approved_by'           => null,
                'created_by'            => $pptk?->id,
            ]
        );
        TerminPembayaran::updateOrCreate(
            ['pekerjaan_id' => $proyek1->id, 'nomor_termin' => 3],
            [
                'nama_termin'           => 'Termin III — Finishing 95%',
                'nilai_termin'          => 319_400_000,
                'persen_progres_syarat' => 95,
                'tanggal_pengajuan'     => null,
                'tanggal_persetujuan'   => null,
                'tanggal_bayar'         => null,
                'status'                => 'draft',
                'catatan_pptk'          => null,
                'catatan_ppk'           => null,
                'approved_by'           => null,
                'created_by'            => $pptk?->id,
            ]
        );

        // 5 Laporan Harian (5 hari terakhir)
        for ($i = 1; $i <= 5; $i++) {
            $tgl = $today->copy()->subDays($i);
            LaporanHarian::updateOrCreate(
                [
                    'pekerjaan_id'    => $proyek1->id,
                    'tanggal_laporan' => $tgl->toDateString(),
                    'jenis'           => 'masuk',
                ],
                [
                    'perusahaan_id' => $itergo?->id,
                    'user_id'       => $vendor?->id,
                    'latitude'      => -6.9890 + ($i * 0.0001),
                    'longitude'     => 107.5260 + ($i * 0.0001),
                    'catatan'       => "Hari ke-{$i} retro: pengecoran kolom lt-2, cuaca cerah.",
                    'status'        => 'approved',
                    'submitted_at'  => $tgl->copy()->setTime(8, 30)->toDateTimeString(),
                ]
            );
        }

        // 2 Milestone
        MilestonePekerjaan::updateOrCreate(
            ['pekerjaan_id' => $proyek1->id, 'urutan' => 1],
            [
                'nama'                   => 'Persiapan & Mobilisasi',
                'deskripsi'              => 'Site clearing, pemasangan pagar, mobilisasi alat',
                'tanggal_target'         => $today->copy()->subDays(20)->toDateString(),
                'tanggal_selesai_aktual' => $today->copy()->subDays(18)->toDateString(),
                'progres_target_persen'  => 30,
                'status'                 => 'selesai',
                'sumber'                 => 'kontrak',
                'catatan'                => 'Selesai 2 hari lebih cepat.',
            ]
        );
        MilestonePekerjaan::updateOrCreate(
            ['pekerjaan_id' => $proyek1->id, 'urutan' => 2],
            [
                'nama'                   => 'Pengecoran Struktur Atas',
                'deskripsi'              => 'Cor kolom & balok lantai 1-2',
                'tanggal_target'         => $today->copy()->addDays(10)->toDateString(),
                'tanggal_selesai_aktual' => null,
                'progres_target_persen'  => 60,
                'status'                 => 'sedang_berjalan',
                'sumber'                 => 'kontrak',
                'catatan'                => null,
            ]
        );

        // 2 Rencana Pengadaan + Realisasi
        $rencanaSemen = RencanaPengadaan::updateOrCreate(
            ['pekerjaan_id' => $proyek1->id, 'nama_item' => 'Semen Tiga Roda 50kg'],
            [
                'satuan'               => 'sak',
                'volume_rencana'       => 500,
                'harga_satuan_rencana' => 75_000,
                'keterangan'           => 'Untuk pengecoran kolom & balok',
                'created_by'           => $pptk?->id,
            ]
        );
        $rencanaBaja = RencanaPengadaan::updateOrCreate(
            ['pekerjaan_id' => $proyek1->id, 'nama_item' => 'Besi Beton Ulir D16'],
            [
                'satuan'               => 'batang',
                'volume_rencana'       => 300,
                'harga_satuan_rencana' => 145_000,
                'keterangan'           => 'Tulangan kolom utama',
                'created_by'           => $pptk?->id,
            ]
        );

        RealisasiPengadaan::updateOrCreate(
            [
                'rencana_pengadaan_id' => $rencanaSemen->id,
                'tanggal_realisasi'    => $today->copy()->subDays(10)->toDateString(),
            ],
            [
                'pekerjaan_id'   => $proyek1->id,
                'perusahaan_id'  => $itergo?->id,
                'volume_beli'    => 200,
                'harga_aktual'   => 78_000,
                'volume_dipakai' => 180,
                'volume_sisa'    => 20,
                'catatan_vendor' => 'Beli batch pertama, harga naik 4% dari rencana.',
                'status'         => 'verified',
                'catatan_pptk'   => 'Verified — invoice asli sudah diupload.',
                'verified_by'    => $pptk?->id,
                'verified_at'    => $today->copy()->subDays(9)->toDateTimeString(),
                'created_by'     => $vendor?->id,
            ]
        );
        RealisasiPengadaan::updateOrCreate(
            [
                'rencana_pengadaan_id' => $rencanaBaja->id,
                'tanggal_realisasi'    => $today->copy()->subDays(2)->toDateString(),
            ],
            [
                'pekerjaan_id'   => $proyek1->id,
                'perusahaan_id'  => $itergo?->id,
                'volume_beli'    => 150,
                'harga_aktual'   => 152_000,
                'volume_dipakai' => 0,
                'volume_sisa'    => 150,
                'catatan_vendor' => 'Stock di gudang, belum dipakai.',
                'status'         => 'submitted',
                'catatan_pptk'   => null,
                'verified_by'    => null,
                'verified_at'    => null,
                'created_by'     => $vendor?->id,
            ]
        );

        // Dokumen kontrak
        Dokumen::updateOrCreate(
            ['pekerjaan_id' => $proyek1->id, 'tipe' => 'kontrak', 'versi' => 1],
            [
                'nama_dokumen'       => 'Kontrak Pembangunan Posyandu Soreang',
                'file_path'          => 'demo/kontrak-posyandu-soreang.pdf',
                'file_original_name' => 'kontrak-posyandu-soreang.pdf',
                'file_size'          => 1_234_567,
                'keterangan'         => 'Versi awal kontrak setelah negosiasi.',
                'created_by'         => $superAdmin?->id,
            ]
        );

        // ────────────────────────────────────────────────────────────
        // PROYEK 2 — DEMO: Terlambat (deadline TODAY-10)
        // ────────────────────────────────────────────────────────────
        Pekerjaan::updateOrCreate(
            [
                'nama_pekerjaan' => '[DEMO] DED Drainase Cibaduyut Tahap 2',
                'bidang_id'      => $bidangDr?->id,
                'tahun_anggaran' => 2026,
            ],
            [
                'perusahaan_id'       => $purna?->id,
                'status_pekerjaan_id' => $statusReview?->id,
                'nilai_pagu'          => 99_500_000,
                'nilai_kontrak'       => 95_750_000,
                'no_spk'              => 'DEMO/02/SPK/DR-DPUTR/2026',
                'no_spmk'             => 'DEMO/02/SPMK/DR-DPUTR/2026',
                'tanggal_spk'         => $today->copy()->subDays(50)->toDateString(),
                'tanggal_spmk'        => $today->copy()->subDays(50)->toDateString(),
                'tanggal_mulai'       => $today->copy()->subDays(50)->toDateString(),
                'tanggal_akhir'       => $today->copy()->subDays(10)->toDateString(),
                'hari_kerja'          => 40,
                'satuan_waktu'        => 'hari_kalender',
                'progres_persen'      => 75,
                'catatan'             => 'Demo proyek terlambat — masih 75% padahal deadline lewat 10 hari.',
                'created_by'          => $superAdmin?->id,
                'updated_by'          => $superAdmin?->id,
            ]
        );

        // ────────────────────────────────────────────────────────────
        // PROYEK 3 — DEMO: Selesai (closed)
        // ────────────────────────────────────────────────────────────
        Pekerjaan::updateOrCreate(
            [
                'nama_pekerjaan' => '[DEMO] Studi Kelayakan Jalan Lingkar Banjaran',
                'bidang_id'      => $bidangJl?->id,
                'tahun_anggaran' => 2026,
            ],
            [
                'perusahaan_id'       => $adhi?->id,
                'status_pekerjaan_id' => $statusSelesai?->id,
                'nilai_pagu'          => 145_000_000,
                'nilai_kontrak'       => 138_500_000,
                'no_spk'              => 'DEMO/03/SPK/JL-DPUTR/2026',
                'no_spmk'             => 'DEMO/03/SPMK/JL-DPUTR/2026',
                'tanggal_spk'         => $today->copy()->subDays(120)->toDateString(),
                'tanggal_spmk'        => $today->copy()->subDays(120)->toDateString(),
                'tanggal_mulai'       => $today->copy()->subDays(120)->toDateString(),
                'tanggal_akhir'       => $today->copy()->subDays(60)->toDateString(),
                'hari_kerja'          => 60,
                'satuan_waktu'        => 'hari_kalender',
                'progres_persen'      => 100,
                'catatan'             => 'Demo proyek selesai — 100% progres, sudah ditutup.',
                'created_by'          => $superAdmin?->id,
                'updated_by'          => $superAdmin?->id,
            ]
        );

        // ────────────────────────────────────────────────────────────
        // PROYEK 4 — DEMO: Kritis (deadline TODAY+5, baru 40%)
        // ────────────────────────────────────────────────────────────
        Pekerjaan::updateOrCreate(
            [
                'nama_pekerjaan' => '[DEMO] Renovasi Atap Kantor Kecamatan Pangalengan',
                'bidang_id'      => $bidangBg?->id,
                'tahun_anggaran' => 2026,
            ],
            [
                'perusahaan_id'       => $itergo?->id,
                'status_pekerjaan_id' => $statusProses?->id,
                'nilai_pagu'          => 175_000_000,
                'nilai_kontrak'       => 168_000_000,
                'no_spk'              => 'DEMO/04/SPK/BG-DPUTR/2026',
                'no_spmk'             => 'DEMO/04/SPMK/BG-DPUTR/2026',
                'tanggal_spk'         => $today->copy()->subDays(40)->toDateString(),
                'tanggal_spmk'        => $today->copy()->subDays(40)->toDateString(),
                'tanggal_mulai'       => $today->copy()->subDays(40)->toDateString(),
                'tanggal_akhir'       => $today->copy()->addDays(5)->toDateString(),
                'hari_kerja'          => 45,
                'satuan_waktu'        => 'hari_kalender',
                'progres_persen'      => 40,
                'catatan'             => 'Demo proyek kritis — sisa 5 hari, baru 40% progres.',
                'created_by'          => $superAdmin?->id,
                'updated_by'          => $superAdmin?->id,
            ]
        );
    }
}
