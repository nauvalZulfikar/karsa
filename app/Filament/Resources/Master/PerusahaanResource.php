<?php

namespace App\Filament\Resources\Master;

use App\Filament\Resources\Master\PerusahaanResource\Pages;
use App\Models\Master\Perusahaan;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PerusahaanResource extends Resource
{
    protected static ?string $model = Perusahaan::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Perusahaan';

    protected static ?string $modelLabel = 'Perusahaan';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin_bidang']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identitas')->schema([
                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(200),

                TextInput::make('singkatan')
                    ->label('Singkatan')
                    ->nullable()
                    ->maxLength(50),

                Select::make('jenis')
                    ->label('Jenis')
                    ->options([
                        'PT' => 'PT',
                        'CV' => 'CV',
                        'Perorangan' => 'Perorangan',
                        'Lainnya' => 'Lainnya',
                    ])
                    ->required(),

                TextInput::make('npwp')
                    ->label('NPWP')
                    ->nullable()
                    ->maxLength(30),
            ]),

            Section::make('Kontak')->schema([
                Textarea::make('alamat')
                    ->label('Alamat')
                    ->nullable(),

                TextInput::make('no_telp')
                    ->label('No. Telepon')
                    ->nullable()
                    ->maxLength(20),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->nullable()
                    ->maxLength(100),

                TextInput::make('pic_nama')
                    ->label('PIC Nama')
                    ->nullable()
                    ->maxLength(100),

                TextInput::make('pic_telp')
                    ->label('PIC Telepon')
                    ->nullable()
                    ->maxLength(20),
            ]),

            Section::make('Status')->schema([
                Toggle::make('is_blacklisted')
                    ->label('Blacklist')
                    ->default(false),

                Textarea::make('catatan')
                    ->label('Catatan')
                    ->nullable(),
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

                TextColumn::make('singkatan')
                    ->label('Singkatan')
                    ->searchable(),

                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge(),

                TextColumn::make('pic_nama')
                    ->label('PIC')
                    ->searchable(),

                TextColumn::make('pic_telp')
                    ->label('Telp PIC'),

                TextColumn::make('is_blacklisted')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Blacklist' : 'Aktif')
                    ->color(fn (bool $state) => $state ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->label('Jenis')
                    ->options([
                        'PT' => 'PT',
                        'CV' => 'CV',
                        'Perorangan' => 'Perorangan',
                        'Lainnya' => 'Lainnya',
                    ]),

                TernaryFilter::make('is_blacklisted')
                    ->label('Blacklist')
                    ->trueLabel('Blacklist')
                    ->falseLabel('Aktif'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerusahaan::route('/'),
            'create' => Pages\CreatePerusahaan::route('/create'),
            'edit' => Pages\EditPerusahaan::route('/{record}/edit'),
        ];
    }
}
