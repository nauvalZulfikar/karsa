<?php

namespace App\Filament\Resources\PekerjaanResource\RelationManagers;

use App\Models\MilestonePekerjaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MilestoneRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';
    protected static ?string $title = 'Milestone & Jadwal';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->required()
                    ->minValue(1),

                Forms\Components\Select::make('sumber')
                    ->label('Sumber')
                    ->options(MilestonePekerjaan::$sumberLabels)
                    ->default('manual')
                    ->required(),
            ]),

            Forms\Components\TextInput::make('nama')
                ->label('Nama Milestone')
                ->required()
                ->maxLength(200),

            Forms\Components\Textarea::make('deskripsi')
                ->label('Deskripsi')
                ->rows(2)
                ->nullable(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('tanggal_target')
                    ->label('Tanggal Target')
                    ->required(),

                Forms\Components\TextInput::make('progres_target_persen')
                    ->label('Target Progres (%)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('tanggal_selesai_aktual')
                    ->label('Tanggal Selesai Aktual')
                    ->nullable(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(MilestonePekerjaan::$statusOptions)
                    ->default('belum_mulai')
                    ->required(),
            ]),

            Forms\Components\Textarea::make('catatan')
                ->label('Catatan')
                ->rows(2)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('urutan')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Milestone')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tanggal_target')
                    ->label('Target')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('progres_target_persen')
                    ->label('Target %')
                    ->formatStateUsing(fn ($state) => $state . '%'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => MilestonePekerjaan::$statusOptions[$state] ?? $state)
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('sumber')
                    ->label('Sumber')
                    ->formatStateUsing(fn ($state) => MilestonePekerjaan::$sumberLabels[$state] ?? $state)
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'kontrak'      => 'success',
                        'generated_ai' => 'info',
                        default        => 'gray',
                    }),

                Tables\Columns\TextColumn::make('tanggal_selesai_aktual')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->placeholder('-'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('selesaikan')
                    ->label('Tandai Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'selesai')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status'                 => 'selesai',
                            'tanggal_selesai_aktual' => today(),
                        ]);
                        Notification::make()->title('Milestone ditandai selesai')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('urutan');
    }
}
