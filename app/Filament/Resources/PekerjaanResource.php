<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PekerjaanResource\Pages;
use App\Models\Master\Bidang;
use App\Models\Master\JenisPekerjaan;
use App\Models\Master\Perusahaan;
use App\Models\Master\StatusPekerjaan;
use App\Models\Pekerjaan;
use App\Services\KickoffParserService;
use App\Models\FilterPreset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PekerjaanResource extends Resource
{
    protected static ?string $model = Pekerjaan::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Pekerjaan';
    protected static ?string $modelLabel = 'Pekerjaan';
    protected static ?string $pluralModelLabel = 'Pekerjaan';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Upload Dokumen Kick-Off')
                ->description('Upload proposal vendor atau kontrak dinas — sistem akan mengisi form otomatis.')
                ->schema([
                    Forms\Components\FileUpload::make('kickoff_dokumen_path')
                        ->label('Proposal / Kontrak (PDF)')
                        ->disk('local')
                        ->directory('kickoff/temp')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)
                        ->nullable()
                        ->helperText('Upload PDF untuk auto-fill form di bawah. Isi manual jika tidak ada file.'),
                ])
                ->collapsible()
                ->visibleOn('create'),

            Forms\Components\Section::make('Informasi Umum')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('bidang_id')
                            ->label('Bidang')
                            ->options(Bidang::active()->pluck('nama', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('jenis_pekerjaan_id')
                            ->label('Jenis Pekerjaan')
                            ->options(JenisPekerjaan::active()->ordered()->pluck('nama', 'id'))
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nama')
                                    ->label('Nama Jenis Pekerjaan')
                                    ->required()
                                    ->maxLength(150),
                                Forms\Components\Textarea::make('keterangan')
                                    ->label('Keterangan (opsional)')
                                    ->rows(2),
                            ])
                            ->createOptionUsing(fn (array $data) => JenisPekerjaan::create([
                                'nama'        => $data['nama'],
                                'keterangan'  => $data['keterangan'] ?? null,
                                'is_active'   => true,
                                'urutan'      => (JenisPekerjaan::max('urutan') ?? 0) + 1,
                            ])->id),
                    ]),

                    Forms\Components\TextInput::make('nama_pekerjaan')
                        ->label('Nama Pekerjaan')
                        ->required()
                        ->maxLength(300)
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('perusahaan_id')
                            ->label('Perusahaan')
                            ->options(Perusahaan::aktif()->pluck('nama', 'id'))
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nama')
                                    ->label('Nama Perusahaan')
                                    ->required()
                                    ->maxLength(200),
                                Forms\Components\Select::make('jenis')
                                    ->label('Jenis')
                                    ->options(['PT' => 'PT', 'CV' => 'CV', 'Perorangan' => 'Perorangan', 'Lainnya' => 'Lainnya'])
                                    ->required()
                                    ->default('PT'),
                                Forms\Components\TextInput::make('npwp')->label('NPWP')->maxLength(20),
                                Forms\Components\TextInput::make('pic_nama')->label('Nama PIC')->maxLength(100),
                                Forms\Components\TextInput::make('pic_telp')->label('No HP PIC')->maxLength(20),
                            ])
                            ->createOptionUsing(fn (array $data) => Perusahaan::create($data + ['is_blacklisted' => false])->id),

                        Forms\Components\Select::make('status_pekerjaan_id')
                            ->label('Status')
                            ->options(StatusPekerjaan::ordered()->pluck('nama', 'id'))
                            ->nullable()
                            ->searchable()
                            ->preload(),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('tahun_anggaran')
                            ->label('Tahun Anggaran')
                            ->numeric()
                            ->default(2026)
                            ->required()
                            ->minValue(2020)
                            ->maxValue(2099),

                        Forms\Components\TextInput::make('progres_persen')
                            ->label('Progress (%)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                    ]),
                ]),

            Forms\Components\Section::make('Nilai Anggaran')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('nilai_pagu')
                            ->label('Nilai Pagu (Rp)')
                            ->numeric()
                            ->nullable()
                            ->prefix('Rp'),

                        Forms\Components\TextInput::make('nilai_kontrak')
                            ->label('Nilai Kontrak (Rp)')
                            ->numeric()
                            ->nullable()
                            ->prefix('Rp')
                            ->helperText('Tidak boleh melebihi nilai pagu'),
                    ]),
                ]),

            Forms\Components\Section::make('SPK & SPMK')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('no_spk')
                            ->label('Nomor SPK')
                            ->nullable()
                            ->maxLength(150),

                        Forms\Components\DatePicker::make('tanggal_spk')
                            ->label('Tanggal SPK')
                            ->nullable()
                            ->displayFormat('d/m/Y'),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('no_spmk')
                            ->label('Nomor SPMK')
                            ->nullable()
                            ->maxLength(150),

                        Forms\Components\DatePicker::make('tanggal_spmk')
                            ->label('Tanggal SPMK')
                            ->nullable()
                            ->displayFormat('d/m/Y'),
                    ]),
                ])
                ->columns(1),

            Forms\Components\Section::make('Waktu Pelaksanaan')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai')
                            ->nullable()
                            ->displayFormat('d/m/Y'),

                        Forms\Components\DatePicker::make('tanggal_akhir')
                            ->label('Tanggal Akhir')
                            ->nullable()
                            ->displayFormat('d/m/Y')
                            ->after('tanggal_mulai'),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('hari_kerja')
                            ->label('Durasi')
                            ->numeric()
                            ->nullable()
                            ->suffix('hari'),

                        Forms\Components\Select::make('satuan_waktu')
                            ->label('Satuan Waktu')
                            ->options([
                                'hari_kerja' => 'Hari Kerja',
                                'hari_kalender' => 'Hari Kalender',
                            ])
                            ->default('hari_kerja'),
                    ]),
                ]),

            Forms\Components\Section::make('Catatan')
                ->schema([
                    Forms\Components\Textarea::make('catatan')
                        ->label('Catatan')
                        ->nullable()
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_pekerjaan')
                    ->label('Nama Pekerjaan')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->wrap(),

                Tables\Columns\TextColumn::make('bidang.nama')
                    ->label('Bidang')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('perusahaan.nama')
                    ->label('Perusahaan')
                    ->limit(35)
                    ->searchable(),

                Tables\Columns\TextColumn::make('nilai_pagu')
                    ->label('Nilai Pagu')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status_waktu_label')
                    ->label('Traffic Light')
                    ->badge()
                    ->color(fn ($record) => $record->status_waktu_color)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('sisa_hari')
                    ->label('Sisa Hari Kerja')
                    ->suffix(fn ($record) => $record->sisa_hari !== null ? ' hari' : '')
                    ->color(fn ($record) => match($record->status_waktu) {
                        'kritis', 'terlambat' => 'danger',
                        'waspada' => 'warning',
                        'aman', 'selesai' => 'success',
                        default => 'gray',
                    })
                    ->sortable(false),

                Tables\Columns\TextColumn::make('statusPekerjaan.nama')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => match ($record->statusPekerjaan?->warna) {
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger'  => 'danger',
                        'info'    => 'info',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('tanggal_akhir')
                    ->label('Deadline')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('progres_persen')
                    ->label('Progress')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tahun_anggaran')
                    ->label('Tahun')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_akhir', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('bidang_id')
                    ->label('Bidang')
                    ->options(Bidang::active()->pluck('nama', 'id')),

                Tables\Filters\SelectFilter::make('status_pekerjaan_id')
                    ->label('Status')
                    ->options(StatusPekerjaan::ordered()->pluck('nama', 'id')),

                Tables\Filters\SelectFilter::make('tahun_anggaran')
                    ->label('Tahun')
                    ->options([2025 => '2025', 2026 => '2026', 2027 => '2027']),

                Tables\Filters\SelectFilter::make('perusahaan_id')
                    ->label('Perusahaan')
                    ->options(Perusahaan::pluck('nama', 'id'))
                    ->searchable(),

                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\Filter::make('deadline_minggu_ini')
                    ->label('Deadline Minggu Ini')
                    ->query(fn ($query) => $query
                        ->whereNotNull('tanggal_akhir')
                        ->whereBetween('tanggal_akhir', [
                            now()->startOfWeek(),
                            now()->endOfWeek(),
                        ])),

                Tables\Filters\Filter::make('sudah_terlambat')
                    ->label('Sudah Terlambat')
                    ->query(fn ($query) => $query
                        ->whereNotNull('tanggal_akhir')
                        ->where('tanggal_akhir', '<', now())
                        ->whereHas('statusPekerjaan', fn ($q) => $q->where('kode', '!=', 'selesai'))),

                Tables\Filters\Filter::make('selesai_bulan_ini')
                    ->label('Selesai Bulan Ini')
                    ->query(fn ($query) => $query
                        ->whereHas('statusPekerjaan', fn ($q) => $q->where('kode', 'selesai'))
                        ->whereMonth('updated_at', now()->month)
                        ->whereYear('updated_at', now()->year)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('simpan_preset')
                    ->label('Simpan Filter sebagai Preset')
                    ->icon('heroicon-o-bookmark')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Preset')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Contoh: Bidang Saya Tahun 2026'),
                        Forms\Components\Select::make('bidang_id')
                            ->label('Bidang')
                            ->options(\App\Models\Master\Bidang::active()->pluck('nama', 'id'))
                            ->nullable(),
                        Forms\Components\Select::make('tahun_anggaran')
                            ->label('Tahun Anggaran')
                            ->options(array_combine(range(date('Y') - 2, date('Y') + 1), range(date('Y') - 2, date('Y') + 1)))
                            ->nullable(),
                        Forms\Components\Select::make('status_pekerjaan_id')
                            ->label('Status')
                            ->options(\App\Models\Master\StatusPekerjaan::ordered()->pluck('nama', 'id'))
                            ->nullable(),
                    ])
                    ->action(function (array $data) {
                        FilterPreset::create([
                            'user_id'  => auth()->id(),
                            'resource' => 'pekerjaan',
                            'nama'     => $data['nama'],
                            'filters'  => array_filter([
                                'bidang_id'           => $data['bidang_id'] ?? null,
                                'tahun_anggaran'      => $data['tahun_anggaran'] ?? null,
                                'status_pekerjaan_id' => $data['status_pekerjaan_id'] ?? null,
                            ]),
                        ]);
                        Notification::make()->title('Preset tersimpan — muat ulang halaman untuk melihat tab baru')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\PekerjaanResource\RelationManagers\PersonilRelationManager::class,
            \App\Filament\Resources\PekerjaanResource\RelationManagers\VendorRelationManager::class,
            \App\Filament\Resources\PekerjaanResource\RelationManagers\RencanaPengadaanRelationManager::class,
            \App\Filament\Resources\PekerjaanResource\RelationManagers\RealisasiPengadaanRelationManager::class,
            \App\Filament\Resources\PekerjaanResource\RelationManagers\DokumenRelationManager::class,
            \App\Filament\Resources\PekerjaanResource\RelationManagers\TerminPembayaranRelationManager::class,
            \App\Filament\Resources\PekerjaanResource\RelationManagers\MilestoneRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPekerjaan::route('/'),
            'create' => Pages\CreatePekerjaan::route('/create'),
            'view' => Pages\ViewPekerjaan::route('/{record}'),
            'edit' => Pages\EditPekerjaan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withTrashed();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->is_active && ! $user->hasRole('vendor');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nama_pekerjaan', 'no_spk', 'no_spmk', 'catatan'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->nama_pekerjaan;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Bidang'   => $record->bidang?->nama ?? '-',
            'Status'   => $record->statusPekerjaan?->nama ?? '-',
            'Progres'  => ($record->progres_persen ?? 0) . '%',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['bidang', 'statusPekerjaan']);
    }
}
