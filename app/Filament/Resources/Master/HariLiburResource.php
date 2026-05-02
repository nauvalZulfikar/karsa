<?php

namespace App\Filament\Resources\Master;

use App\Filament\Resources\Master\HariLiburResource\Pages;
use App\Models\Master\HariLibur;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class HariLiburResource extends Resource
{
    protected static ?string $model = HariLibur::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Hari Libur';

    protected static ?string $modelLabel = 'Hari Libur';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('tanggal')
                ->label('Tanggal')
                ->required()
                ->displayFormat('d/m/Y'),

            TextInput::make('nama')
                ->label('Nama')
                ->required()
                ->maxLength(150),

            Toggle::make('is_cuti_bersama')
                ->label('Cuti Bersama')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('is_cuti_bersama')
                    ->label('Cuti Bersama')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Cuti Bersama' : 'Libur Nasional')
                    ->color(fn (bool $state) => $state ? 'warning' : 'info'),

                TextColumn::make('tahun')
                    ->label('Tahun')
                    ->getStateUsing(fn ($record) => $record->tanggal?->year)
                    ->sortable(false),
            ])
            ->defaultSort('tanggal', 'asc')
            ->filters([
                Filter::make('tahun_2025')
                    ->label('2025')
                    ->query(fn ($query) => $query->whereYear('tanggal', 2025)),

                Filter::make('tahun_2026')
                    ->label('2026')
                    ->query(fn ($query) => $query->whereYear('tanggal', 2026)),

                Filter::make('tahun_2027')
                    ->label('2027')
                    ->query(fn ($query) => $query->whereYear('tanggal', 2027)),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHariLibur::route('/'),
            'create' => Pages\CreateHariLibur::route('/create'),
            'edit' => Pages\EditHariLibur::route('/{record}/edit'),
        ];
    }
}
