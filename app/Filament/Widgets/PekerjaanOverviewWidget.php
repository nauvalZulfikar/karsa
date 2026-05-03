<?php

namespace App\Filament\Widgets;

use App\Models\LaporanHarian;
use App\Models\Pekerjaan;
use App\Models\TerminPembayaran;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PekerjaanOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $totalAktif = Pekerjaan::whereHas('statusPekerjaan', fn ($q) => $q->where('is_final', false))->count();

        $totalNilai = Pekerjaan::whereHas('statusPekerjaan', fn ($q) => $q->where('is_final', false))
            ->sum('nilai_kontrak');

        $laporanPending = LaporanHarian::where('status', 'pending')
            ->whereDate('tanggal_laporan', today())
            ->count();

        $terminPending = TerminPembayaran::where('status', 'diajukan')->count();

        return [
            Stat::make('Proyek Aktif', $totalAktif)
                ->description('Total proyek sedang berjalan')
                ->icon('heroicon-o-briefcase')
                ->color('primary'),

            Stat::make('Total Nilai Kontrak Aktif', 'Rp ' . number_format($totalNilai, 0, ',', '.'))
                ->description('Akumulasi nilai kontrak proyek aktif')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Laporan Hari Ini (Pending)', $laporanPending)
                ->description('Laporan vendor menunggu approval')
                ->icon('heroicon-o-camera')
                ->color($laporanPending > 0 ? 'warning' : 'success'),

            Stat::make('Termin Menunggu Persetujuan', $terminPending)
                ->description('Pengajuan pembayaran belum disetujui PPK')
                ->icon('heroicon-o-clock')
                ->color($terminPending > 0 ? 'warning' : 'success'),
        ];
    }
}
