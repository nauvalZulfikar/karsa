<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PekerjaanResource;
use App\Exports\LaporanHarianExport;
use App\Exports\PekerjaanExport;
use App\Exports\PengadaanExport;
use App\Exports\TerminPembayaranExport;
use App\Models\Master\Bidang;
use App\Models\Pekerjaan;
use App\Services\KontrakParserService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class DashboardActionsWidget extends Widget implements
    \Filament\Actions\Contracts\HasActions,
    \Filament\Forms\Contracts\HasForms
{
    use \Filament\Actions\Concerns\InteractsWithActions;
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static string $view = 'filament.widgets.dashboard-actions';
    protected static ?int $sort = -1;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 'full';

    public function importKontrakAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('importKontrak')
            ->label('📑 Import Kontrak')
            ->color('warning')
            ->size('lg')
            ->modalHeading('Import dari PDF Kontrak / Surat Dinas')
            ->modalDescription('Upload file PDF — AI akan mengekstrak nama proyek, nilai kontrak, durasi, termin, dan milestone otomatis.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Proses Dokumen')
            ->form([
                FileUpload::make('file')
                    ->label('File PDF Kontrak / Surat Dinas')
                    ->disk('local')
                    ->directory('tmp/kontrak')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->required()
                    ->helperText('Maksimal 10 MB. Hanya PDF digital (bukan hasil scan/foto).'),
                Placeholder::make('info')
                    ->label('')
                    ->content('💡 AI akan baca dokumen, ekstrak data, dan buka form Tambah Pekerjaan dengan field sudah terisi. Tinggal review & simpan.'),
            ])
            ->action(function (array $data) {
                $relativePath = is_array($data['file']) ? reset($data['file']) : $data['file'];
                $path = Storage::disk('local')->path($relativePath);
                if (!file_exists($path)) {
                    Notification::make()->title('File tidak ditemukan')->danger()->send();
                    return;
                }
                try {
                    $parsed = app(KontrakParserService::class)->parse($path);
                    session()->put('kontrak_import', $parsed);
                    $j1 = count($parsed['termin_pembayaran'] ?? []);
                    $j2 = count($parsed['milestones'] ?? []);
                    Notification::make()
                        ->title('Dokumen berhasil dibaca')
                        ->body("Ditemukan {$j1} termin & {$j2} milestone — akan dibuat otomatis setelah disimpan.")
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
            });
    }

    public function exportAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('export')
            ->label('📤 Export Data')
            ->color('gray')
            ->size('lg')
            ->modalHeading('Export Data')
            ->modalDescription('Pilih jenis data yang mau di-export.')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Download')
            ->form([
                Select::make('jenis')
                    ->label('Jenis Export')
                    ->options([
                        'pekerjaan_xlsx'  => '📊 Pekerjaan (Excel)',
                        'pekerjaan_pdf'   => '📄 Pekerjaan (PDF Landscape)',
                        'laporan'         => '📝 Laporan Harian (Excel)',
                        'pengadaan'       => '📦 Pengadaan (Excel)',
                        'termin'          => '💰 Termin Pembayaran (Excel)',
                    ])
                    ->required()
                    ->default('pekerjaan_xlsx'),
                Select::make('tahun')
                    ->label('Tahun Anggaran')
                    ->options(fn () => array_combine(
                        $years = range(date('Y') + 1, date('Y') - 5),
                        $years
                    ))
                    ->default(date('Y'))
                    ->required(),
            ])
            ->action(function (array $data) {
                $tahun = (int) $data['tahun'];
                try {
                    $tahunStr = (string) $tahun;
                    return match ($data['jenis']) {
                        'pekerjaan_xlsx' => Excel::download(new PekerjaanExport(tahun: $tahunStr), "pekerjaan_{$tahun}.xlsx"),
                        'pekerjaan_pdf'  => $this->exportPekerjaanPdf($tahun),
                        'laporan'        => Excel::download(new LaporanHarianExport(), "laporan_harian_{$tahun}.xlsx"),
                        'pengadaan'      => Excel::download(new PengadaanExport(), "pengadaan_{$tahun}.xlsx"),
                        'termin'         => Excel::download(new TerminPembayaranExport(), "termin_{$tahun}.xlsx"),
                    };
                } catch (\Throwable $e) {
                    Notification::make()->title('Export gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private function exportPekerjaanPdf(int $tahun)
    {
        $pekerjaans = Pekerjaan::with(['bidang', 'perusahaan', 'statusPekerjaan'])
            ->where('tahun_anggaran', $tahun)->get();
        $pdf = Pdf::loadView('exports.pekerjaan-pdf', compact('pekerjaans', 'tahun'))->setPaper('a4', 'landscape');
        return response()->streamDownload(fn () => print($pdf->output()), "pekerjaan_{$tahun}.pdf");
    }
}
