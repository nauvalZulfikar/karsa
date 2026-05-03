<?php

namespace App\Exports;

use App\Models\TerminPembayaran;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TerminPembayaranExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private ?int $pekerjaanId = null) {}

    public function query()
    {
        return TerminPembayaran::query()
            ->with(['pekerjaan', 'approvedBy'])
            ->when($this->pekerjaanId, fn ($q) => $q->where('pekerjaan_id', $this->pekerjaanId))
            ->orderBy('pekerjaan_id')
            ->orderBy('nomor_termin');
    }

    public function title(): string
    {
        return 'Rekap Pembayaran';
    }

    public function headings(): array
    {
        return [
            'No', 'Proyek', 'Termin', 'Nilai (Rp)', 'Syarat Progres (%)',
            'Status', 'Tgl Pengajuan', 'Tgl Persetujuan', 'Tgl Bayar',
            'Disetujui Oleh', 'Catatan PPK',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row->pekerjaan?->nama_pekerjaan ?? '-',
            $row->nama_termin,
            (float) $row->nilai_termin,
            (float) $row->persen_progres_syarat,
            $row->status_label,
            $row->tanggal_pengajuan?->format('d/m/Y') ?? '-',
            $row->tanggal_persetujuan?->format('d/m/Y') ?? '-',
            $row->tanggal_bayar?->format('d/m/Y') ?? '-',
            $row->approvedBy?->name ?? '-',
            $row->catatan_ppk ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EDE9FE'],
            ]],
        ];
    }
}
