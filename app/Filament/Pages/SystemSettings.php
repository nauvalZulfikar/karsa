<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Pengaturan Sistem';
    protected static ?string $title           = 'Pengaturan Sistem';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view            = 'filament.pages.system-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'nama_instansi'       => SystemSetting::get('nama_instansi', ''),
            'nama_singkat'        => SystemSetting::get('nama_singkat', ''),
            'alamat_kantor'       => SystemSetting::get('alamat_kantor', ''),
            'no_telp_kantor'      => SystemSetting::get('no_telp_kantor', ''),
            'tahun_anggaran_aktif'=> SystemSetting::get('tahun_anggaran_aktif', date('Y')),
            'deadline_alert_days' => SystemSetting::get('deadline_alert_days', '14,7,3'),
            'notif_laporan_aktif' => SystemSetting::get('notif_laporan_aktif', true),
            'notif_deadline_aktif'=> SystemSetting::get('notif_deadline_aktif', true),
            'notif_termin_aktif'  => SystemSetting::get('notif_termin_aktif', true),
            'jam_masuk_buka'      => SystemSetting::get('jam_masuk_buka', '06:00'),
            'jam_masuk_tutup'     => SystemSetting::get('jam_masuk_tutup', '09:00'),
            'jam_pulang_buka'     => SystemSetting::get('jam_pulang_buka', '15:00'),
            'jam_pulang_tutup'    => SystemSetting::get('jam_pulang_tutup', '18:00'),
            'maintenance_mode'    => SystemSetting::get('maintenance_mode', false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informasi Instansi')
                ->icon('heroicon-o-building-office-2')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('nama_instansi')
                            ->label('Nama Instansi')
                            ->required()
                            ->maxLength(200),

                        TextInput::make('nama_singkat')
                            ->label('Nama Singkat')
                            ->required()
                            ->maxLength(100),
                    ]),

                    TextInput::make('alamat_kantor')
                        ->label('Alamat Kantor')
                        ->maxLength(300),

                    TextInput::make('no_telp_kantor')
                        ->label('Nomor Telepon Kantor')
                        ->maxLength(30),

                    TextInput::make('tahun_anggaran_aktif')
                        ->label('Tahun Anggaran Aktif')
                        ->numeric()
                        ->required()
                        ->minValue(2020)
                        ->maxValue(2100)
                        ->helperText('Tahun yang muncul sebagai default di semua filter'),
                ]),

            Section::make('Notifikasi WhatsApp')
                ->icon('heroicon-o-bell')
                ->schema([
                    TextInput::make('deadline_alert_days')
                        ->label('Alert Deadline (hari ke-)')
                        ->required()
                        ->helperText('Pisahkan dengan koma. Contoh: 14,7,3 artinya WA dikirim saat sisa H-14, H-7, dan H-3')
                        ->placeholder('14,7,3'),

                    Grid::make(3)->schema([
                        Toggle::make('notif_deadline_aktif')
                            ->label('Notifikasi Deadline')
                            ->helperText('WA peringatan deadline ke personil'),

                        Toggle::make('notif_laporan_aktif')
                            ->label('Notifikasi Laporan Harian')
                            ->helperText('WA reminder ke vendor yang belum lapor'),

                        Toggle::make('notif_termin_aktif')
                            ->label('Notifikasi Termin')
                            ->helperText('WA ke PPK untuk termin pending'),
                    ]),
                ]),

            Section::make('Jam Laporan Vendor')
                ->icon('heroicon-o-clock')
                ->description('Atur batas waktu vendor bisa submit laporan harian')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('jam_masuk_buka')
                            ->label('Jam Buka Laporan Masuk')
                            ->placeholder('06:00')
                            ->helperText('Format HH:MM'),

                        TextInput::make('jam_masuk_tutup')
                            ->label('Jam Tutup Laporan Masuk')
                            ->placeholder('09:00')
                            ->helperText('Format HH:MM'),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('jam_pulang_buka')
                            ->label('Jam Buka Laporan Pulang')
                            ->placeholder('15:00')
                            ->helperText('Format HH:MM'),

                        TextInput::make('jam_pulang_tutup')
                            ->label('Jam Tutup Laporan Pulang')
                            ->placeholder('18:00')
                            ->helperText('Format HH:MM'),
                    ]),
                ]),

            Section::make('Sistem')
                ->icon('heroicon-o-server')
                ->schema([
                    Toggle::make('maintenance_mode')
                        ->label('Mode Maintenance')
                        ->helperText('Aktifkan saat melakukan pemeliharaan sistem. Hanya super_admin yang bisa login.')
                        ->onColor('danger')
                        ->offColor('success'),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value);
        }

        SystemSetting::clearCache();

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
