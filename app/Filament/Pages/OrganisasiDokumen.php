<?php

namespace App\Filament\Pages;

use App\Models\ChatUpload;
use App\Models\Dokumen;
use App\Models\Pekerjaan;
use App\Services\DocumentGroupingService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrganisasiDokumen extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-inbox-stack';
    protected static ?string $navigationLabel = 'Organisasi Dokumen';
    protected static ?string $title           = 'Organisasi Dokumen';
    protected static ?string $navigationGroup = 'Data';
    protected static ?int    $navigationSort  = 3;
    protected static string  $view            = 'filament.pages.organisasi-dokumen';

    public ?int $selectedId = null;
    public ?array $data = [];

    /** Mode grup: signature grup terpilih + proyek tujuan untuk bulk-organise. */
    public ?string $selectedGroupKey = null;
    public ?int $groupPekerjaanId = null;

    public static function getNavigationBadge(): ?string
    {
        $n = ChatUpload::unorganized()->count();

        return $n > 0 ? (string) $n : null;
    }

    public function mount(): void
    {
        $this->form->fill(['versi' => '1.0']);
    }

    /** @return \Illuminate\Support\Collection<int,ChatUpload> */
    public function getUnorganizedProperty()
    {
        return ChatUpload::unorganized()->latest()->get();
    }

    public function getSelectedProperty(): ?ChatUpload
    {
        return $this->selectedId ? ChatUpload::find($this->selectedId) : null;
    }

    /** Rekomendasi grup (≥2 file yang signature paketnya sama). */
    public function getRecommendationsProperty(): array
    {
        return app(DocumentGroupingService::class)->cluster($this->unorganized);
    }

    public function getSelectedGroupProperty(): ?array
    {
        if (! $this->selectedGroupKey) {
            return null;
        }

        return collect($this->recommendations)->firstWhere('key', $this->selectedGroupKey);
    }

    /** @return array<int,string> */
    public function pekerjaanOptions(): array
    {
        return Pekerjaan::orderBy('nama_pekerjaan')->pluck('nama_pekerjaan', 'id')->all();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('pekerjaan_id')
                    ->label('Proyek')
                    ->options(fn () => Pekerjaan::orderBy('nama_pekerjaan')->pluck('nama_pekerjaan', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('tipe')
                    ->label('Tipe Dokumen')
                    ->options(Dokumen::tipeOptions())
                    ->required(),

                Grid::make(2)->schema([
                    TextInput::make('nama_dokumen')->label('Nama Dokumen')->required()->maxLength(255),
                    TextInput::make('versi')->label('Versi')->default('1.0')->maxLength(20),
                ]),

                Textarea::make('keterangan')->label('Keterangan')->rows(2)->nullable(),
            ])
            ->statePath('data');
    }

    public function select(int $id): void
    {
        $upload = ChatUpload::findOrFail($id);
        $this->selectedId = $id;

        $this->form->fill([
            'nama_dokumen' => preg_replace('/\.[^.]+$/', '', $upload->original_name),
            'versi'        => '1.0',
            'tipe'         => $this->guessTipe($upload->original_name),
        ]);
    }

    /** Pilih grup rekomendasi → mode bulk-organise. */
    public function selectGroup(string $key): void
    {
        $this->selectedGroupKey = $key;
        $this->groupPekerjaanId = null;

        // tampilkan preview file pertama di grup
        $group = $this->selectedGroup;
        $this->selectedId = $group ? $group['files']->first()?->id : null;
    }

    public function clearGroup(): void
    {
        $this->selectedGroupKey = null;
        $this->groupPekerjaanId = null;
        $this->selectedId = null;
    }

    public function organize(): void
    {
        $upload = $this->selected;
        if (! $upload) {
            Notification::make()->title('Pilih file dulu di sebelah kiri.')->warning()->send();

            return;
        }

        $state = $this->form->getState();
        $dokumen = $this->storeAsDokumen($upload, (int) $state['pekerjaan_id'], $state['tipe'], $state['nama_dokumen'], $state['versi'] ?? null, $state['keterangan'] ?? null);

        if (! $dokumen) {
            return;
        }

        $this->selectedId = null;
        $this->form->fill(['versi' => '1.0']);

        Notification::make()
            ->title('Dokumen "' . $dokumen->nama_dokumen . '" dimasukkan ke proyek.')
            ->success()
            ->send();
    }

    /** Masukkan SEMUA file dalam grup ke satu proyek sekaligus. */
    public function organizeGroup(): void
    {
        $group = $this->selectedGroup;
        if (! $group) {
            Notification::make()->title('Grup tidak ditemukan.')->warning()->send();

            return;
        }
        if (! $this->groupPekerjaanId) {
            Notification::make()->title('Pilih proyek tujuan dulu.')->warning()->send();

            return;
        }

        $count = 0;
        foreach ($group['files'] as $upload) {
            $name = preg_replace('/\.[^.]+$/', '', $upload->original_name);
            if ($this->storeAsDokumen($upload, (int) $this->groupPekerjaanId, $this->guessTipe($upload->original_name), $name)) {
                $count++;
            }
        }

        $proyek = Pekerjaan::find($this->groupPekerjaanId)?->nama_pekerjaan ?? 'proyek';
        $this->clearGroup();

        Notification::make()
            ->title("{$count} file ({$group['label']}) dimasukkan ke \"{$proyek}\".")
            ->success()
            ->send();
    }

    /** Salin file fisik + buat record Dokumen + tandai upload sudah diorganise. */
    private function storeAsDokumen(ChatUpload $upload, int $pekerjaanId, ?string $tipe, string $nama, ?string $versi = null, ?string $keterangan = null): ?Dokumen
    {
        if (! is_file((string) $upload->abs_path)) {
            Notification::make()->title('File fisik tidak ditemukan: ' . $upload->original_name)->danger()->send();

            return null;
        }

        $ext  = pathinfo($upload->original_name, PATHINFO_EXTENSION);
        $dest = 'dokumen/' . date('Y') . '/' . Str::uuid()->toString() . ($ext ? '.' . $ext : '');

        // stream — aman untuk file besar
        $stream = fopen($upload->abs_path, 'rb');
        Storage::disk('local')->writeStream($dest, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $dokumen = Dokumen::create([
            'pekerjaan_id'       => $pekerjaanId,
            'tipe'               => $tipe ?: 'lainnya',
            'nama_dokumen'       => $nama,
            'versi'              => $versi ?: '1.0',
            'file_path'          => $dest,
            'file_original_name' => $upload->original_name,
            'file_size'          => $upload->size_bytes,
            'keterangan'         => $keterangan,
            'created_by'         => auth()->id(),
        ]);

        $upload->update(['organized_at' => now(), 'dokumen_id' => $dokumen->id]);

        return $dokumen;
    }

    protected function guessTipe(string $name): ?string
    {
        $n = strtolower($name);

        return match (true) {
            str_contains($n, 'kak')                                   => 'kak',
            str_contains($n, 'addendum')                              => 'addendum',
            // Bundel "Spk,Spmk,Ba ..." & SPK lepas → kontrak (cek sebelum spmk/bast).
            str_starts_with($n, 'spk') || str_contains($n, 'spk,')
                || str_contains($n, 'p fajr')                         => 'kontrak',
            str_contains($n, 'spmk')                                  => 'spmk',
            str_contains($n, 'bast')                                  => 'bast',
            str_contains($n, 'gambar')                                => 'gambar_kerja',
            default                                                   => 'lainnya',
        };
    }
}
