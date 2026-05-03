<?php

namespace App\Livewire;

use App\Models\LaporanHarian;
use App\Models\Pekerjaan;
use App\Models\TerminPembayaran;
use Carbon\Carbon;

class AiChatHero extends AiChatWidget
{
    public bool $isOpen = true;

    public function mount(): void
    {
        if (empty($this->messages)) {
            $name = auth()->user()?->name ?? 'Pak';

            // 1. Welcome message
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => "Halo {$name}! Saya asisten AI DPUTR. Coba: \"berapa proyek kritis hari ini?\" atau \"update progres jalan soreang jadi 75%\".",
            ];

            // 2. Auto status report
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => $this->generateStatusReport(),
            ];
        }
    }

    private function generateStatusReport(): string
    {
        $today = Carbon::today()->locale('id');
        $tahunIni = (int) date('Y');

        $pekerjaans = Pekerjaan::with('statusPekerjaan')
            ->where('tahun_anggaran', $tahunIni)
            ->get();

        $total       = $pekerjaans->count();
        $aman        = $pekerjaans->filter(fn($p) => $p->status_waktu === 'aman')->count();
        $waspada     = $pekerjaans->filter(fn($p) => $p->status_waktu === 'waspada')->count();
        $kritis      = $pekerjaans->filter(fn($p) => $p->status_waktu === 'kritis')->count();
        $terlambat   = $pekerjaans->filter(fn($p) => $p->status_waktu === 'terlambat')->count();
        $belumMulai  = $pekerjaans->filter(fn($p) => $p->status_waktu === 'belum_mulai')->count();
        $selesai     = $pekerjaans->filter(fn($p) => $p->status_waktu === 'selesai')->count();
        $aktif       = $total - $selesai;

        $perluPerhatian = $pekerjaans
            ->filter(fn($p) => in_array($p->status_waktu, ['kritis', 'terlambat']))
            ->sortBy(fn($p) => $p->sisa_hari)
            ->take(3);

        $laporanHariIni  = LaporanHarian::whereDate('tanggal_laporan', $today)->count();
        $terminMenunggu  = TerminPembayaran::where('status', 'diajukan')->count();

        $report  = "📊 Status Hari Ini · {$today->translatedFormat('l, d F Y')}\n";
        $report .= str_repeat('─', 30) . "\n\n";

        $report .= "📋 Ringkasan Proyek {$tahunIni}\n";
        $report .= "Total: {$total}  •  Aktif: {$aktif}  •  Selesai: {$selesai}\n\n";

        $report .= "🚦 Status Waktu\n";
        $report .= "🟢 Aman: {$aman}   🟡 Waspada: {$waspada}   🔴 Kritis: {$kritis}\n";
        $report .= "⛔ Terlambat: {$terlambat}   ⏸ Backlog: {$belumMulai}\n";

        if ($perluPerhatian->isNotEmpty()) {
            $report .= "\n⚠️ Perlu Perhatian\n";
            foreach ($perluPerhatian as $p) {
                $sisa = $p->sisa_hari;
                $info = $sisa < 0 ? "terlambat " . abs($sisa) . " hari" : "{$sisa} hari lagi";
                $nama = mb_strlen($p->nama_pekerjaan) > 50
                    ? mb_substr($p->nama_pekerjaan, 0, 47) . '...'
                    : $p->nama_pekerjaan;
                $report .= "• {$nama} — {$info}\n";
            }
        }

        $report .= "\n📝 Hari Ini\n";
        $report .= "• {$laporanHariIni} laporan harian masuk\n";
        if ($terminMenunggu > 0) {
            $report .= "• 💰 {$terminMenunggu} termin menunggu approval\n";
        }

        $report .= "\nTanya saya untuk detail lebih lanjut, atau pilih saran di bawah 👇";

        return $report;
    }

    public function render()
    {
        return view('livewire.ai-chat-hero');
    }
}
