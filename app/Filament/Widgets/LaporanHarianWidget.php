<?php

namespace App\Filament\Widgets;

use App\Models\LaporanHarian;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LaporanHarianWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected static bool $isLazy = false;
    protected static ?string $heading = 'Laporan Vendor Hari Ini';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LaporanHarian::query()
                    ->with(['pekerjaan', 'perusahaan', 'user'])
                    ->whereDate('tanggal_laporan', today())
                    ->latest('submitted_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('pekerjaan.nama_pekerjaan')
                    ->label('Proyek')
                    ->limit(35),

                Tables\Columns\TextColumn::make('perusahaan.nama')
                    ->label('Vendor'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelapor'),

                Tables\Columns\TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn ($state) => $state === 'masuk' ? 'success' : 'info')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Jam Submit')
                    ->dateTime('H:i'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default    => 'Pending',
                    }),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
