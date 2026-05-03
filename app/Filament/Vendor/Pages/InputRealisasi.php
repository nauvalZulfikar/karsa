<?php

namespace App\Filament\Vendor\Pages;

use App\Models\Pekerjaan;
use App\Models\RencanaPengadaan;
use App\Models\RealisasiPengadaan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class InputRealisasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Pengadaan Barang';
    protected static ?string $title = 'Input Realisasi Pengadaan';
    protected static string $view = 'filament.vendor.pages.input-realisasi';
    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Pilih Proyek & Item')->schema([
                Select::make('pekerjaan_id')
                    ->label('Proyek')
                    ->options($this->getProyekOptions())
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $set('rencana_pengadaan_id', null)),

                Select::make('rencana_pengadaan_id')
                    ->label('Item Pengadaan')
                    ->options(function ($get) {
                        $pid = $get('pekerjaan_id');
                        if (!$pid) return [];
                        return RencanaPengadaan::where('pekerjaan_id', $pid)
                            ->pluck('nama_item', 'id');
                    })
                    ->required()
                    ->searchable(),

                DatePicker::make('tanggal_realisasi')
                    ->label('Tanggal Realisasi')
                    ->required()
                    ->default(today())
                    ->maxDate(today()),
            ]),

            Section::make('Volume & Harga')->schema([
                Grid::make(3)->schema([
                    TextInput::make('volume_beli')
                        ->label('Volume Dibeli')
                        ->numeric()
                        ->required()
                        ->minValue(0.001)
                        ->helperText('Jumlah yang dibeli hari ini'),

                    TextInput::make('volume_dipakai')
                        ->label('Volume Dipakai')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->helperText('Jumlah yang dipasang/dipakai'),

                    TextInput::make('volume_sisa')
                        ->label('Volume Sisa')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->helperText('Sisa di gudang/lapangan'),
                ]),

                TextInput::make('harga_aktual')
                    ->label('Harga per Satuan Aktual (Rp)')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->minValue(0),
            ]),

            Section::make('Bukti Foto')->schema([
                FileUpload::make('foto_invoice_path')
                    ->label('Foto Invoice / Struk Pembelian')
                    ->image()
                    ->disk('local')
                    ->directory('pengadaan/invoice')
                    ->maxSize(10240)
                    ->nullable()
                    ->helperText('Upload foto struk atau invoice pembelian'),

                FileUpload::make('foto_material_path')
                    ->label('Foto Material di Lapangan')
                    ->image()
                    ->disk('local')
                    ->directory('pengadaan/material')
                    ->maxSize(10240)
                    ->nullable()
                    ->helperText('Upload foto material yang sudah terpasang/tersimpan'),
            ]),

            Textarea::make('catatan_vendor')
                ->label('Catatan Tambahan')
                ->rows(2)
                ->nullable(),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        if (!$user->perusahaan_id) {
            Notification::make()
                ->title('Akun vendor tidak terhubung ke perusahaan')
                ->danger()
                ->send();
            return;
        }

        RealisasiPengadaan::create([
            'rencana_pengadaan_id' => $data['rencana_pengadaan_id'],
            'pekerjaan_id'         => $data['pekerjaan_id'],
            'perusahaan_id'        => $user->perusahaan_id,
            'tanggal_realisasi'    => $data['tanggal_realisasi'],
            'volume_beli'          => $data['volume_beli'],
            'harga_aktual'         => $data['harga_aktual'],
            'volume_dipakai'       => $data['volume_dipakai'],
            'volume_sisa'          => $data['volume_sisa'],
            'foto_invoice_path'    => $data['foto_invoice_path'] ?? null,
            'foto_material_path'   => $data['foto_material_path'] ?? null,
            'catatan_vendor'       => $data['catatan_vendor'] ?? null,
            'status'               => 'submitted',
            'created_by'           => $user->id,
        ]);

        $this->form->fill();

        Notification::make()
            ->title('Realisasi berhasil dikirim')
            ->body('Data pengadaan menunggu verifikasi PPTK.')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RealisasiPengadaan::query()
                    ->where('perusahaan_id', auth()->user()?->perusahaan_id)
                    ->with(['rencanaPengadaan', 'pekerjaan'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('pekerjaan.nama_pekerjaan')
                    ->label('Proyek')
                    ->limit(30),

                TextColumn::make('rencanaPengadaan.nama_item')
                    ->label('Item'),

                TextColumn::make('tanggal_realisasi')
                    ->label('Tanggal')
                    ->date('d M Y'),

                TextColumn::make('volume_beli')
                    ->label('Vol Beli')
                    ->formatStateUsing(fn ($state) => number_format($state, 2)),

                TextColumn::make('harga_aktual')
                    ->label('Harga')
                    ->money('IDR', locale: 'id'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'submitted' => 'warning',
                        'verified'  => 'success',
                        'rejected'  => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        'submitted' => 'Menunggu',
                        'verified'  => 'Disetujui',
                        'rejected'  => 'Ditolak',
                        default => $state,
                    }),

                TextColumn::make('catatan_pptk')
                    ->label('Catatan PPTK')
                    ->limit(40)
                    ->placeholder('-'),
            ])
            ->paginated([10, 25])
            ->defaultSort('created_at', 'desc');
    }

    private function getProyekOptions(): array
    {
        $perusahaanId = auth()->user()?->perusahaan_id;
        if (!$perusahaanId) return [];

        return Pekerjaan::whereHas('vendors', fn ($q) => $q->where('perusahaan.id', $perusahaanId))
            ->pluck('nama_pekerjaan', 'id')
            ->toArray();
    }
}
