<?php

namespace App\Console\Commands;

use App\Models\LaporanHarian;
use App\Models\Pekerjaan;
use App\Models\SystemSetting;
use App\Models\TerminPembayaran;
use App\Models\User;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class KirimWeeklyDigest extends Command
{
    protected $signature   = 'notifikasi:weekly-digest';
    protected $description = 'Kirim ringkasan mingguan via WA ke admin/manajemen tiap Senin pagi';

    public function handle(WaGatewayService $wa): int
    {
        if (!SystemSetting::get('notif_weekly_digest_aktif', true)) {
            $this->info('Weekly digest dinonaktifkan via pengaturan.');
            return Command::SUCCESS;
        }

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd   = Carbon::now()->endOfWeek();

        $pekerjaans = Pekerjaan::with('statusPekerjaan')->get();
        $aman       = $pekerjaans->filter(fn ($p) => $p->status_waktu === 'aman')->count();
        $waspada    = $pekerjaans->filter(fn ($p) => $p->status_waktu === 'waspada')->count();
        $kritis     = $pekerjaans->filter(fn ($p) => in_array($p->status_waktu, ['kritis', 'terlambat']))->count();
        $selesai    = $pekerjaans->filter(fn ($p) => $p->status_waktu === 'selesai')->count();

        $kritisList = $pekerjaans
            ->filter(fn ($p) => in_array($p->status_waktu, ['kritis', 'terlambat']))
            ->take(5);

        $laporanMinggu = LaporanHarian::whereBetween('tanggal_laporan', [$weekStart, $weekEnd])->count();

        $terminMenunggu = TerminPembayaran::where('status', 'diajukan')->count();

        $tahun       = SystemSetting::get('tahun_anggaran_aktif', date('Y'));
        $instansi    = SystemSetting::get('nama_singkat', 'DPUTR');

        $msg  = "📊 *Ringkasan Mingguan {$instansi}*\n";
        $msg .= "Tahun Anggaran: {$tahun}\n";
        $msg .= "Periode: " . $weekStart->format('d M') . " – " . $weekEnd->format('d M Y') . "\n\n";

        $msg .= "🚦 *Status Proyek* (total: {$pekerjaans->count()})\n";
        $msg .= "🟢 Aman: {$aman}\n";
        $msg .= "🟡 Waspada: {$waspada}\n";
        $msg .= "🔴 Kritis/Terlambat: {$kritis}\n";
        $msg .= "✅ Selesai: {$selesai}\n\n";

        if ($kritisList->isNotEmpty()) {
            $msg .= "⚠️ *Perlu Perhatian:*\n";
            foreach ($kritisList as $p) {
                $sisa = $p->sisa_hari;
                $info = $sisa < 0 ? "terlambat " . abs($sisa) . " hari" : "sisa {$sisa} hari";
                $msg .= "• {$p->nama_pekerjaan} (progres {$p->progres_persen}%, {$info})\n";
            }
            $msg .= "\n";
        }

        $msg .= "📝 Laporan harian minggu ini: {$laporanMinggu}\n";
        if ($terminMenunggu > 0) {
            $msg .= "💰 Termin menunggu approval: {$terminMenunggu}\n";
        }

        $msg .= "\nBuka dashboard untuk detail lengkap.";

        $targetUsers = User::role(['super_admin', 'admin_bidang'])
            ->whereNotNull('no_telp')
            ->where('no_telp', '!=', '')
            ->get();

        $kirimCount = 0;
        foreach ($targetUsers as $user) {
            try {
                $wa->kirim($user->no_telp, $msg);
                $this->info("Sent to {$user->name} ({$user->no_telp})");
                $kirimCount++;
            } catch (\Throwable $e) {
                $this->error("Failed to {$user->name}: " . $e->getMessage());
            }
        }

        $this->info("Total terkirim: {$kirimCount} dari {$targetUsers->count()}");
        return Command::SUCCESS;
    }
}
