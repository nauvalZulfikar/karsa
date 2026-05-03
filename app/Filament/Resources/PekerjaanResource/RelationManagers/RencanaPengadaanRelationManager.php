<?php

namespace App\Filament\Resources\PekerjaanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RencanaPengadaanRelationManager extends RelationManager
{
    protected static string $relationship = 'rencanaPengadaan';
    protected static ?string $title = 'Rencana Pengadaan Barang';

    public function form(Form $form): Form
    {
        return $form->schema([
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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->label('Terpakai (Verified)')
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('lihat_realisasi')
                    ->label('Realisasi')
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn ($record) => route('filament.admin.resources.realisasi-pengadaans.index', ['tableFilters[rencana_pengadaan_id][value]' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
