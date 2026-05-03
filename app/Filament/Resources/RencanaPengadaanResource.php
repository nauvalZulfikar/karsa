<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RencanaPengadaanResource\Pages;
use App\Models\RencanaPengadaan;
use App\Models\Pekerjaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RencanaPengadaanResource extends Resource
{
    protected static ?string $model = RencanaPengadaan::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Rencana Pengadaan';
    protected static ?string $modelLabel = 'Rencana Pengadaan';
    protected static ?string $pluralModelLabel = 'Rencana Pengadaan';
    protected static ?string $navigationGroup = 'Pengadaan';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('pekerjaan_id')
                ->label('Proyek')
                ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                ->searchable()
                ->required(),

            Forms\Components\TextInput::make('nama_item')
                ->label('Nama Item')
                ->required()
                ->maxLength(255),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('satuan')
                    ->label('Satuan')
                    ->placeholder('sak / m3 / batang / liter')
                    ->required()
                    ->maxLength(50),

                Forms\Components\TextInput::make('volume_rencana')
                    ->label('Volume Rencana')
                    ->numeric()
                    ->required()
                    ->minValue(0.001),
            ]),

            Forms\Components\TextInput::make('harga_satuan_rencana')
                ->label('Harga Satuan (Rp)')
                ->numeric()
                ->required()
                ->prefix('Rp')
                ->minValue(0),

            Forms\Components\Textarea::make('keterangan')
                ->label('Keterangan')
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
                    ->limit(40),

                Tables\Columns\TextColumn::make('nama_item')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('volume_rencana')
                    ->label('Volume Rencana')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . $record->satuan),

                Tables\Columns\TextColumn::make('harga_satuan_rencana')
                    ->label('Harga Satuan')
                    ->money('IDR', locale: 'id'),

                Tables\Columns\TextColumn::make('total_rencana')
                    ->label('Total Rencana')
                    ->getStateUsing(fn ($record) => $record->total_rencana)
                    ->money('IDR', locale: 'id'),

                Tables\Columns\TextColumn::make('total_volume_dipakai')
                    ->label('Terpakai')
                    ->getStateUsing(fn ($record) => number_format($record->total_volume_dipakai, 2) . ' ' . $record->satuan),

                Tables\Columns\IconColumn::make('is_alert')
                    ->label('Alert')
                    ->getStateUsing(fn ($record) => $record->is_alert)
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pekerjaan_id')
                    ->label('Proyek')
                    ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                    ->searchable(),

                Tables\Filters\Filter::make('ada_alert')
                    ->label('Ada Selisih Volume')
                    ->query(function ($query) {
                        $query->whereHas('realisasi', function ($q) {
                            $q->where('status', 'verified');
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRencanaPengadaans::route('/'),
            'create' => Pages\CreateRencanaPengadaan::route('/create'),
            'edit'   => Pages\EditRencanaPengadaan::route('/{record}/edit'),
        ];
    }
}
