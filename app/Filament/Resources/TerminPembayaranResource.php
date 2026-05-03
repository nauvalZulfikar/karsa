<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerminPembayaranResource\Pages;
use App\Models\Pekerjaan;
use App\Models\TerminPembayaran;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TerminPembayaranResource extends Resource
{
    protected static ?string $model = TerminPembayaran::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Termin Pembayaran';
    protected static ?string $modelLabel = 'Termin Pembayaran';
    protected static ?string $pluralModelLabel = 'Termin Pembayaran';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('pekerjaan_id')
                ->label('Proyek')
                ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                ->searchable()
                ->required(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('nomor_termin')
                    ->label('Nomor Termin')
                    ->numeric()
                    ->required()
                    ->minValue(1),

                Forms\Components\TextInput::make('nama_termin')
                    ->label('Nama Termin')
                    ->required()
                    ->maxLength(100),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('nilai_termin')
                    ->label('Nilai Termin (Rp)')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->minValue(0),

                Forms\Components\TextInput::make('persen_progres_syarat')
                    ->label('Syarat Progres (%)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
            ]),

            Forms\Components\Grid::make(3)->schema([
                Forms\Components\DatePicker::make('tanggal_pengajuan')
                    ->label('Tgl Pengajuan')
                    ->nullable(),

                Forms\Components\DatePicker::make('tanggal_persetujuan')
                    ->label('Tgl Persetujuan')
                    ->nullable(),

                Forms\Components\DatePicker::make('tanggal_bayar')
                    ->label('Tgl Bayar')
                    ->nullable(),
            ]),

            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(TerminPembayaran::$statusOptions)
                ->required(),

            Forms\Components\Textarea::make('catatan_pptk')
                ->label('Catatan PPTK')
                ->rows(2)
                ->nullable(),

            Forms\Components\Textarea::make('catatan_ppk')
                ->label('Catatan PPK')
                ->rows(2)
                ->nullable(),

            Forms\Components\FileUpload::make('dokumen_path')
                ->label('Dokumen Pendukung')
                ->disk('local')
                ->directory('dokumen/termin')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(20480)
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pekerjaan.nama_pekerjaan')
                    ->label('Proyek')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('nama_termin')
                    ->label('Termin')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nilai_termin')
                    ->label('Nilai')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('persen_progres_syarat')
                    ->label('Syarat')
                    ->formatStateUsing(fn ($state) => $state . '%'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => TerminPembayaran::$statusOptions[$state] ?? $state)
                    ->badge()
                    ->color(fn ($record) => $record->status_color)
                    ->sortable(),

                Tables\Columns\TextColumn::make('tanggal_pengajuan')
                    ->label('Tgl Pengajuan')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tanggal_bayar')
                    ->label('Tgl Bayar')
                    ->date('d M Y')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Disetujui Oleh')
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pekerjaan_id')
                    ->label('Proyek')
                    ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(TerminPembayaran::$statusOptions),
            ])
            ->actions([
                Tables\Actions\Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'diajukan')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_persetujuan')
                            ->label('Tanggal Persetujuan')
                            ->required()
                            ->default(today()),
                        Forms\Components\Textarea::make('catatan_ppk')
                            ->label('Catatan PPK')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status'              => 'disetujui',
                            'tanggal_persetujuan' => $data['tanggal_persetujuan'],
                            'catatan_ppk'         => $data['catatan_ppk'] ?? null,
                            'approved_by'         => auth()->id(),
                        ]);
                        Notification::make()->title('Termin disetujui')->success()->send();
                    }),

                Tables\Actions\Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'diajukan')
                    ->form([
                        Forms\Components\Textarea::make('catatan_ppk')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status'      => 'ditolak',
                            'catatan_ppk' => $data['catatan_ppk'],
                            'approved_by' => auth()->id(),
                        ]);
                        Notification::make()->title('Termin ditolak')->danger()->send();
                    }),

                Tables\Actions\Action::make('bayar')
                    ->label('Catat Bayar')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'disetujui')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_bayar')
                            ->label('Tanggal Pembayaran')
                            ->required()
                            ->default(today()),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status'        => 'dibayar',
                            'tanggal_bayar' => $data['tanggal_bayar'],
                        ]);
                        Notification::make()->title('Pembayaran dicatat')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('tanggal_pengajuan', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTerminPembayarans::route('/'),
            'create' => Pages\CreateTerminPembayaran::route('/create'),
            'edit'   => Pages\EditTerminPembayaran::route('/{record}/edit'),
        ];
    }
}
