<?php

namespace App\Console\Commands;

use App\Models\LaporanHarian;
use App\Models\Pekerjaan;
use App\Models\User;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class KirimReminderLaporanHarian extends Command
{
    protected $signature   = 'notifikasi:laporan-harian {jenis=masuk : masuk atau pulang}';
    protected $description = 'Kirim reminder WA ke vendor yang belum submit laporan harian';

    public function handle(WaGatewayService $wa): int
    {
        $jenis = $this->argument('jenis');
        if (!in_array($jenis, ['masuk', 'pulang'])) {
            $this->error('Jenis harus "masuk" atau "pulang"');
            return Command::FAILURE;
        }

        $today      = Carbon::today();
        $kirimCount = 0;

        $vendorUsers = User::role('vendor')
            ->where('is_active', true)
            ->whereNotNull('perusahaan_id')
            ->whereNotNull('no_telp')
            ->get();

        foreach ($vendorUsers as $user) {
            $proyekAktif = Pekerjaan::whereHas('vendors', fn ($q) => $q->where('perusahaan.id', $user->perusahaan_id))
                ->whereHas('statusPekerjaan', fn ($q) => $q->where('is_final', false))
                ->whereDate('tanggal_mulai', '<=', $today)
                ->whereDate('tanggal_akhir', '>=', $today)
                ->exists();

            if (!$proyekAktif) {
                continue;
            }

            $sudahLapor = LaporanHarian::where('user_id', $user->id)
                ->where('jenis', $jenis)
                ->whereDate('tanggal_laporan', $today)
                ->exists();

            if ($sudahLapor) {
                continue;
            }

            $pesan = $this->buildPesan($user, $jenis);
            if ($wa->kirim($user->no_telp, $pesan)) {
                $kirimCount++;
            }
        }

        $this->info("Reminder laporan {$jenis} terkirim: {$kirimCount} vendor.");
        return Command::SUCCESS;
    }

    private function buildPesan(User $user, string $jenis): string
    {
        $jamWindow = $jenis === 'masuk' ? '06:00 – 09:00' : '15:00 – 18:00';
        $icon      = $jenis === 'masuk' ? '🌅' : '🌇';

        return implode("\n", [
            "{$icon} *REMINDER LAPORAN HARIAN*",
            '',
            "Halo {$user->name},",
            "Mohon segera submit laporan *{$jenis}* hari ini.",
            "Jam laporan {$jenis}: {$jamWindow} WIB",
            '',
            'Akses portal vendor di:',
            url('/vendor/submit-laporan'),
            '',
            '_Pesan otomatis DPUTR Project Management_',
        ]);
    }
}
