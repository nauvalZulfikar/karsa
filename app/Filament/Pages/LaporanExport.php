<?php

namespace App\Filament\Pages;

use App\Exports\LaporanHarianExport;
use App\Exports\PekerjaanExport;
use App\Exports\PengadaanExport;
use App\Exports\TerminPembayaranExport;
use App\Models\Master\Bidang;
use App\Models\Pekerjaan;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanExport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Export Laporan';
    protected static ?string $title           = 'Export & Unduh Laporan';
    protected static ?string $navigationGroup = 'Data';
    protected static ?int    $navigationSort  = 1;
    protected static string  $view            = 'filament.pages.laporan-export';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'tahun'       => date('Y'),
            'dari'        => now()->startOfMonth()->format('Y-m-d'),
            'sampai'      => now()->format('Y-m-d'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Filter')->schema([
                Grid::make(3)->schema([
                    Select::make('bidang_id')
                        ->label('Bidang')
                        ->options(Bidang::active()->pluck('nama', 'id'))
                        ->placeholder('Semua Bidang')
                        ->nullable(),

                    Select::make('pekerjaan_id')
                        ->label('Proyek')
                        ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                        ->placeholder('Semua Proyek')
                        ->searchable()
                        ->nullable(),

                    Select::make('tahun')
                        ->label('Tahun Anggaran')
                        ->options(array_combine(
                            range(date('Y') - 3, date('Y') + 1),
                            range(date('Y') - 3, date('Y') + 1)
                        ))
                        ->default(date('Y')),
                ]),

                Grid::make(2)->schema([
                    DatePicker::make('dari')
                        ->label('Dari Tanggal')
                        ->default(now()->startOfMonth()),

                    DatePicker::make('sampai')
                        ->label('Sampai Tanggal')
                        ->default(now()),
                ]),
            ]),
        ])->statePath('data');
    }

    public function exportPekerjaanExcel(): BinaryFileResponse
    {
        $data = $this->form->getState();
        $filename = 'rekap-pekerjaan-' . ($data['tahun'] ?? date('Y')) . '.xlsx';

        return Excel::download(
            new PekerjaanExport($data['bidang_id'] ?? null, $data['tahun'] ?? null),
            $filename
        );
    }

    public function exportPekerjaanPdf(): StreamedResponse
    {
        $data = $this->form->getState();

        $rows = Pekerjaan::with(['bidang', 'perusahaan', 'statusPekerjaan'])
            ->when($data['bidang_id'] ?? null, fn ($q, $v) => $q->where('bidang_id', $v))
            ->when($data['tahun'] ?? null, fn ($q, $v) => $q->where('tahun_anggaran', $v))
            ->orderBy('bidang_id')
            ->orderBy('nama_pekerjaan')
            ->get();

        $bidangNama = isset($data['bidang_id'])
            ? Bidang::find($data['bidang_id'])?->nama
            : null;

        $pdf = Pdf::loadView('exports.pekerjaan-pdf', [
            'rows'      => $rows,
            'tahun'     => $data['tahun'] ?? null,
            'bidangNama'=> $bidangNama,
        ])->setPaper('a4', 'landscape');

        $filename = 'rekap-pekerjaan-' . ($data['tahun'] ?? date('Y')) . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename
        );
    }

    public function exportLaporanExcel(): BinaryFileResponse
    {
        $data     = $this->form->getState();
        $filename = 'laporan-harian-' . ($data['dari'] ?? date('Y-m-d')) . '.xlsx';

        return Excel::download(
            new LaporanHarianExport(
                $data['pekerjaan_id'] ?? null,
                $data['dari'] ?? null,
                $data['sampai'] ?? null,
            ),
            $filename
        );
    }

    public function exportPengadaanExcel(): BinaryFileResponse
    {
        $data     = $this->form->getState();
        $filename = 'rekap-pengadaan.xlsx';

        return Excel::download(
            new PengadaanExport($data['pekerjaan_id'] ?? null),
            $filename
        );
    }

    public function exportTerminExcel(): BinaryFileResponse
    {
        $data     = $this->form->getState();
        $filename = 'rekap-termin-pembayaran.xlsx';

        return Excel::download(
            new TerminPembayaranExport($data['pekerjaan_id'] ?? null),
            $filename
        );
    }
}
