<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Grup: umum
            ['key' => 'nama_instansi',      'value' => 'Dinas Pekerjaan Umum dan Tata Ruang',     'type' => 'string',  'group' => 'umum',      'label' => 'Nama Instansi'],
            ['key' => 'nama_singkat',        'value' => 'DPUTR Kab. Bandung',                       'type' => 'string',  'group' => 'umum',      'label' => 'Nama Singkat'],
            ['key' => 'alamat_kantor',       'value' => 'Jl. Raya Soreang, Kabupaten Bandung',      'type' => 'string',  'group' => 'umum',      'label' => 'Alamat Kantor'],
            ['key' => 'no_telp_kantor',      'value' => '(022) 5891234',                            'type' => 'string',  'group' => 'umum',      'label' => 'Nomor Telepon Kantor'],
            ['key' => 'tahun_anggaran_aktif','value' => date('Y'),                                  'type' => 'integer', 'group' => 'umum',      'label' => 'Tahun Anggaran Aktif'],

            // Grup: notifikasi
            ['key' => 'deadline_alert_days', 'value' => '14,7,3',                                   'type' => 'string',  'group' => 'notifikasi','label' => 'Alert Deadline (hari ke-)'],
            ['key' => 'notif_laporan_aktif', 'value' => '1',                                        'type' => 'boolean', 'group' => 'notifikasi','label' => 'Aktifkan Notifikasi Laporan Harian'],
            ['key' => 'notif_deadline_aktif','value' => '1',                                        'type' => 'boolean', 'group' => 'notifikasi','label' => 'Aktifkan Notifikasi Deadline'],
            ['key' => 'notif_termin_aktif',  'value' => '1',                                        'type' => 'boolean', 'group' => 'notifikasi','label' => 'Aktifkan Notifikasi Termin'],

            // Grup: laporan_vendor
            ['key' => 'jam_masuk_buka',      'value' => '06:00',                                    'type' => 'string',  'group' => 'laporan_vendor', 'label' => 'Jam Buka Laporan Masuk'],
            ['key' => 'jam_masuk_tutup',     'value' => '09:00',                                    'type' => 'string',  'group' => 'laporan_vendor', 'label' => 'Jam Tutup Laporan Masuk'],
            ['key' => 'jam_pulang_buka',     'value' => '15:00',                                    'type' => 'string',  'group' => 'laporan_vendor', 'label' => 'Jam Buka Laporan Pulang'],
            ['key' => 'jam_pulang_tutup',    'value' => '18:00',                                    'type' => 'string',  'group' => 'laporan_vendor', 'label' => 'Jam Tutup Laporan Pulang'],

            // Grup: sistem
            ['key' => 'maintenance_mode',    'value' => '0',                                        'type' => 'boolean', 'group' => 'sistem',    'label' => 'Mode Maintenance'],
            ['key' => 'app_version',         'value' => '1.0.0',                                    'type' => 'string',  'group' => 'sistem',    'label' => 'Versi Aplikasi'],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
