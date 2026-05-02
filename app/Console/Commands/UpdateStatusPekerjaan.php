<?php

namespace App\Console\Commands;

use App\Models\Master\StatusPekerjaan;
use App\Models\Pekerjaan;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateStatusPekerjaan extends Command
{
    protected $signature = 'pekerjaan:update-status';
    protected $description = 'Update status pekerjaan yang sudah melewati deadline menjadi terlambat';

    public function handle(): void
    {
        $statusTerlambat = StatusPekerjaan::where('kode', 'terlambat')->first();
        $statusSelesai = StatusPekerjaan::where('kode', 'selesai')->first();

        if (!$statusTerlambat || !$statusSelesai) {
            $this->error('Status pekerjaan tidak ditemukan di database.');
            return;
        }

        $updated = Pekerjaan::query()
            ->whereNotNull('tanggal_akhir')
            ->where('tanggal_akhir', '<', Carbon::today())
            ->where('status_pekerjaan_id', '!=', $statusSelesai->id)
            ->where('status_pekerjaan_id', '!=', $statusTerlambat->id)
            ->update(['status_pekerjaan_id' => $statusTerlambat->id]);

        $this->info("Updated {$updated} pekerjaan ke status Terlambat.");
    }
}
