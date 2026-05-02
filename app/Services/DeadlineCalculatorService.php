<?php

namespace App\Services;

use App\Models\Master\HariLibur;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DeadlineCalculatorService
{
    public function hitungSisaHariKerja(?Carbon $tanggalAkhir, string $satuan = 'hari_kerja'): ?int
    {
        if (!$tanggalAkhir) return null;

        $today = Carbon::today();

        if ($satuan === 'hari_kalender') {
            return $today->diffInDays($tanggalAkhir, false);
        }

        $libur = $this->getLiburDates($tanggalAkhir->year);
        if ($today->year !== $tanggalAkhir->year) {
            $libur = array_merge($libur, $this->getLiburDates($today->year));
        }

        if ($today->gt($tanggalAkhir)) {
            return -$this->countWorkingDays($tanggalAkhir, $today, $libur);
        }

        return $this->countWorkingDays($today, $tanggalAkhir, $libur);
    }

    public function hitungPersenWaktu(?Carbon $tanggalMulai, ?Carbon $tanggalAkhir, string $satuan = 'hari_kerja'): ?float
    {
        if (!$tanggalMulai || !$tanggalAkhir) return null;

        $today = Carbon::today();
        $libur = $this->getLiburDates($tanggalAkhir->year);

        $total = max(1, $this->countWorkingDays($tanggalMulai, $tanggalAkhir, $libur));
        $terpakai = $this->countWorkingDays($tanggalMulai, min($today, $tanggalAkhir), $libur);

        return min(100, round(($terpakai / $total) * 100, 1));
    }

    public function getStatusWaktu(?int $sisaHari, bool $sudahSelesai = false): string
    {
        if ($sudahSelesai) return 'selesai';
        if ($sisaHari === null) return 'belum_mulai';
        if ($sisaHari < 0) return 'terlambat';
        if ($sisaHari <= 7) return 'kritis';
        if ($sisaHari <= 14) return 'waspada';
        return 'aman';
    }

    private function countWorkingDays(Carbon $from, Carbon $to, array $libur): int
    {
        $count = 0;
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lte($end)) {
            if (!$current->isWeekend() && !in_array($current->format('Y-m-d'), $libur)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    private function getLiburDates(int $year): array
    {
        return Cache::remember("hari_libur_{$year}", 3600, fn() => HariLibur::getLiburDates($year));
    }
}
