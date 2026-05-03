<?php

namespace App\Filament\Pages;

use App\Models\Master\Bidang;
use App\Models\Pekerjaan;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class TimelinePekerjaan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Timeline';
    protected static ?string $title = 'Timeline Pekerjaan';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.timeline-pekerjaan';

    public ?int $filterBidang = null;
    public int $filterTahun = 2026;

    public function getPekerjaan(): \Illuminate\Support\Collection
    {
        return Pekerjaan::with(['bidang', 'statusPekerjaan', 'perusahaan'])
            ->when($this->filterBidang, fn ($q) => $q->where('bidang_id', $this->filterBidang))
            ->where('tahun_anggaran', $this->filterTahun)
            ->whereNotNull('tanggal_mulai')
            ->whereNotNull('tanggal_akhir')
            ->orderBy('tanggal_akhir')
            ->get();
    }

    public function getBidangOptions(): array
    {
        return Bidang::active()->pluck('nama', 'id')->toArray();
    }
}
