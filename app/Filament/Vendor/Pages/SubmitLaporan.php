<?php

namespace App\Filament\Vendor\Pages;

use App\Models\LaporanHarian;
use App\Models\Pekerjaan;
use App\Models\SystemSetting;
use App\Services\FotoStampingService;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SubmitLaporan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Submit Laporan';
    protected static ?string $title = 'Submit Laporan Harian';
    protected static string $view = 'filament.vendor.pages.submit-laporan';
    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('pekerjaan_id')
                    ->label('Proyek')
                    ->options($this->getProyekOptions())
                    ->required()
                    ->searchable(),

                Select::make('jenis')
                    ->label('Jenis Laporan')
                    ->options([
                        'masuk'  => 'Laporan Masuk (06:00 – 09:00)',
                        'pulang' => 'Laporan Pulang (15:00 – 18:00)',
                    ])
                    ->required(),

                FileUpload::make('foto_path')
                    ->label('Foto')
                    ->image()
                    ->disk('local')
                    ->directory('foto_laporan/temp')
                    ->required()
                    ->maxSize(10240)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),

                Hidden::make('latitude'),
                Hidden::make('longitude'),

                Textarea::make('catatan')
                    ->label('Catatan (opsional)')
                    ->rows(2)
                    ->nullable(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data  = $this->form->getState();
        $user  = auth()->user();
        $now   = now();
        $jenis = $data['jenis'];

        [$jamMasukBuka,  $jamMasukMenit]  = array_map('intval', explode(':', SystemSetting::get('jam_masuk_buka',  '06:00')));
        [$jamMasukTutup, $jamMasukMenitT] = array_map('intval', explode(':', SystemSetting::get('jam_masuk_tutup', '09:00')));
        [$jamPulangBuka, $jamPulangMenit] = array_map('intval', explode(':', SystemSetting::get('jam_pulang_buka', '15:00')));
        [$jamPulangTutup,$jamPulangMenitT]= array_map('intval', explode(':', SystemSetting::get('jam_pulang_tutup','18:00')));

        $windowMasuk  = $now->between(
            Carbon::today()->setTime($jamMasukBuka, $jamMasukMenit),
            Carbon::today()->setTime($jamMasukTutup, $jamMasukMenitT)
        );
        $windowPulang = $now->between(
            Carbon::today()->setTime($jamPulangBuka, $jamPulangMenit),
            Carbon::today()->setTime($jamPulangTutup, $jamPulangMenitT)
        );

        if ($jenis === 'masuk' && !$windowMasuk) {
            Notification::make()
                ->title('Di luar jam laporan masuk')
                ->body('Laporan masuk hanya bisa dikirim antara 06:00 – 09:00.')
                ->danger()
                ->send();
            return;
        }

        if ($jenis === 'pulang' && !$windowPulang) {
            Notification::make()
                ->title('Di luar jam laporan pulang')
                ->body('Laporan pulang hanya bisa dikirim antara 15:00 – 18:00.')
                ->danger()
                ->send();
            return;
        }

        $exists = LaporanHarian::where('pekerjaan_id', $data['pekerjaan_id'])
            ->where('user_id', $user->id)
            ->where('jenis', $jenis)
            ->whereDate('tanggal_laporan', today())
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Laporan sudah dikirim')
                ->body('Anda sudah mengirim laporan ' . $jenis . ' untuk proyek ini hari ini.')
                ->warning()
                ->send();
            return;
        }

        $laporan = LaporanHarian::create([
            'pekerjaan_id'       => $data['pekerjaan_id'],
            'perusahaan_id'      => $user->perusahaan_id,
            'user_id'            => $user->id,
            'jenis'              => $jenis,
            'foto_original_path' => $data['foto_path'],
            'latitude'           => $data['latitude'] ?: null,
            'longitude'          => $data['longitude'] ?: null,
            'catatan'            => $data['catatan'] ?? null,
            'status'             => 'pending',
            'submitted_at'       => $now,
            'tanggal_laporan'    => today(),
        ]);

        try {
            $laporan->load(['pekerjaan', 'perusahaan', 'user']);
            $stamped = app(FotoStampingService::class)->stamp($data['foto_path'], $laporan);
            $laporan->update(['foto_stamped_path' => $stamped]);
        } catch (\Throwable $e) {
            // stamp failure is non-fatal
        }

        $this->form->fill();

        Notification::make()
            ->title('Laporan berhasil dikirim')
            ->body('Laporan ' . $jenis . ' tercatat pukul ' . $now->format('H:i') . ' WIB.')
            ->success()
            ->send();
    }

    private function getProyekOptions(): array
    {
        $perusahaanId = auth()->user()?->perusahaan_id;
        if (!$perusahaanId) {
            return [];
        }

        return Pekerjaan::whereHas('vendors', fn ($q) => $q->where('perusahaan.id', $perusahaanId))
            ->pluck('nama_pekerjaan', 'id')
            ->toArray();
    }
}
