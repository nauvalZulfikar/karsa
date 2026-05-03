<?php

namespace App\Console\Commands;

use App\Models\Pekerjaan;
use App\Models\SystemSetting;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class KirimNotifikasiDeadline extends Command
{
    protected $signature   = 'notifikasi:deadline';
    protected $description = 'Kirim notifikasi WA untuk pekerjaan yang mendekati deadline';

    public function handle(WaGatewayService $wa): int
    {
        $deadlineDays = array_map(
            'intval',
            explode(',', SystemSetting::get('deadline_alert_days', env('NOTIF_DEADLINE_DAYS', '14,7,3')))
        );

        if (!SystemSetting::get('notif_deadline_aktif', true)) {
            $this->info('Notifikasi deadline dinonaktifkan via pengaturan sistem.');
            return Command::SUCCESS;
        }

        $today      = Carbon::today();
        $kirimCount = 0;

        foreach ($deadlineDays as $sisaHari) {
            $targetDate = $today->copy()->addWeekdays($sisaHari);

            $pekerjaanList = Pekerjaan::with(['personil.tenagaAhli', 'statusPekerjaan'])
                ->whereDate('tanggal_akhir', $targetDate)
                ->whereHas('statusPekerjaan', fn ($q) => $q->where('is_final', false))
                ->get();

            foreach ($pekerjaanList as $pekerjaan) {
                $pesan = $this->buildPesan($pekerjaan, $sisaHari);

                foreach ($pekerjaan->personil as $personil) {
                    $telp = $personil->tenagaAhli?->no_telp ?? null;
                    if ($telp && $wa->kirim($telp, $pesan)) {
                        $kirimCount++;
                    }
                }
            }
        }

        $this->info("Notifikasi deadline terkirim: {$kirimCount} pesan.");
        return Command::SUCCESS;
    }

    private function buildPesan(Pekerjaan $pekerjaan, int $sisaHari): string
    {
        $tanggalAkhir = $pekerjaan->tanggal_akhir->format('d M Y');
        $progres      = $pekerjaan->progres_persen ?? 0;

        return implode("\n", [
            '⚠️ *PERINGATAN DEADLINE PEKERJAAN*',
            '',
            "Proyek: *{$pekerjaan->nama_pekerjaan}*",
            "Sisa waktu: *{$sisaHari} hari kerja*",
            "Deadline: {$tanggalAkhir}",
            "Progres saat ini: {$progres}%",
            '',
            'Segera pastikan progres pekerjaan sesuai target.',
            '',
            '_Pesan otomatis DPUTR Project Management_',
        ]);
    }
}
