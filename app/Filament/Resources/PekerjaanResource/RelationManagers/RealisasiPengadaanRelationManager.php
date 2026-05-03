<?php

namespace App\Filament\Resources\PekerjaanResource\RelationManagers;

use App\Models\RealisasiPengadaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RealisasiPengadaanRelationManager extends RelationManager
{
    protected static string $relationship = 'realisasiPengadaan';
    protected static ?string $title = 'Realisasi Pengadaan';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('rencana_pengadaan_id')
                ->label('Rencana')
                ->relationship('rencanaPengadaan', 'nama_item', fn ($query) => $query->where('pekerjaan_id', $this->ownerRecord->id))
                ->required()
                ->searchable(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('volume_aktual')
                    ->label('Volume Aktual')
                    ->numeric()
                    ->required()
                    ->minValue(0),

                Forms\Components\TextInput::make('harga_aktual')
                    ->label('Harga Satuan Aktual (Rp)')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->minValue(0),
            ]),

            Forms\Components\DatePicker::make('tanggal_realisasi')
                ->label('Tanggal Realisasi')
                ->required()
                ->default(today()),

            Forms\Components\Textarea::make('catatan')
                ->label('Catatan')
                ->rows(2)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rencanaPengadaan.nama_item')
                    ->label('Item')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('volume_aktual')
                    ->label('Volume'),

                Tables\Columns\TextColumn::make('harga_aktual')
                    ->label('Harga')
                    ->money('IDR', locale: 'id'),

                Tables\Columns\TextColumn::make('total_aktual')
                    ->label('Total')
                    ->state(fn ($record) => $record->total_aktual)
                    ->money('IDR', locale: 'id'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    }),

                Tables\Columns\TextColumn::make('tanggal_realisasi')
                    ->label('Tgl Realisasi')
                    ->date('d M Y'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'submitted')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status'      => 'verified',
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                        ]);
                        Notification::make()->title('Realisasi diverifikasi')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('tanggal_realisasi', 'desc');
    }
}
