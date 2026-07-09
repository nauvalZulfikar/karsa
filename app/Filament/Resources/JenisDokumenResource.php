<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JenisDokumenResource\Pages;
use App\Models\JenisDokumen;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class JenisDokumenResource extends Resource
{
    protected static ?string $model = JenisDokumen::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Jenis Dokumen';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Jenis Dokumen';

    protected static ?string $pluralModelLabel = 'Jenis Dokumen';

    /** Palet warna badge Filament. */
    protected const COLORS = [
        'gray'    => 'Abu-abu',
        'info'    => 'Biru (info)',
        'primary' => 'Utama',
        'success' => 'Hijau (selesai)',
        'warning' => 'Kuning (proses)',
        'danger'  => 'Merah (penting)',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')
                ->label('Nama Tipe Dokumen')
                ->required()
                ->maxLength(100)
                ->placeholder('cth. Notulensi Rapat'),

            TextInput::make('key')
                ->label('Kode (slug)')
                ->helperText('Kosongkan untuk dibuat otomatis dari nama. Jangan ubah bila sudah dipakai dokumen.')
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->placeholder('otomatis'),

            Select::make('color')
                ->label('Warna Badge')
                ->options(self::COLORS)
                ->default('gray')
                ->required(),

            TextInput::make('urutan')
                ->label('Urutan')
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label('Aktif')
                ->helperText('Nonaktif = tetap tampil di dokumen lama, tapi hilang dari pilihan tambah dokumen.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('label')
                    ->label('Nama')
                    ->badge()
                    ->color(fn ($record) => $record->color)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('key')
                    ->label('Kode')
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('urutan')
                    ->label('Urutan')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListJenisDokumen::route('/'),
            'create' => Pages\CreateJenisDokumen::route('/create'),
            'edit'   => Pages\EditJenisDokumen::route('/{record}/edit'),
        ];
    }
}
