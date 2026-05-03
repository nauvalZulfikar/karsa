<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DokumenResource\Pages;
use App\Models\Dokumen;
use App\Models\Pekerjaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DokumenResource extends Resource
{
    protected static ?string $model = Dokumen::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationLabel = 'Dokumen';
    protected static ?string $modelLabel = 'Dokumen';
    protected static ?string $pluralModelLabel = 'Dokumen';
    protected static ?string $navigationGroup = 'Dokumen';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('pekerjaan_id')
                ->label('Proyek')
                ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                ->searchable()
                ->required(),

            Forms\Components\Select::make('tipe')
                ->label('Tipe Dokumen')
                ->options(Dokumen::$tipeOptions)
                ->required(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('nama_dokumen')
                    ->label('Nama Dokumen')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('versi')
                    ->label('Versi')
                    ->default('1.0')
                    ->maxLength(20),
            ]),

            Forms\Components\FileUpload::make('file_path')
                ->label('File')
                ->disk('local')
                ->directory('dokumen/' . date('Y'))
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'image/jpeg', 'image/png', 'image/webp',
                ])
                ->maxSize(51200)
                ->required(),

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

                Tables\Columns\TextColumn::make('tipe')
                    ->label('Tipe')
                    ->formatStateUsing(fn ($state) => Dokumen::$tipeOptions[$state] ?? $state)
                    ->badge()
                    ->color(fn ($record) => $record->tipe_color)
                    ->sortable(),

                Tables\Columns\TextColumn::make('nama_dokumen')
                    ->label('Nama Dokumen')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('versi')
                    ->label('Versi')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('file_size_human')
                    ->label('Ukuran')
                    ->state(fn ($record) => $record->file_size_human),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Diupload'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pekerjaan_id')
                    ->label('Proyek')
                    ->options(Pekerjaan::pluck('nama_pekerjaan', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('tipe')
                    ->label('Tipe Dokumen')
                    ->options(Dokumen::$tipeOptions),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => route('dokumen.download', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDokumens::route('/'),
            'create' => Pages\CreateDokumen::route('/create'),
            'edit'   => Pages\EditDokumen::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nama_dokumen', 'keterangan', 'file_original_name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->nama_dokumen;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Proyek' => $record->pekerjaan?->nama_pekerjaan ?? '-',
            'Tipe'   => $record->tipe_label,
            'Versi'  => $record->versi,
        ];
    }
}
