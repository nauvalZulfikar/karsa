<?php

namespace App\Filament\Resources\PekerjaanResource\RelationManagers;

use App\Models\Dokumen;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DokumenRelationManager extends RelationManager
{
    protected static string $relationship = 'dokumen';
    protected static ?string $title = 'Dokumen Proyek';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipe')
                ->label('Tipe Dokumen')
                ->options(Dokumen::$tipeOptions)
                ->required(),

            Forms\Components\TextInput::make('nama_dokumen')
                ->label('Nama Dokumen')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('versi')
                ->label('Versi')
                ->default('1.0')
                ->maxLength(20),

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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->label('Diupload Oleh'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Upload')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipe')
                    ->label('Tipe')
                    ->options(Dokumen::$tipeOptions),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        if (!empty($data['file_path'])) {
                            $fullPath = Storage::disk('local')->path($data['file_path']);
                            if (file_exists($fullPath)) {
                                $data['file_size'] = filesize($fullPath);
                                $data['file_original_name'] = basename($data['file_path']);
                            }
                        }
                        return $data;
                    }),
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
            ->defaultSort('created_at', 'desc');
    }
}
