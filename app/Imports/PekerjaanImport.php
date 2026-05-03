<?php

namespace App\Imports;

use App\Models\Master\Bidang;
use App\Models\Master\JenisPekerjaan;
use App\Models\Master\Perusahaan;
use App\Models\Master\StatusPekerjaan;
use App\Models\Pekerjaan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class PekerjaanImport implements ToCollection, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public array $errors   = [];
    public int   $imported = 0;
    public int   $skipped  = 0;

    private array $bidangCache       = [];
    private array $jenisCache        = [];
    private array $perusahaanCache   = [];
    private array $statusCache       = [];

    public function chunkSize(): int
    {
        return 50;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            try {
                $bidang = $this->lookupBidang($row['bidang'] ?? null);
                if (!$bidang) {
                    $this->errors[] = "Baris {$rowNum}: Bidang '{$row['bidang']}' tidak ditemukan.";
                    $this->skipped++;
                    continue;
                }

                $perusahaan = $this->lookupPerusahaan($row['perusahaan'] ?? null);

                $namaPekerjaan = trim($row['nama_pekerjaan'] ?? '');
                if (!$namaPekerjaan) {
                    $this->errors[] = "Baris {$rowNum}: Kolom 'nama_pekerjaan' wajib diisi.";
                    $this->skipped++;
                    continue;
                }

                $data = [
                    'bidang_id'          => $bidang->id,
                    'jenis_pekerjaan_id' => $this->lookupJenis($row['jenis_pekerjaan'] ?? null)?->id,
                    'perusahaan_id'      => $perusahaan?->id,
                    'status_pekerjaan_id'=> $this->lookupStatus($row['status'] ?? 'berjalan')?->id,
                    'tahun_anggaran'     => $row['tahun_anggaran'] ?? date('Y'),
                    'nama_pekerjaan'     => $namaPekerjaan,
                    'nilai_pagu'         => $this->parseAngka($row['nilai_pagu'] ?? 0),
                    'nilai_kontrak'      => $this->parseAngka($row['nilai_kontrak'] ?? 0),
                    'no_spk'             => $row['no_spk'] ?? null,
                    'tanggal_spk'        => $this->parseTanggal($row['tanggal_spk'] ?? null),
                    'no_spmk'            => $row['no_spmk'] ?? null,
                    'tanggal_spmk'       => $this->parseTanggal($row['tanggal_spmk'] ?? null),
                    'tanggal_mulai'      => $this->parseTanggal($row['tanggal_mulai'] ?? null),
                    'tanggal_akhir'      => $this->parseTanggal($row['tanggal_akhir'] ?? null),
                    'hari_kerja'         => (int) ($row['hari_kerja'] ?? 0),
                    'satuan_waktu'       => $row['satuan_waktu'] ?? 'hari_kalender',
                    'progres_persen'     => (float) ($row['progres_persen'] ?? 0),
                    'catatan'            => $row['catatan'] ?? null,
                    'created_by'         => auth()->id(),
                ];

                Pekerjaan::updateOrCreate(
                    ['no_spk' => $data['no_spk'], 'bidang_id' => $data['bidang_id']],
                    $data
                );

                $this->imported++;
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
                $this->skipped++;
            }
        }
    }

    private function lookupBidang(?string $nama): ?Bidang
    {
        if (!$nama) return null;
        $nama = strtolower(trim($nama));
        if (!isset($this->bidangCache[$nama])) {
            $this->bidangCache[$nama] = Bidang::whereRaw('LOWER(nama) LIKE ?', ["%{$nama}%"])->first();
        }
        return $this->bidangCache[$nama];
    }

    private function lookupJenis(?string $nama): ?JenisPekerjaan
    {
        if (!$nama) return null;
        $nama = strtolower(trim($nama));
        if (!isset($this->jenisCache[$nama])) {
            $this->jenisCache[$nama] = JenisPekerjaan::whereRaw('LOWER(nama) LIKE ?', ["%{$nama}%"])->first();
        }
        return $this->jenisCache[$nama];
    }

    private function lookupPerusahaan(?string $nama): ?Perusahaan
    {
        if (!$nama) return null;
        $nama = strtolower(trim($nama));
        if (!isset($this->perusahaanCache[$nama])) {
            $this->perusahaanCache[$nama] = Perusahaan::whereRaw('LOWER(nama) LIKE ?', ["%{$nama}%"])->first();
        }
        return $this->perusahaanCache[$nama];
    }

    private function lookupStatus(?string $kode): ?StatusPekerjaan
    {
        $kode = strtolower(trim($kode ?? 'berjalan'));
        if (!isset($this->statusCache[$kode])) {
            $this->statusCache[$kode] = StatusPekerjaan::where('kode', $kode)->first()
                ?? StatusPekerjaan::first();
        }
        return $this->statusCache[$kode];
    }

    private function parseAngka(mixed $val): float
    {
        if (is_numeric($val)) return (float) $val;
        return (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', (string) $val));
    }

    private function parseTanggal(mixed $val): ?string
    {
        if (!$val) return null;
        try {
            if (is_numeric($val)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$val))->format('Y-m-d');
            }
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
