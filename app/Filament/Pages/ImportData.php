<?php

namespace App\Filament\Pages;

use App\Imports\HariLiburImport;
use App\Imports\PerusahaanImport;
use App\Imports\PekerjaanImport;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class ImportData extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Import Data';
    protected static ?string $title           = 'Import Data dari Excel';
    protected static ?string $navigationGroup = 'Data';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.pages.import-data';

    public ?array $data = [];

    // Result state
    public ?array $importResult = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Upload File')->schema([
                FileUpload::make('file_pekerjaan')
                    ->label('File Pekerjaan (.xlsx)')
                    ->disk('local')
                    ->directory('imports/temp')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->maxSize(10240)
                    ->nullable()
                    ->helperText('Kolom wajib: bidang, nama_pekerjaan. Opsional: jenis_pekerjaan, perusahaan, status, tahun_anggaran, nilai_pagu, nilai_kontrak, no_spk, tanggal_spk, tanggal_mulai, tanggal_akhir, hari_kerja, progres_persen'),

                FileUpload::make('file_perusahaan')
                    ->label('File Perusahaan (.xlsx)')
                    ->disk('local')
                    ->directory('imports/temp')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->maxSize(10240)
                    ->nullable()
                    ->helperText('Kolom wajib: nama. Opsional: npwp, alamat, no_telp, email, direktur, blacklist (ya/tidak)'),

                FileUpload::make('file_hari_libur')
                    ->label('File Hari Libur (.xlsx)')
                    ->disk('local')
                    ->directory('imports/temp')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->maxSize(5120)
                    ->nullable()
                    ->helperText('Kolom wajib: tanggal (format dd/mm/yyyy), nama'),
            ]),
        ])->statePath('data');
    }

    public function import(): void
    {
        $data   = $this->form->getState();
        $result = [];

        if ($data['file_pekerjaan'] ?? null) {
            $path    = storage_path('app/private/' . $data['file_pekerjaan']);
            $importer = new PekerjaanImport();
            Excel::import($importer, $path);
            $result['pekerjaan'] = [
                'imported' => $importer->imported,
                'skipped'  => $importer->skipped,
                'errors'   => $importer->errors,
            ];
        }

        if ($data['file_perusahaan'] ?? null) {
            $path     = storage_path('app/private/' . $data['file_perusahaan']);
            $importer = new PerusahaanImport();
            Excel::import($importer, $path);
            $result['perusahaan'] = [
                'imported' => $importer->imported,
                'skipped'  => $importer->skipped,
                'errors'   => $importer->errors,
            ];
        }

        if ($data['file_hari_libur'] ?? null) {
            $path     = storage_path('app/private/' . $data['file_hari_libur']);
            $importer = new HariLiburImport();
            Excel::import($importer, $path);
            $result['hari_libur'] = [
                'imported' => $importer->imported,
                'skipped'  => $importer->skipped,
                'errors'   => $importer->errors,
            ];
        }

        if (empty($result)) {
            Notification::make()
                ->title('Tidak ada file yang diupload')
                ->warning()
                ->send();
            return;
        }

        $this->importResult = $result;
        $this->form->fill();

        $totalImported = collect($result)->sum('imported');
        $totalSkipped  = collect($result)->sum('skipped');

        Notification::make()
            ->title("Import selesai: {$totalImported} baris berhasil, {$totalSkipped} dilewati")
            ->success()
            ->send();
    }

    public function downloadTemplatePekerjaan(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->download(
            public_path('templates/template-pekerjaan.xlsx'),
            'template-import-pekerjaan.xlsx'
        );
    }
}
