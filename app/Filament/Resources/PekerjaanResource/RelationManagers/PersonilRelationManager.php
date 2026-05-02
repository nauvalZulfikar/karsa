<?php

namespace App\Filament\Resources\PekerjaanResource\RelationManagers;

use App\Models\Master\TenagaAhli;
use App\Models\PekerjaanPersonil;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PersonilRelationManager extends RelationManager
{
    protected static string $relationship = 'personil';
    protected static ?string $title = 'Personil';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tenaga_ahli_id')
                ->label('Tenaga Ahli')
                ->options(TenagaAhli::active()->pluck('nama', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $ta = TenagaAhli::find($state);
                    if ($ta?->jabatan_keahlian) {
                        $set('jabatan_kontrak', $ta->jabatan_keahlian);
                    }
                }),

            Forms\Components\TextInput::make('jabatan_kontrak')
                ->label('Jabatan pada Kontrak')
                ->required()
                ->maxLength(150),

            Forms\Components\TextInput::make('nilai_honor_kontrak')
                ->label('Nilai Honor Kontrak (Rp)')
                ->numeric()
                ->nullable()
                ->prefix('Rp'),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('tanggal_mulai_tugas')
                    ->label('Mulai Tugas')
                    ->nullable()
                    ->displayFormat('d/m/Y'),

                Forms\Components\DatePicker::make('tanggal_akhir_tugas')
                    ->label('Akhir Tugas')
                    ->nullable()
                    ->displayFormat('d/m/Y'),
            ]),

            Forms\Components\Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('jabatan_kontrak')
            ->columns([
                Tables\Columns\TextColumn::make('tenagaAhli.nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('jabatan_kontrak')
                    ->label('Jabatan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('tenagaAhli.no_telp')
                    ->label('No. Telp'),

                Tables\Columns\TextColumn::make('nilai_honor_kontrak')
                    ->label('Honor (Rp)')
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('tanggal_mulai_tugas')
                    ->label('Mulai')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('tanggal_akhir_tugas')
                    ->label('Akhir')
                    ->date('d/m/Y'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->before(function (array $data) {
                        $this->checkOverlap($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function checkOverlap(array $data): void
    {
        if (!isset($data['tenaga_ahli_id'], $data['tanggal_mulai_tugas'], $data['tanggal_akhir_tugas'])) {
            return;
        }

        $overlapping = PekerjaanPersonil::where('tenaga_ahli_id', $data['tenaga_ahli_id'])
            ->where('is_active', true)
            ->where('pekerjaan_id', '!=', $this->getOwnerRecord()->id)
            ->where(function ($q) use ($data) {
                $q->whereBetween('tanggal_mulai_tugas', [$data['tanggal_mulai_tugas'], $data['tanggal_akhir_tugas']])
                  ->orWhereBetween('tanggal_akhir_tugas', [$data['tanggal_mulai_tugas'], $data['tanggal_akhir_tugas']]);
            })
            ->with('pekerjaan')
            ->first();

        if ($overlapping) {
            \Filament\Notifications\Notification::make()
                ->title('Peringatan Jadwal Bentrok')
                ->body("Tenaga ahli ini sedang aktif di pekerjaan: {$overlapping->pekerjaan->nama_pekerjaan} pada periode yang sama.")
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
