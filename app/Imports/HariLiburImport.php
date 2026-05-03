<?php

namespace App\Imports;

use App\Models\Master\HariLibur;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class HariLiburImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public array $errors   = [];
    public int   $imported = 0;
    public int   $skipped  = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            try {
                $tanggal = null;
                $raw     = $row['tanggal'] ?? null;

                if (!$raw) {
                    $this->errors[] = "Baris {$rowNum}: Kolom 'tanggal' wajib diisi.";
                    $this->skipped++;
                    continue;
                }

                if (is_numeric($raw)) {
                    $tanggal = Carbon::instance(
                        \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$raw)
                    )->format('Y-m-d');
                } else {
                    $tanggal = Carbon::parse($raw)->format('Y-m-d');
                }

                $nama = trim($row['nama'] ?? $row['keterangan'] ?? '');
                if (!$nama) {
                    $this->errors[] = "Baris {$rowNum}: Kolom 'nama' wajib diisi.";
                    $this->skipped++;
                    continue;
                }

                HariLibur::updateOrCreate(
                    ['tanggal' => $tanggal],
                    ['nama' => $nama]
                );
                $this->imported++;
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
                $this->skipped++;
            }
        }
    }
}
