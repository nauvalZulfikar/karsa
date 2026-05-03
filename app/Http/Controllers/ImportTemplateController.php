<?php

namespace App\Http\Controllers;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ImportTemplateController extends Controller
{
    public function download(string $type)
    {
        abort_unless(auth()->check(), 403);

        return match($type) {
            'pekerjaan'  => $this->templatePekerjaan(),
            'perusahaan' => $this->templatePerusahaan(),
            'hari-libur' => $this->templateHariLibur(),
            default      => abort(404),
        };
    }

    private function templatePekerjaan()
    {
        $export = new class implements FromArray, WithHeadings, ShouldAutoSize {
            public function headings(): array
            {
                return [
                    'bidang', 'jenis_pekerjaan', 'perusahaan', 'status',
                    'tahun_anggaran', 'nama_pekerjaan', 'nilai_pagu', 'nilai_kontrak',
                    'no_spk', 'tanggal_spk', 'no_spmk', 'tanggal_spmk',
                    'tanggal_mulai', 'tanggal_akhir', 'hari_kerja', 'satuan_waktu',
                    'progres_persen', 'catatan',
                ];
            }
            public function array(): array
            {
                return [[
                    'Bina Marga', 'Jasa Konsultansi', 'CV Contoh Jaya', 'berjalan',
                    date('Y'), 'Peningkatan Jalan Contoh', 500000000, 450000000,
                    'SPK/001/BM/2026', '01/01/2026', 'SPMK/001/BM/2026', '05/01/2026',
                    '10/01/2026', '31/12/2026', 200, 'hari_kalender',
                    25, 'Contoh catatan',
                ]];
            }
        };

        return Excel::download($export, 'template-import-pekerjaan.xlsx');
    }

    private function templatePerusahaan()
    {
        $export = new class implements FromArray, WithHeadings, ShouldAutoSize {
            public function headings(): array
            {
                return ['nama', 'npwp', 'alamat', 'no_telp', 'email', 'direktur', 'blacklist'];
            }
            public function array(): array
            {
                return [['CV Contoh Jaya', '01.234.567.8-901.000', 'Jl. Contoh No.1 Bandung', '08112345678', 'cv@contoh.co.id', 'Budi Santoso', 'tidak']];
            }
        };

        return Excel::download($export, 'template-import-perusahaan.xlsx');
    }

    private function templateHariLibur()
    {
        $export = new class implements FromArray, WithHeadings, ShouldAutoSize {
            public function headings(): array
            {
                return ['tanggal', 'nama'];
            }
            public function array(): array
            {
                return [
                    ['01/01/2026', 'Tahun Baru Masehi'],
                    ['28/01/2026', 'Tahun Baru Imlek'],
                ];
            }
        };

        return Excel::download($export, 'template-import-hari-libur.xlsx');
    }
}
