<?php

namespace App\Filament\Resources\Master;

use App\Filament\Resources\Master\TenagaAhliResource\Pages;
use App\Models\Master\Perusahaan;
use App\Models\Master\TenagaAhli;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TenagaAhliResource extends Resource
{
    protected static ?string $model = TenagaAhli::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Tenaga Ahli';

    protected static ?string $modelLabel = 'Tenaga Ahli';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin_bidang', 'pptk']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identitas')->schema([
                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(150),

                TextInput::make('nik')
                    ->label('NIK')
                    ->nullable()
                    ->maxLength(20),

                TextInput::make('npwp')
                    ->label('NPWP')
                    ->nullable()
                    ->maxLength(30),

                Select::make('perusahaan_id')
                    ->label('Perusahaan')
                    ->options(Perusahaan::pluck('nama', 'id'))
                    ->nullable()
                    ->searchable()
                    ->placeholder('Pilih Perusahaan'),

                TextInput::make('jabatan_keahlian')
                    ->label('Jabatan/Keahlian')
                    ->nullable()
                    ->maxLength(100),

                TextInput::make('sertifikasi')
                    ->label('Sertifikasi')
                    ->nullable()
                    ->maxLength(200),
            ]),

            Section::make('Kontak')->schema([
                Textarea::make('alamat')
                    ->label('Alamat')
                    ->nullable(),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->nullable()
                    ->maxLength(100),

                TextInput::make('no_telp')
                    ->label('No. Telepon')
                    ->nullable()
                    ->maxLength(20),
            ]),

            Section::make('Status')->schema([
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jabatan_keahlian')
                    ->label('Jabatan/Keahlian')
                    ->searchable(),

                TextColumn::make('sertifikasi')
                    ->label('Sertifikasi')
                    ->searchable(),

                TextColumn::make('perusahaan.nama')
                    ->label('Perusahaan')
                    ->sortable(),

                TextColumn::make('no_telp')
                    ->label('No. Telepon'),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),

                TextColumn::make('pekerjaan_aktif_count')
                    ->label('Proyek Aktif')
                    ->counts('pekerjaanAktif')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),

                SelectFilter::make('perusahaan_id')
                    ->label('Perusahaan')
                    ->options(Perusahaan::pluck('nama', 'id')),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount('pekerjaanAktif');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenagaAhli::route('/'),
            'create' => Pages\CreateTenagaAhli::route('/create'),
            'edit' => Pages\EditTenagaAhli::route('/{record}/edit'),
        ];
    }
}
