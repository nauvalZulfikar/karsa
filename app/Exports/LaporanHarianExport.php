<?php

namespace App\Exports;

use App\Models\LaporanHarian;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanHarianExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private ?int    $pekerjaanId = null,
        private ?string $dari = null,
        private ?string $sampai = null,
    ) {}

    public function query()
    {
        return LaporanHarian::query()
            ->with(['pekerjaan', 'perusahaan', 'user'])
            ->when($this->pekerjaanId, fn ($q) => $q->where('pekerjaan_id', $this->pekerjaanId))
            ->when($this->dari,   fn ($q) => $q->whereDate('tanggal_laporan', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('tanggal_laporan', '<=', $this->sampai))
            ->orderBy('tanggal_laporan')
            ->orderBy('submitted_at');
    }

    public function title(): string
    {
        return 'Laporan Harian Vendor';
    }

    public function headings(): array
    {
        return [
            'No', 'Tanggal', 'Proyek', 'Vendor', 'Pelapor',
            'Jenis', 'Jam Submit', 'Status', 'Lokasi (Lat,Lng)', 'Catatan',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;

        $lokasi = ($row->latitude && $row->longitude)
            ? "{$row->latitude},{$row->longitude}"
            : '-';

        return [
            $no,
            $row->tanggal_laporan?->format('d/m/Y') ?? '-',
            $row->pekerjaan?->nama_pekerjaan ?? '-',
            $row->perusahaan?->nama ?? '-',
            $row->user?->name ?? '-',
            ucfirst($row->jenis),
            $row->submitted_at?->format('H:i') ?? '-',
            match($row->status) {
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default    => 'Pending',
            },
            $lokasi,
            $row->catatan ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D1FAE5'],
            ]],
        ];
    }
}
