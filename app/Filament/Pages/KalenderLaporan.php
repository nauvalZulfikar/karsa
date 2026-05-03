<?php

namespace App\Filament\Pages;

use App\Models\LaporanHarian;
use App\Models\Master\Perusahaan;
use App\Models\Pekerjaan;
use Carbon\Carbon;
use Filament\Pages\Page;

class KalenderLaporan extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationLabel = 'Kalender Laporan';
    protected static ?string $title = 'Kalender Laporan Vendor';
    protected static string $view = 'filament.pages.kalender-laporan';
    protected static ?int $navigationSort = 4;

    public ?int $filterPekerjaan = null;
    public string $filterTanggal = '';

    public function mount(): void
    {
        $this->filterTanggal = today()->format('Y-m-d');
    }

    public function getPekerjaanOptions(): array
    {
        return Pekerjaan::has('vendors')
            ->pluck('nama_pekerjaan', 'id')
            ->toArray();
    }

    public function getKalenderData(): array
    {
        if (!$this->filterPekerjaan) {
            return [];
        }

        $pekerjaan = Pekerjaan::with('vendors')->find($this->filterPekerjaan);
        if (!$pekerjaan) {
            return [];
        }

        $tanggal     = Carbon::parse($this->filterTanggal);
        $startOfWeek = $tanggal->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $dates       = collect(range(0, 6))->map(fn ($d) => $startOfWeek->copy()->addDays($d));

        $laporan = LaporanHarian::where('pekerjaan_id', $this->filterPekerjaan)
            ->whereBetween('tanggal_laporan', [$startOfWeek->format('Y-m-d'), $startOfWeek->copy()->addDays(6)->format('Y-m-d')])
            ->get()
            ->groupBy(fn ($l) => $l->perusahaan_id . '_' . $l->tanggal_laporan->format('Y-m-d') . '_' . $l->jenis);

        $rows = [];
        foreach ($pekerjaan->vendors as $vendor) {
            $cols = [];
            foreach ($dates as $date) {
                $dateStr   = $date->format('Y-m-d');
                $keyMasuk  = $vendor->id . '_' . $dateStr . '_masuk';
                $keyPulang = $vendor->id . '_' . $dateStr . '_pulang';
                $cols[] = [
                    'date'    => $dateStr,
                    'masuk'   => isset($laporan[$keyMasuk]),
                    'pulang'  => isset($laporan[$keyPulang]),
                    'weekend' => $date->isWeekend(),
                ];
            }
            $rows[] = ['vendor' => $vendor->nama, 'cols' => $cols];
        }

        return [
            'dates' => $dates->map(fn ($d) => $d->translatedFormat('D d/m'))->toArray(),
            'rows'  => $rows,
        ];
    }
}
