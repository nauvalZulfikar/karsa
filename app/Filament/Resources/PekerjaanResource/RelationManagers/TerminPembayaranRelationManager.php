<?php

namespace App\Filament\Resources\PekerjaanResource\RelationManagers;

use App\Models\TerminPembayaran;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TerminPembayaranRelationManager extends RelationManager
{
    protected static string $relationship = 'terminPembayaran';
    protected static ?string $title = 'Termin Pembayaran';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('nomor_termin')
                    ->label('Nomor Termin')
                    ->numeric()
                    ->required()
                    ->minValue(1),

                Forms\Components\TextInput::make('nama_termin')
                    ->label('Nama Termin')
                    ->placeholder('Termin I / Uang Muka / Termin Akhir')
                    ->required()
                    ->maxLength(100),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('nilai_termin')
                    ->label('Nilai Termin (Rp)')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->minValue(0),

                Forms\Components\TextInput::make('persen_progres_syarat')
                    ->label('Syarat Progres (%)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->helperText('Minimal progres pekerjaan sebelum termin ini bisa diajukan'),
            ]),

            Forms\Components\DatePicker::make('tanggal_pengajuan')
                ->label('Tanggal Pengajuan')
                ->nullable(),

            Forms\Components\Textarea::make('catatan_pptk')
                ->label('Catatan PPTK')
                ->rows(2)
                ->nullable(),

            Forms\Components\FileUpload::make('dokumen_path')
                ->label('Dokumen Pendukung (BAST/Invoice)')
                ->disk('local')
                ->directory('dokumen/termin')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(20480)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_termin')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nama_termin')
                    ->label('Nama Termin')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nilai_termin')
                    ->label('Nilai')
                    ->money('IDR', locale: 'id'),

                Tables\Columns\TextColumn::make('persen_progres_syarat')
                    ->label('Syarat')
                    ->formatStateUsing(fn ($state) => $state . '%'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => TerminPembayaran::$statusOptions[$state] ?? $state)
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('tanggal_pengajuan')
                    ->label('Tgl Pengajuan')
                    ->date('d M Y')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('tanggal_bayar')
                    ->label('Tgl Bayar')
                    ->date('d M Y')
                    ->placeholder('-'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('ajukan')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if (!$record->is_syarat_terpenuhi) {
                            Notification::make()
                                ->title('Syarat progres belum terpenuhi')
                                ->body('Progres pekerjaan belum mencapai ' . $record->persen_progres_syarat . '%')
                                ->warning()
                                ->send();
                            return;
                        }
                        $record->update([
                            'status'            => 'diajukan',
                            'tanggal_pengajuan' => today(),
                        ]);
                        Notification::make()->title('Termin diajukan ke PPK')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('nomor_termin');
    }
}
