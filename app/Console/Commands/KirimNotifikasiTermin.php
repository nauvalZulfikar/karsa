<?php

namespace App\Console\Commands;

use App\Models\TerminPembayaran;
use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Console\Command;

class KirimNotifikasiTermin extends Command
{
    protected $signature   = 'notifikasi:termin-pending';
    protected $description = 'Kirim notifikasi WA ke PPK untuk termin yang menunggu persetujuan';

    public function handle(WaGatewayService $wa): int
    {
        $terminPending = TerminPembayaran::with(['pekerjaan'])
            ->where('status', 'diajukan')
            ->where('tanggal_pengajuan', '<=', now()->subDay())
            ->get();

        if ($terminPending->isEmpty()) {
            $this->info('Tidak ada termin pending.');
            return Command::SUCCESS;
        }

        $ppkUsers = User::role('ppk')
            ->where('is_active', true)
            ->whereNotNull('no_telp')
            ->get();

        $kirimCount = 0;

        foreach ($terminPending as $termin) {
            $pesan = $this->buildPesan($termin);
            foreach ($ppkUsers as $ppk) {
                if ($wa->kirim($ppk->no_telp, $pesan)) {
                    $kirimCount++;
                }
            }
        }

        $this->info("Notifikasi termin pending terkirim: {$kirimCount} pesan.");
        return Command::SUCCESS;
    }

    private function buildPesan(TerminPembayaran $termin): string
    {
        $nilai = 'Rp ' . number_format($termin->nilai_termin, 0, ',', '.');

        return implode("\n", [
            '💰 *TERMIN PEMBAYARAN MENUNGGU PERSETUJUAN*',
            '',
            "Proyek: *{$termin->pekerjaan->nama_pekerjaan}*",
            "Termin: {$termin->nama_termin}",
            "Nilai: {$nilai}",
            "Tanggal pengajuan: {$termin->tanggal_pengajuan->format('d M Y')}",
            '',
            'Silakan login untuk menyetujui atau menolak termin ini.',
            '',
            '_Pesan otomatis DPUTR Project Management_',
        ]);
    }
}
