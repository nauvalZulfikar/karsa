<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanHarianResource\Pages;
use App\Models\LaporanHarian;
use App\Models\Master\Perusahaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LaporanHarianResource extends Resource
{
    protected static ?string $model = LaporanHarian::class;
    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Laporan Harian';
    protected static ?string $modelLabel = 'Laporan Harian';
    protected static ?string $pluralModelLabel = 'Laporan Harian';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->options([
                    'pending'  => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->required(),
            Forms\Components\Textarea::make('alasan_rejected')
                ->label('Alasan Ditolak')
                ->nullable()
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_laporan')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pekerjaan.nama_pekerjaan')
                    ->label('Pekerjaan')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('perusahaan.nama')
                    ->label('Vendor')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dikirim Oleh'),

                Tables\Columns\TextColumn::make('jenis')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'masuk'  => 'success',
                        'pulang' => 'info',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Jam Kirim')
                    ->dateTime('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'pending'  => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'gray',
                    }),

                Tables\Columns\ImageColumn::make('foto_stamped_path')
                    ->label('Foto')
                    ->disk('local')
                    ->square()
                    ->size(56),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('jenis')
                    ->options(['masuk' => 'Masuk', 'pulang' => 'Pulang']),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\SelectFilter::make('perusahaan_id')
                    ->label('Vendor')
                    ->options(Perusahaan::pluck('nama', 'id'))
                    ->searchable(),

                Tables\Filters\Filter::make('hari_ini')
                    ->label('Hari Ini')
                    ->query(fn ($query) => $query->whereDate('tanggal_laporan', today()))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (LaporanHarian $record) => $record->status === 'pending')
                    ->action(fn (LaporanHarian $record) => $record->update(['status' => 'approved'])),

                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LaporanHarian $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn (LaporanHarian $record) => $record->update(['status' => 'rejected'])),

                Tables\Actions\EditAction::make()->label('Edit'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLaporanHarian::route('/'),
            'edit'  => Pages\EditLaporanHarian::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['catatan'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return 'Laporan ' . ucfirst($record->jenis) . ' — ' . ($record->pekerjaan?->nama_pekerjaan ?? '-');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Vendor'  => $record->perusahaan?->nama ?? '-',
            'Tanggal' => $record->tanggal_laporan?->format('d M Y') ?? '-',
            'Status'  => ucfirst($record->status),
        ];
    }
}
