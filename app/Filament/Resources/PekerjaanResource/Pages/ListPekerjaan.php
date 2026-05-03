<?php

namespace App\Filament\Resources\PekerjaanResource\Pages;

use App\Filament\Resources\PekerjaanResource;
use App\Models\FilterPreset;
use App\Services\KontrakParserService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListPekerjaan extends ListRecords
{
    protected static string $resource = PekerjaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importKontrak')
                ->label('Import dari Kontrak')
                ->icon('heroicon-o-document-arrow-up')
                ->color('gray')
                ->modalHeading('Import Data dari Kontrak / Surat Dinas')
                ->modalDescription('Upload file PDF kontrak atau surat dinas. AI akan mengekstrak data secara otomatis — hasilnya tetap perlu direview sebelum disimpan.')
                ->modalWidth('lg')
                ->form([
                    FileUpload::make('file')
                        ->label('File PDF Kontrak / Surat Dinas')
                        ->disk('local')
                        ->directory('tmp/kontrak')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)
                        ->required()
                        ->helperText('Maksimal 10 MB. Harus PDF digital (bukan hasil scan/foto).'),

                    Placeholder::make('info')
                        ->label('')
                        ->content('💡 Setelah upload, AI akan membaca dokumen dan mengisi form otomatis. Kamu bisa mengubah field yang tidak tepat sebelum menyimpan.'),
                ])
                ->modalSubmitActionLabel('Proses Dokumen')
                ->action(function (array $data) {
                    // FileUpload returns path relative to disk root, e.g. "tmp/kontrak/file.pdf"
                    $relativePath = is_array($data['file']) ? reset($data['file']) : $data['file'];
                    $path = Storage::disk('local')->path($relativePath);

                    if (!file_exists($path)) {
                        Notification::make()
                            ->title('File tidak ditemukan')
                            ->body('Gagal membaca file yang diupload. Coba lagi.')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $parsed = app(KontrakParserService::class)->parse($path);
                        session()->put('kontrak_import', $parsed);

                        $jumlahTermin     = count($parsed['termin_pembayaran'] ?? []);
                        $jumlahMilestone  = count($parsed['milestones'] ?? []);

                        Notification::make()
                            ->title('Dokumen berhasil dibaca')
                            ->body("Data pekerjaan diekstrak. Ditemukan {$jumlahTermin} termin & {$jumlahMilestone} milestone — akan otomatis dibuat setelah disimpan.")
                            ->success()
                            ->send();

                        $this->redirect(PekerjaanResource::getUrl('create'));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal memproses dokumen')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tahunIni = date('Y');

        $tabs = [
            'semua' => Tab::make('Semua'),

            'tahun_ini' => Tab::make('Tahun ' . $tahunIni)
                ->modifyQueryUsing(fn (Builder $q) => $q->where('tahun_anggaran', $tahunIni)),

            'aktif' => Tab::make('Sedang Berjalan')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereHas(
                    'statusPekerjaan', fn ($sq) => $sq->where('is_final', false)
                )),

            'deadline_dekat' => Tab::make('Deadline Dekat')
                ->modifyQueryUsing(fn (Builder $q) => $q
                    ->whereNotNull('tanggal_akhir')
                    ->whereBetween('tanggal_akhir', [now(), now()->addDays(14)])
                    ->whereHas('statusPekerjaan', fn ($sq) => $sq->where('is_final', false))
                ),

            'terlambat' => Tab::make('Terlambat')
                ->modifyQueryUsing(fn (Builder $q) => $q
                    ->whereNotNull('tanggal_akhir')
                    ->where('tanggal_akhir', '<', now())
                    ->whereHas('statusPekerjaan', fn ($sq) => $sq->where('is_final', false))
                ),

            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereHas(
                    'statusPekerjaan', fn ($sq) => $sq->where('is_final', true)
                )),
        ];

        foreach (FilterPreset::forUser('pekerjaan') as $preset) {
            $filters = $preset->filters;
            $tabs['preset_' . $preset->id] = Tab::make('⭐ ' . $preset->nama)
                ->modifyQueryUsing(function (Builder $q) use ($filters) {
                    if (!empty($filters['bidang_id'])) {
                        $q->where('bidang_id', $filters['bidang_id']);
                    }
                    if (!empty($filters['tahun_anggaran'])) {
                        $q->where('tahun_anggaran', $filters['tahun_anggaran']);
                    }
                    if (!empty($filters['status_pekerjaan_id'])) {
                        $q->where('status_pekerjaan_id', $filters['status_pekerjaan_id']);
                    }
                    return $q;
                });
        }

        return $tabs;
    }
}
