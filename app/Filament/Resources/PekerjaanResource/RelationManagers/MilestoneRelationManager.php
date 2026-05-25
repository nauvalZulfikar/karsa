<?php

namespace App\Filament\Resources\PekerjaanResource\RelationManagers;

use App\Models\MilestoneChecklistItem;
use App\Models\MilestonePekerjaan;
use App\Models\User;
use App\Services\WaGatewayService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

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
            ->modifyQueryUsing(fn ($query) => $query->with('checklistItems'))
            ->columns([
                Tables\Columns\TextColumn::make('urutan')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Milestone')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->wrap()
                    ->limit(150)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('vendor_progress')
                    ->label('Progres')
                    ->badge()
                    ->color('info'),

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

                Tables\Columns\TextColumn::make('confirmed_at')
                    ->label('Dikonfirmasi')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('alasan_penolakan')
                    ->label('Alasan Tolak')
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('lihat_checklist')
                    ->label('Lihat Checklist')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'Checklist: ' . $record->nama)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn ($record) => view('filament.milestone-checklist-modal', [
                        'items'     => $record->checklistItems()->orderBy('tipe')->orderBy('id')->get(),
                        'milestone' => $record,
                        'isVendor'  => auth()->user()->hasRole('vendor'),
                        'isAdmin'   => auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin']),
                    ])),

                Tables\Actions\Action::make('tick_vendor')
                    ->label('Centang/Uncentang Vendor')
                    ->icon('heroicon-o-check')
                    ->color('warning')
                    ->visible(fn ($record) => auth()->user()->hasRole('vendor')
                        && $record->pekerjaan->perusahaan_id === auth()->user()->perusahaan_id
                        && $record->checklistItems()->exists())
                    ->before(function ($record) {
                        abort_unless(
                            auth()->user()->hasRole('vendor')
                            && $record->pekerjaan->perusahaan_id === auth()->user()->perusahaan_id,
                            403
                        );
                    })
                    ->form([
                        Forms\Components\Select::make('checklist_item_id')
                            ->label('Item Checklist')
                            ->options(fn ($record) => $record->checklistItems()
                                ->get()
                                ->mapWithKeys(fn ($item) => [
                                    $item->id => ($item->is_done_vendor ? '[✓] ' : '[ ] ') . $item->nama,
                                ]))
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $item = MilestoneChecklistItem::where('id', $data['checklist_item_id'])
                            ->where('milestone_pekerjaan_id', $record->id)
                            ->firstOrFail();
                        $item->update([
                            'is_done_vendor' => !$item->is_done_vendor,
                            'vendor_done_at' => $item->is_done_vendor ? null : now(),
                        ]);
                        $label = $item->is_done_vendor ? 'ditandai selesai' : 'dibatalkan';
                        Notification::make()->title("Item vendor {$label}")->success()->send();
                    }),

                Tables\Actions\Action::make('tick_admin')
                    ->label('Centang/Uncentang Admin')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin'])
                        && $record->checklistItems()->exists())
                    ->before(function ($record) {
                        abort_unless(
                            auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin']),
                            403
                        );
                    })
                    ->form([
                        Forms\Components\Select::make('checklist_item_id')
                            ->label('Item Checklist')
                            ->options(fn ($record) => $record->checklistItems()
                                ->get()
                                ->mapWithKeys(fn ($item) => [
                                    $item->id => ($item->is_done_admin ? '[✓] ' : '[ ] ') . $item->nama,
                                ]))
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $item = MilestoneChecklistItem::where('id', $data['checklist_item_id'])
                            ->where('milestone_pekerjaan_id', $record->id)
                            ->firstOrFail();
                        $newState = !$item->is_done_admin;
                        $item->update([
                            'is_done_admin'  => $newState,
                            'admin_done_at'  => $newState ? now() : null,
                            'admin_done_by'  => $newState ? auth()->id() : null,
                        ]);
                        $label = $newState ? 'ditandai selesai' : 'dibatalkan';
                        Notification::make()->title("Item admin {$label}")->success()->send();
                    }),

                Tables\Actions\Action::make('ajukan_selesai')
                    ->label('Ajukan Selesai')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => auth()->user()->hasRole('vendor')
                        && in_array($record->status, ['belum_mulai', 'sedang_berjalan'])
                        && $record->pekerjaan->perusahaan_id === auth()->user()->perusahaan_id
                        && $record->all_vendor_done)
                    ->before(function ($record) {
                        abort_unless(
                            auth()->user()->hasRole('vendor')
                            && in_array($record->status, ['belum_mulai', 'sedang_berjalan'])
                            && $record->pekerjaan->perusahaan_id === auth()->user()->perusahaan_id
                            && $record->all_vendor_done,
                            403
                        );
                    })
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'diajukan_vendor',
                            'alasan_penolakan' => null,
                        ]);
                        $this->kirimNotifikasiAjuanVendor($record);
                        $this->kirimWaKePpk($record);
                        Notification::make()->title('Milestone diajukan')->success()->send();
                    }),

                Tables\Actions\Action::make('konfirmasi')
                    ->label('Konfirmasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin'])
                        && $record->status === 'diajukan_vendor')
                    ->before(function ($record) {
                        abort_unless(
                            auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin'])
                            && $record->status === 'diajukan_vendor',
                            403
                        );
                    })
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status'       => 'dikonfirmasi',
                            'confirmed_by' => auth()->id(),
                            'confirmed_at' => now(),
                        ]);
                        Notification::make()->title('Milestone dikonfirmasi')->success()->send();
                    }),

                Tables\Actions\Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin'])
                        && $record->status === 'diajukan_vendor')
                    ->before(function ($record) {
                        abort_unless(
                            auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin'])
                            && $record->status === 'diajukan_vendor',
                            403
                        );
                    })
                    ->form([
                        Forms\Components\Textarea::make('alasan_penolakan')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->minLength(10)
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status'           => 'ditolak',
                            'alasan_penolakan' => $data['alasan_penolakan'],
                        ]);
                        Notification::make()->title('Milestone ditolak')->danger()->send();
                    }),

                Tables\Actions\Action::make('tandai_selesai')
                    ->label('Tandai Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin'])
                        && !in_array($record->status, ['selesai', 'dikonfirmasi', 'diajukan_vendor', 'ditolak']))
                    ->before(function ($record) {
                        abort_unless(
                            auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin'])
                            && !in_array($record->status, ['selesai', 'dikonfirmasi', 'diajukan_vendor', 'ditolak']),
                            403
                        );
                    })
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

    private function kirimNotifikasiAjuanVendor(MilestonePekerjaan $milestone): void
    {
        $pekerjaan = $milestone->pekerjaan;
        if (!$pekerjaan) return;

        $admins = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['pptk', 'ppk', 'admin_bidang']))
            ->where('bidang_id', $pekerjaan->bidang_id)
            ->where('is_active', true)
            ->get();

        foreach ($admins as $user) {
            Notification::make()
                ->title('Milestone Diajukan Vendor')
                ->body("{$milestone->nama} — {$pekerjaan->nama_pekerjaan}")
                ->sendToDatabase($user);
        }
    }

    private function kirimWaKePpk(MilestonePekerjaan $milestone): void
    {
        $pekerjaan = $milestone->pekerjaan;
        if (!$pekerjaan) return;

        $ppkUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'ppk'))
            ->where('bidang_id', $pekerjaan->bidang_id)
            ->whereNotNull('no_telp')
            ->get();

        $pesan = "[DPUTR-PM] Vendor mengajukan selesai: {$milestone->nama} — {$pekerjaan->nama_pekerjaan}. Silakan konfirmasi di panel admin.";

        foreach ($ppkUsers as $user) {
            try {
                app(WaGatewayService::class)->kirim($user->no_telp, $pesan);
            } catch (\Throwable $e) {
                Log::warning('WA ke PPK gagal', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
