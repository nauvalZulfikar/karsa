<?php

namespace App\Filament\Resources\PekerjaanResource\RelationManagers;

use App\Models\Master\Perusahaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VendorRelationManager extends RelationManager
{
    protected static string $relationship = 'vendors';
    protected static ?string $title = 'Vendor';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('perusahaan_id')
                ->label('Perusahaan Vendor')
                ->options(Perusahaan::aktif()->pluck('nama', 'id'))
                ->required()
                ->searchable()
                ->preload(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Perusahaan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jenis')
                    ->badge(),
                Tables\Columns\TextColumn::make('pic_nama')
                    ->label('PIC'),
                Tables\Columns\TextColumn::make('pic_telp')
                    ->label('Telp PIC'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
