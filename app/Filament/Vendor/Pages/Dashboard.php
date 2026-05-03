<?php

namespace App\Filament\Vendor\Pages;

use App\Models\LaporanHarian;
use App\Models\Pekerjaan;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Dashboard Vendor';
    protected static string $view = 'filament.vendor.pages.dashboard';
    protected static ?int $navigationSort = 1;

    public function getPekerjaan(): \Illuminate\Support\Collection
    {
        $perusahaanId = auth()->user()->perusahaan_id;
        if (!$perusahaanId) {
            return collect();
        }

        return Pekerjaan::with(['bidang', 'statusPekerjaan'])
            ->whereHas('vendors', fn ($q) => $q->where('perusahaan.id', $perusahaanId))
            ->orderBy('tanggal_akhir')
            ->get();
    }

    public function getLaporanHariIni(): \Illuminate\Support\Collection
    {
        return LaporanHarian::where('user_id', auth()->id())
            ->whereDate('tanggal_laporan', today())
            ->with('pekerjaan')
            ->get();
    }
}
