<?php

namespace App\Filament\Resources\Master;

use App\Filament\Resources\Master\JenisPekerjaanResource\Pages;
use App\Models\Master\JenisPekerjaan;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JenisPekerjaanResource extends Resource
{
    protected static ?string $model = JenisPekerjaan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Jenis Pekerjaan';

    protected static ?string $modelLabel = 'Jenis Pekerjaan';

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

            TextInput::make('keterangan')
                ->label('Keterangan')
                ->nullable()
                ->maxLength(200),

            TextInput::make('urutan')
                ->label('Urutan')
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
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

                TextColumn::make('keterangan')
                    ->label('Keterangan'),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),
            ])
            ->defaultSort('urutan', 'asc')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJenisPekerjaan::route('/'),
            'create' => Pages\CreateJenisPekerjaan::route('/create'),
            'edit' => Pages\EditJenisPekerjaan::route('/{record}/edit'),
        ];
    }
}
