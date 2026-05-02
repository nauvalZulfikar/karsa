<?php

namespace App\Filament\Resources\Master;

use App\Filament\Resources\Master\StatusPekerjaanResource\Pages;
use App\Models\Master\StatusPekerjaan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusPekerjaanResource extends Resource
{
    protected static ?string $model = StatusPekerjaan::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Status Pekerjaan';

    protected static ?string $modelLabel = 'Status Pekerjaan';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nama')
                ->label('Nama')
                ->required()
                ->maxLength(100),

            TextInput::make('kode')
                ->label('Kode')
                ->required()
                ->maxLength(30),

            Select::make('warna')
                ->label('Warna')
                ->options([
                    'gray' => 'Gray',
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'success' => 'Success',
                    'danger' => 'Danger',
                    'primary' => 'Primary',
                ])
                ->required(),

            TextInput::make('urutan')
                ->label('Urutan')
                ->numeric()
                ->default(0),

            TextInput::make('keterangan')
                ->label('Keterangan')
                ->nullable()
                ->maxLength(200),

            Toggle::make('is_final')
                ->label('Status Akhir')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('urutan')
                    ->label('Urutan')
                    ->sortable(),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable(),

                TextColumn::make('warna')
                    ->label('Warna')
                    ->badge()
                    ->color(fn (string $state) => $state),

                TextColumn::make('is_final')
                    ->label('Status Akhir')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Final' : 'Proses')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('urutan', 'asc')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatusPekerjaan::route('/'),
            'create' => Pages\CreateStatusPekerjaan::route('/create'),
            'edit' => Pages\EditStatusPekerjaan::route('/{record}/edit'),
        ];
    }
}
