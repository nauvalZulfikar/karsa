<?php

namespace App\Exports;

use App\Models\RencanaPengadaan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PengadaanExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private ?int $pekerjaanId = null) {}

    public function query()
    {
        return RencanaPengadaan::query()
            ->with(['pekerjaan', 'realisasi' => fn ($q) => $q->where('status', 'verified')])
            ->when($this->pekerjaanId, fn ($q) => $q->where('pekerjaan_id', $this->pekerjaanId))
            ->orderBy('pekerjaan_id')
            ->orderBy('nama_item');
    }

    public function title(): string
    {
        return 'Rekap Pengadaan';
    }

    public function headings(): array
    {
        return [
            'No', 'Proyek', 'Nama Item', 'Satuan',
            'Vol Rencana', 'Harga Satuan Rencana (Rp)', 'Total Rencana (Rp)',
            'Vol Terpakai (Verified)', 'Selisih Vol', 'Total Realisasi (Rp)', 'Alert',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row->pekerjaan?->nama_pekerjaan ?? '-',
            $row->nama_item,
            $row->satuan,
            (float) $row->volume_rencana,
            (float) $row->harga_satuan_rencana,
            $row->total_rencana,
            $row->total_volume_dipakai,
            $row->selisih_volume,
            $row->total_realisasi_nilai,
            $row->is_alert ? 'YA' : 'OK',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF9C3'],
            ]],
        ];
    }
}
