<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RealisasiPengadaanResource\Pages;
use App\Models\RealisasiPengadaan;
use App\Models\RencanaPengadaan;
use App\Models\Pekerjaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RealisasiPengadaanResource extends Resource
{
    protected static ?string $model = RealisasiPengadaan::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Realisasi Pengadaan';
    protected static ?string $modelLabel = 'Realisasi Pengadaan';
    protected static ?string $pluralModelLabel = 'Realisasi Pengadaan';
    protected static ?string $navigationGroup = 'Pengadaan';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Data Pengadaan')->schema([
                Forms\Components\Select::make('pekerjaan_id')
                    ->label('Proyek')
                    ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $set('rencana_pengadaan_id', null)),

                Forms\Components\Select::make('rencana_pengadaan_id')
                    ->label('Item Rencana')
                    ->options(function ($get) {
                        $pid = $get('pekerjaan_id');
                        if (!$pid) return [];
                        return RencanaPengadaan::where('pekerjaan_id', $pid)
                            ->pluck('nama_item', 'id');
                    })
                    ->searchable()
                    ->required(),

                Forms\Components\DatePicker::make('tanggal_realisasi')
                    ->label('Tanggal Realisasi')
                    ->required()
                    ->default(today()),
            ]),

            Forms\Components\Section::make('Volume & Harga')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('volume_beli')
                        ->label('Volume Dibeli')
                        ->numeric()
                        ->required()
                        ->minValue(0.001),

                    Forms\Components\TextInput::make('volume_dipakai')
                        ->label('Volume Dipakai')
                        ->numeric()
                        ->required()
                        ->minValue(0),

                    Forms\Components\TextInput::make('volume_sisa')
                        ->label('Volume Sisa')
                        ->numeric()
                        ->required()
                        ->minValue(0),
                ]),

                Forms\Components\TextInput::make('harga_aktual')
                    ->label('Harga Aktual per Satuan (Rp)')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->minValue(0),
            ]),

            Forms\Components\Section::make('Bukti Foto')->schema([
                Forms\Components\FileUpload::make('foto_invoice_path')
                    ->label('Foto Invoice/Struk')
                    ->image()
                    ->disk('local')
                    ->directory('pengadaan/invoice')
                    ->nullable()
                    ->maxSize(10240),

                Forms\Components\FileUpload::make('foto_material_path')
                    ->label('Foto Material di Lapangan')
                    ->image()
                    ->disk('local')
                    ->directory('pengadaan/material')
                    ->nullable()
                    ->maxSize(10240),
            ]),

            Forms\Components\Textarea::make('catatan_vendor')
                ->label('Catatan Vendor')
                ->rows(2)
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
                    ->limit(35),

                Tables\Columns\TextColumn::make('rencanaPengadaan.nama_item')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('perusahaan.nama')
                    ->label('Vendor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('tanggal_realisasi')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('volume_beli')
                    ->label('Vol Beli')
                    ->formatStateUsing(fn ($state) => number_format($state, 2)),

                Tables\Columns\TextColumn::make('harga_aktual')
                    ->label('Harga Aktual')
                    ->money('IDR', locale: 'id'),

                Tables\Columns\TextColumn::make('total_aktual')
                    ->label('Total Aktual')
                    ->getStateUsing(fn ($record) => $record->total_aktual)
                    ->money('IDR', locale: 'id'),

                Tables\Columns\TextColumn::make('status')
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

                Tables\Columns\IconColumn::make('is_markup_alert')
                    ->label('Markup')
                    ->getStateUsing(fn ($record) => $record->is_markup_alert)
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success')
                    ->tooltip(fn ($record) => $record->markup_persen !== null
                        ? 'Markup: ' . number_format($record->markup_persen, 1) . '%'
                        : null
                    ),

                Tables\Columns\ImageColumn::make('foto_invoice_path')
                    ->label('Invoice')
                    ->disk('local')
                    ->height(40)
                    ->width(60),

                Tables\Columns\ImageColumn::make('foto_material_path')
                    ->label('Material')
                    ->disk('local')
                    ->height(40)
                    ->width(60),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pekerjaan_id')
                    ->label('Proyek')
                    ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'submitted' => 'Menunggu',
                        'verified'  => 'Disetujui',
                        'rejected'  => 'Ditolak',
                    ]),

                Tables\Filters\Filter::make('markup_alert')
                    ->label('Ada Alert Markup')
                    ->query(function (Builder $query) {
                        $query->whereHas('rencanaPengadaan', function ($q) {
                            $q->whereRaw('realisasi_pengadaan.harga_aktual > rencana_pengadaan.harga_satuan_rencana * 1.15');
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label('Setujui')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'submitted')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status'      => 'verified',
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                        ]);
                        Notification::make()->title('Realisasi disetujui')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'submitted')
                    ->form([
                        Forms\Components\Textarea::make('catatan_pptk')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status'       => 'rejected',
                            'catatan_pptk' => $data['catatan_pptk'],
                            'verified_by'  => auth()->id(),
                            'verified_at'  => now(),
                        ]);
                        Notification::make()->title('Realisasi ditolak')->danger()->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRealisasiPengadaans::route('/'),
            'create' => Pages\CreateRealisasiPengadaan::route('/create'),
            'view'   => Pages\ViewRealisasiPengadaan::route('/{record}'),
            'edit'   => Pages\EditRealisasiPengadaan::route('/{record}/edit'),
        ];
    }
}
