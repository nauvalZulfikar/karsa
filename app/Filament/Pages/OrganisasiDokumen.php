<?php

namespace App\Filament\Pages;

use App\Models\ChatUpload;
use App\Models\Dokumen;
use App\Models\Pekerjaan;
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
                    ->options(Dokumen::$tipeOptions)
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

    public function organize(): void
    {
        $upload = $this->selected;
        if (! $upload) {
            Notification::make()->title('Pilih file dulu di sebelah kiri.')->warning()->send();

            return;
        }
        if (! is_file((string) $upload->abs_path)) {
            Notification::make()->title('File fisik tidak ditemukan.')->danger()->send();

            return;
        }

        $state = $this->form->getState();

        $ext  = pathinfo($upload->original_name, PATHINFO_EXTENSION);
        $dest = 'dokumen/' . date('Y') . '/' . Str::uuid()->toString() . ($ext ? '.' . $ext : '');

        // Salin file (stream — aman untuk file besar) ke library Dokumen.
        $stream = fopen($upload->abs_path, 'rb');
        Storage::disk('local')->writeStream($dest, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $dokumen = Dokumen::create([
            'pekerjaan_id'       => $state['pekerjaan_id'],
            'tipe'               => $state['tipe'],
            'nama_dokumen'       => $state['nama_dokumen'],
            'versi'              => $state['versi'] ?: '1.0',
            'file_path'          => $dest,
            'file_original_name' => $upload->original_name,
            'file_size'          => $upload->size_bytes,
            'keterangan'         => $state['keterangan'] ?? null,
            'created_by'         => auth()->id(),
        ]);

        $upload->update(['organized_at' => now(), 'dokumen_id' => $dokumen->id]);

        $this->selectedId = null;
        $this->form->fill(['versi' => '1.0']);

        Notification::make()
            ->title('Dokumen "' . $dokumen->nama_dokumen . '" dimasukkan ke proyek.')
            ->success()
            ->send();
    }

    protected function guessTipe(string $name): ?string
    {
        $n = strtolower($name);

        return match (true) {
            str_contains($n, 'kak')                                   => 'kak',
            str_contains($n, 'spmk')                                  => 'spmk',
            str_contains($n, 'bast')                                  => 'bast',
            str_contains($n, 'addendum')                              => 'addendum',
            str_starts_with($n, 'spk') || str_contains($n, 'spk,')
                || str_contains($n, 'p fajr')                         => 'kontrak',
            str_contains($n, 'gambar')                                => 'gambar_kerja',
            default                                                   => 'lainnya',
        };
    }
}
