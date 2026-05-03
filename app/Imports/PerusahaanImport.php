<?php

namespace App\Imports;

use App\Models\Master\Perusahaan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class PerusahaanImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public array $errors   = [];
    public int   $imported = 0;
    public int   $skipped  = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            $nama = trim($row['nama'] ?? '');
            if (!$nama) {
                $this->errors[] = "Baris {$rowNum}: Kolom 'nama' wajib diisi.";
                $this->skipped++;
                continue;
            }

            try {
                Perusahaan::updateOrCreate(
                    ['nama' => $nama],
                    [
                        'npwp'           => $row['npwp'] ?? null,
                        'alamat'         => $row['alamat'] ?? null,
                        'no_telp'        => $row['no_telp'] ?? null,
                        'email'          => $row['email'] ?? null,
                        'direktur'       => $row['direktur'] ?? null,
                        'is_blacklisted' => strtolower($row['blacklist'] ?? 'tidak') === 'ya',
                    ]
                );
                $this->imported++;
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
                $this->skipped++;
            }
        }
    }
}
