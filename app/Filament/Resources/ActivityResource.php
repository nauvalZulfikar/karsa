<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Audit Trail';
    protected static ?string $modelLabel      = 'Aktivitas';
    protected static ?string $pluralModelLabel= 'Audit Trail';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int    $navigationSort  = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable()
                    ->width('160px'),

                Tables\Columns\TextColumn::make('event')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        'created' => 'Dibuat',
                        'updated' => 'Diubah',
                        'deleted' => 'Dihapus',
                        default   => $state ?? '-',
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Objek')
                    ->formatStateUsing(fn ($state) => class_basename($state ?? ''))
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID')
                    ->width('60px'),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Dilakukan Oleh')
                    ->placeholder('Sistem'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('properties')
                    ->label('Perubahan')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state) return '-';

                        $props = is_string($state)
                            ? json_decode($state, true)
                            : (is_array($state) ? $state : $state->toArray());

                        if (empty($props)) return '-';

                        $old  = $props['old'] ?? [];
                        $new  = $props['attributes'] ?? [];

                        if (empty($old) && !empty($new)) {
                            $keys = array_keys($new);
                            return implode(', ', array_slice($keys, 0, 4))
                                . (count($keys) > 4 ? ' +' . (count($keys) - 4) . ' lainnya' : '');
                        }

                        if (!empty($old)) {
                            $changed = array_keys($old);
                            return implode(', ', array_slice($changed, 0, 3))
                                . (count($changed) > 3 ? ' +' . (count($changed) - 3) . ' lainnya' : '');
                        }

                        return '-';
                    })
                    ->tooltip(function ($record) {
                        $props = $record->properties;
                        if (!$props) return null;

                        $arr  = is_array($props) ? $props : $props->toArray();
                        $old  = $arr['old'] ?? [];
                        $new  = $arr['attributes'] ?? [];

                        if (empty($old) && empty($new)) return null;

                        $lines = [];
                        if (!empty($old)) {
                            foreach ($old as $field => $val) {
                                $newVal = $new[$field] ?? '(dihapus)';
                                $lines[] = "{$field}: {$val} → {$newVal}";
                            }
                        } else {
                            foreach (array_slice($new, 0, 10) as $field => $val) {
                                $lines[] = "{$field}: {$val}";
                            }
                        }
                        return implode("\n", $lines);
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Aksi')
                    ->options([
                        'created' => 'Dibuat',
                        'updated' => 'Diubah',
                        'deleted' => 'Dihapus',
                    ]),

                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Dilakukan Oleh')
                    ->options(fn () => \App\Models\User::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Objek')
                    ->options(function () {
                        return Activity::query()
                            ->whereNotNull('subject_type')
                            ->distinct()
                            ->pluck('subject_type', 'subject_type')
                            ->mapWithKeys(fn ($v) => [$v => class_basename($v)])
                            ->toArray();
                    }),

                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('dari')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('sampai')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['dari'] ?? null) {
                            $query->whereDate('created_at', '>=', $data['dari']);
                        }
                        if ($data['sampai'] ?? null) {
                            $query->whereDate('created_at', '<=', $data['sampai']);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn (Activity $record) => view(
                        'filament.audit.detail',
                        ['activity' => $record]
                    )),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
