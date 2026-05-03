<?php

namespace App\Filament\Widgets;

use App\Models\Pekerjaan;
use Filament\Widgets\ChartWidget;

class ProgresDistribusiWidget extends ChartWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Distribusi Progres Pekerjaan';
    protected static bool $isLazy = false;
    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $buckets = [
            '0–24%'    => 0,
            '25–49%'   => 0,
            '50–74%'   => 0,
            '75–99%'   => 0,
            '100%'     => 0,
        ];

        Pekerjaan::pluck('progres_persen')->each(function ($persen) use (&$buckets) {
            $p = (float) $persen;
            if ($p >= 100)      $buckets['100%']++;
            elseif ($p >= 75)   $buckets['75–99%']++;
            elseif ($p >= 50)   $buckets['50–74%']++;
            elseif ($p >= 25)   $buckets['25–49%']++;
            else                $buckets['0–24%']++;
        });

        return [
            'datasets' => [
                [
                    'label'           => 'Jumlah Proyek',
                    'data'            => array_values($buckets),
                    'backgroundColor' => [
                        'rgba(239,68,68,0.7)',   // 0–24  red
                        'rgba(249,115,22,0.7)',  // 25–49 orange
                        'rgba(234,179,8,0.7)',   // 50–74 yellow
                        'rgba(34,197,94,0.7)',   // 75–99 green
                        'rgba(16,185,129,0.7)',  // 100   emerald
                    ],
                    'borderColor' => [
                        'rgb(239,68,68)',
                        'rgb(249,115,22)',
                        'rgb(234,179,8)',
                        'rgb(34,197,94)',
                        'rgb(16,185,129)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_keys($buckets),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
