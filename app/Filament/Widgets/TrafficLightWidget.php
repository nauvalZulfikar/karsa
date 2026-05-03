<?php

namespace App\Filament\Widgets;

use App\Models\Pekerjaan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TrafficLightWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $semua = Pekerjaan::with(['statusPekerjaan', 'personil'])->get();

        $counts = ['aman' => 0, 'waspada' => 0, 'kritis' => 0, 'terlambat' => 0, 'selesai' => 0, 'belum_mulai' => 0];

        foreach ($semua as $p) {
            $status = $p->status_waktu;
            if (isset($counts[$status])) {
                $counts[$status]++;
            } else {
                $counts['belum_mulai']++;
            }
        }

        return [
            Stat::make('Aman', $counts['aman'])
                ->description('Progres sesuai jadwal')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Waspada', $counts['waspada'])
                ->description('Mendekati batas waktu')
                ->icon('heroicon-o-exclamation-circle')
                ->color('warning'),

            Stat::make('Kritis', $counts['kritis'])
                ->description('Sangat mendekati deadline')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Terlambat', $counts['terlambat'])
                ->description('Melewati tanggal kontrak')
                ->icon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make('Selesai', $counts['selesai'])
                ->description('Pekerjaan telah selesai')
                ->icon('heroicon-o-trophy')
                ->color('success'),

            Stat::make('Belum Mulai', $counts['belum_mulai'])
                ->description('Tanggal mulai belum tercapai')
                ->icon('heroicon-o-calendar')
                ->color('gray'),
        ];
    }
}
