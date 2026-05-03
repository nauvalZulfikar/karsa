<?php

namespace App\Filament\Resources\PekerjaanResource\Pages;

use App\Filament\Resources\PekerjaanResource;
use App\Models\Master\Perusahaan;
use App\Models\MilestonePekerjaan;
use App\Models\Pekerjaan;
use App\Models\TerminPembayaran;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePekerjaan extends CreateRecord
{
    protected static string $resource = PekerjaanResource::class;

    public ?array $pendingTermin     = null;
    public ?array $pendingMilestones = null;

    public function mount(): void
    {
        parent::mount();

        if (session()->has('kontrak_import')) {
            $parsed = session()->pull('kontrak_import');

            // Backward-compat: support flat shape from old parser
            if (!isset($parsed['pekerjaan'])) {
                $parsed = ['pekerjaan' => $parsed, 'termin_pembayaran' => [], 'milestones' => []];
            }

            $this->pendingTermin     = $parsed['termin_pembayaran'] ?? [];
            $this->pendingMilestones = $parsed['milestones'] ?? [];

            $this->prefillFromKontrak($parsed['pekerjaan']);
        }
    }

    private function prefillFromKontrak(array $parsed): void
    {
        $formData = [];

        $map = [
            'nama_pekerjaan', 'no_spk', 'tanggal_spk', 'no_spmk', 'tanggal_spmk',
            'nilai_pagu', 'nilai_kontrak', 'hari_kerja', 'satuan_waktu',
            'tanggal_mulai', 'tanggal_akhir', 'tahun_anggaran', 'catatan',
        ];

        foreach ($map as $key) {
            if (!empty($parsed[$key])) {
                $formData[$key] = $parsed[$key];
            }
        }

        // Match vendor name to existing Perusahaan
        $vendorMatched = false;
        if (!empty($parsed['nama_perusahaan'])) {
            $perusahaan = Perusahaan::where('nama', 'like', '%' . $parsed['nama_perusahaan'] . '%')
                ->first();

            if (!$perusahaan) {
                $words = explode(' ', $parsed['nama_perusahaan']);
                if (count($words) >= 2) {
                    $perusahaan = Perusahaan::where('nama', 'like', '%' . $words[0] . ' ' . $words[1] . '%')
                        ->first();
                }
            }

            if ($perusahaan) {
                $formData['perusahaan_id'] = $perusahaan->id;
                $vendorMatched             = true;
            }
        }

        $this->form->fill($formData);

        $vendorInfo = !empty($parsed['nama_perusahaan'])
            ? " Vendor \"{$parsed['nama_perusahaan']}\" " . ($vendorMatched ? 'cocok di database.' : 'tidak ditemukan — pilih manual.')
            : '';

        Notification::make()
            ->title('Data kontrak berhasil diekstrak')
            ->body('Periksa form di bawah, lengkapi yang masih kosong, lalu Simpan.' . $vendorInfo)
            ->warning()
            ->persistent()
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Pekerjaan $pekerjaan */
        $pekerjaan = $this->record;

        $createdTermin    = 0;
        $createdMilestone = 0;

        if (!empty($this->pendingTermin) && $pekerjaan->nilai_kontrak) {
            $createdTermin = $this->createTermin($pekerjaan);
        }

        if (!empty($this->pendingMilestones) && $pekerjaan->tanggal_mulai) {
            $createdMilestone = $this->createMilestones($pekerjaan);
        }

        if ($createdTermin || $createdMilestone) {
            Notification::make()
                ->title('Auto-generate selesai')
                ->body("Berhasil membuat {$createdTermin} termin pembayaran & {$createdMilestone} milestone dari kontrak.")
                ->success()
                ->send();
        }
    }

    private function createTermin(Pekerjaan $pekerjaan): int
    {
        $count        = 0;
        $nilaiKontrak = (float) $pekerjaan->nilai_kontrak;

        foreach ($this->pendingTermin as $t) {
            try {
                TerminPembayaran::updateOrCreate(
                    [
                        'pekerjaan_id' => $pekerjaan->id,
                        'nomor_termin' => $t['nomor_termin'],
                    ],
                    [
                        'nama_termin'           => $t['nama_termin'],
                        'persen_progres_syarat' => $t['persen_progres_syarat'],
                        'nilai_termin'          => round(($t['persen_nilai'] / 100) * $nilaiKontrak, 2),
                        'status'                => 'draft',
                        'catatan_pptk'          => $t['syarat_dokumen'] ?? null,
                        'created_by'            => auth()->id(),
                    ]
                );
                $count++;
            } catch (\Throwable) {
                // skip rows that fail (e.g. duplicate nomor_termin handled by unique)
            }
        }

        return $count;
    }

    private function createMilestones(Pekerjaan $pekerjaan): int
    {
        $count = 0;
        $mulai = Carbon::parse($pekerjaan->tanggal_mulai);

        foreach ($this->pendingMilestones as $m) {
            try {
                MilestonePekerjaan::create([
                    'pekerjaan_id'          => $pekerjaan->id,
                    'urutan'                => $m['urutan'],
                    'nama'                  => $m['nama'],
                    'deskripsi'             => $m['deskripsi'],
                    'tanggal_target'        => $mulai->copy()->addDays($m['hari_setelah_mulai'])->format('Y-m-d'),
                    'progres_target_persen' => $m['progres_target_persen'],
                    'sumber'                => $m['sumber'],
                    'status'                => 'belum_mulai',
                ]);
                $count++;
            } catch (\Throwable) {
                // skip on error
            }
        }

        return $count;
    }
}
