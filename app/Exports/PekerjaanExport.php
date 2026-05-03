<?php

namespace App\Exports;

use App\Models\Pekerjaan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PekerjaanExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private ?int $bidangId = null,
        private ?string $tahun = null,
        private ?string $statusWaktu = null,
    ) {}

    public function query()
    {
        return Pekerjaan::query()
            ->with(['bidang', 'perusahaan', 'statusPekerjaan', 'jenisPekerjaan'])
            ->when($this->bidangId, fn ($q) => $q->where('bidang_id', $this->bidangId))
            ->when($this->tahun,    fn ($q) => $q->where('tahun_anggaran', $this->tahun))
            ->orderBy('bidang_id')
            ->orderBy('nama_pekerjaan');
    }

    public function title(): string
    {
        return 'Rekap Pekerjaan';
    }

    public function headings(): array
    {
        return [
            'No', 'Bidang', 'Nama Pekerjaan', 'Jenis', 'Perusahaan',
            'Tahun Anggaran', 'Nilai Pagu (Rp)', 'Nilai Kontrak (Rp)',
            'No SPK', 'Tgl Mulai', 'Tgl Akhir', 'Hari Kerja',
            'Progres (%)', 'Status', 'Traffic Light', 'Sisa Hari',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row->bidang?->nama ?? '-',
            $row->nama_pekerjaan,
            $row->jenisPekerjaan?->nama ?? '-',
            $row->perusahaan?->nama ?? '-',
            $row->tahun_anggaran,
            (float) $row->nilai_pagu,
            (float) $row->nilai_kontrak,
            $row->no_spk ?? '-',
            $row->tanggal_mulai?->format('d/m/Y') ?? '-',
            $row->tanggal_akhir?->format('d/m/Y') ?? '-',
            $row->hari_kerja,
            (float) ($row->progres_persen ?? 0),
            $row->statusPekerjaan?->nama ?? '-',
            $row->status_waktu_label,
            $row->sisa_hari ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DBEAFE'],
            ]],
        ];
    }
}
