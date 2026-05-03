<?php

namespace App\Services;

use App\Models\LaporanHarian;
use App\Models\MilestonePekerjaan;
use App\Models\Pekerjaan;
use App\Models\PekerjaanPersonil;
use App\Models\SystemSetting;
use App\Models\TerminPembayaran;
use Illuminate\Support\Facades\Http;

class AiChatService
{
    private string $apiKey;
    private string $model  = 'gpt-4o-mini';
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
    }

    public function chat(array $messages): string
    {
        if (empty($this->apiKey)) {
            return 'OPENAI_API_KEY belum dikonfigurasi di file .env.';
        }

        $history = $this->buildHistory($messages);
        $tools   = $this->getToolDefinitions();
        $system  = $this->getSystemPrompt();

        // Prepend system message
        $apiMessages = array_merge(
            [['role' => 'system', 'content' => $system]],
            $history
        );

        $response = $this->callOpenAI($apiMessages, $tools);

        // Tool-call loop (max 5 rounds)
        $iterations = 0;
        while (($response['choices'][0]['finish_reason'] ?? '') === 'tool_calls' && $iterations < 5) {
            $iterations++;
            $assistantMsg = $response['choices'][0]['message'];
            $apiMessages[] = $assistantMsg;

            foreach ($assistantMsg['tool_calls'] ?? [] as $toolCall) {
                $name      = $toolCall['function']['name'];
                $input     = json_decode($toolCall['function']['arguments'], true) ?? [];
                $result    = $this->executeTool($name, $input);

                $apiMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }

            $response = $this->callOpenAI($apiMessages, $tools);
        }

        return $response['choices'][0]['message']['content']
            ?? 'Tidak ada respons dari asisten.';
    }

    private function buildHistory(array $messages): array
    {
        $history = array_values(array_filter(
            $messages,
            fn ($m) => in_array($m['role'] ?? '', ['user', 'assistant'])
        ));

        // OpenAI also requires first message to be user
        while (!empty($history) && $history[0]['role'] !== 'user') {
            array_shift($history);
        }

        return $history;
    }

    private function callOpenAI(array $messages, array $tools): array
    {
        $response = Http::timeout(60)
            ->withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model'       => $this->model,
                'max_tokens'  => 1024,
                'messages'    => $messages,
                'tools'       => $tools,
                'tool_choice' => 'auto',
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message', $response->body());
            throw new \RuntimeException("OpenAI API: {$error}");
        }

        return $response->json();
    }

    private function getSystemPrompt(): string
    {
        $instansi = SystemSetting::get('nama_instansi', 'DPUTR Kabupaten Bandung');
        $tahun    = SystemSetting::get('tahun_anggaran_aktif', date('Y'));
        $userName = auth()->user()?->name ?? 'pengguna';

        return "Kamu adalah asisten AI untuk sistem Project Management {$instansi}. "
            . "Tahun anggaran aktif: {$tahun}. User saat ini: {$userName}. "
            . "Jawab dalam Bahasa Indonesia yang singkat, ramah, dan jelas. "
            . "Kamu PUNYA AKSES untuk MENGUBAH data — gunakan tools update_* untuk update progres, tandai milestone selesai, atau approve termin. "
            . "Selalu KONFIRMASI dulu ke user sebelum menjalankan tools update_*/approve_* (tampilkan apa yang akan diubah, tunggu user setuju). "
            . "Untuk pertanyaan informasi, langsung gunakan tools get_* tanpa konfirmasi. "
            . "Format angka uang dalam Rupiah (Rp) dengan titik pemisah ribuan. "
            . "Status traffic light: aman (hijau), waspada (kuning), kritis/terlambat (merah), selesai (abu-abu).";
    }

    private function getToolDefinitions(): array
    {
        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_dashboard_stats',
                    'description' => 'Ambil statistik overview: jumlah proyek, total nilai kontrak, rata-rata progres, dan distribusi status traffic light.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_pekerjaan_list',
                    'description' => 'Cari dan tampilkan daftar pekerjaan/proyek dengan filter opsional.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'search'       => ['type' => 'string',  'description' => 'Kata kunci nama pekerjaan atau nomor SPK'],
                            'status_waktu' => ['type' => 'string',  'description' => 'Filter: aman, waspada, kritis, terlambat, selesai, belum_mulai'],
                            'limit'        => ['type' => 'integer', 'description' => 'Jumlah maksimal hasil (default 10, max 20)'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_pekerjaan_detail',
                    'description' => 'Ambil detail lengkap satu pekerjaan berdasarkan ID atau nomor SPK.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'id'     => ['type' => 'integer', 'description' => 'ID pekerjaan'],
                            'no_spk' => ['type' => 'string',  'description' => 'Nomor SPK (bisa sebagian)'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_laporan_harian',
                    'description' => 'Ambil laporan harian terbaru atau pada tanggal tertentu.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'tanggal'      => ['type' => 'string',  'description' => 'Tanggal format YYYY-MM-DD (default: hari ini)'],
                            'pekerjaan_id' => ['type' => 'integer', 'description' => 'Filter per ID pekerjaan'],
                            'limit'        => ['type' => 'integer', 'description' => 'Jumlah maksimal (default 10)'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_personil_proyek',
                    'description' => 'Ambil daftar personil/tenaga ahli yang bertugas di proyek tertentu.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'pekerjaan_id' => ['type' => 'integer', 'description' => 'ID pekerjaan (wajib)'],
                        ],
                        'required' => ['pekerjaan_id'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'update_progres_pekerjaan',
                    'description' => 'Update persentase progres pekerjaan. KONFIRMASI ke user dulu sebelum panggil ini.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'pekerjaan_id'   => ['type' => 'integer', 'description' => 'ID pekerjaan'],
                            'progres_persen' => ['type' => 'number',  'description' => 'Progres baru dalam persen (0-100)'],
                        ],
                        'required' => ['pekerjaan_id', 'progres_persen'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'tandai_milestone_selesai',
                    'description' => 'Tandai milestone sebagai selesai dengan tanggal hari ini. KONFIRMASI ke user dulu.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'milestone_id' => ['type' => 'integer', 'description' => 'ID milestone'],
                        ],
                        'required' => ['milestone_id'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_milestone_pekerjaan',
                    'description' => 'Ambil daftar milestone untuk satu pekerjaan.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'pekerjaan_id' => ['type' => 'integer', 'description' => 'ID pekerjaan (wajib)'],
                        ],
                        'required' => ['pekerjaan_id'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_termin_pekerjaan',
                    'description' => 'Ambil daftar termin pembayaran untuk satu pekerjaan.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'pekerjaan_id' => ['type' => 'integer', 'description' => 'ID pekerjaan (wajib)'],
                        ],
                        'required' => ['pekerjaan_id'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'approve_termin',
                    'description' => 'Approve / setujui termin pembayaran (status diajukan → disetujui). KONFIRMASI ke user dulu.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'termin_id' => ['type' => 'integer', 'description' => 'ID termin'],
                            'catatan'   => ['type' => 'string',  'description' => 'Catatan PPK opsional'],
                        ],
                        'required' => ['termin_id'],
                    ],
                ],
            ],
        ];
    }

    private function executeTool(string $name, array $input): array
    {
        return match ($name) {
            'get_dashboard_stats'      => $this->toolDashboardStats(),
            'get_pekerjaan_list'       => $this->toolPekerjaanList($input),
            'get_pekerjaan_detail'     => $this->toolPekerjaanDetail($input),
            'get_laporan_harian'       => $this->toolLaporanHarian($input),
            'get_personil_proyek'      => $this->toolPersonilProyek($input),
            'get_milestone_pekerjaan'  => $this->toolMilestonePekerjaan($input),
            'get_termin_pekerjaan'     => $this->toolTerminPekerjaan($input),
            'update_progres_pekerjaan' => $this->toolUpdateProgres($input),
            'tandai_milestone_selesai' => $this->toolMilestoneSelesai($input),
            'approve_termin'           => $this->toolApproveTermin($input),
            default                    => ['error' => "Tool '{$name}' tidak dikenali"],
        };
    }

    private function toolDashboardStats(): array
    {
        $all         = Pekerjaan::with('statusPekerjaan')->get();
        $statusGroups = $all->groupBy(fn ($p) => $p->status_waktu);

        return [
            'total_pekerjaan'     => $all->count(),
            'total_nilai_kontrak' => 'Rp ' . number_format((float) $all->sum('nilai_kontrak'), 0, ',', '.'),
            'rata_rata_progres'   => round((float) $all->avg('progres_persen'), 1) . '%',
            'traffic_light'       => [
                'aman'        => $statusGroups->get('aman', collect())->count(),
                'waspada'     => $statusGroups->get('waspada', collect())->count(),
                'kritis'      => $statusGroups->get('kritis', collect())->count(),
                'terlambat'   => $statusGroups->get('terlambat', collect())->count(),
                'selesai'     => $statusGroups->get('selesai', collect())->count(),
                'belum_mulai' => $statusGroups->get('belum_mulai', collect())->count(),
            ],
        ];
    }

    private function toolPekerjaanList(array $input): array
    {
        $limit = min((int) ($input['limit'] ?? 10), 20);
        $query = Pekerjaan::with(['bidang', 'perusahaan', 'statusPekerjaan']);

        if (!empty($input['search'])) {
            $s = $input['search'];
            $query->where(fn ($q) => $q
                ->where('nama_pekerjaan', 'like', "%{$s}%")
                ->orWhere('no_spk', 'like', "%{$s}%"));
        }

        $items = $query->latest()->limit(50)->get();

        if (!empty($input['status_waktu'])) {
            $items = $items->filter(fn ($p) => $p->status_waktu === $input['status_waktu'])->values();
        }

        return $items->take($limit)->map(fn ($p) => [
            'id'            => $p->id,
            'no_spk'        => $p->no_spk,
            'nama'          => $p->nama_pekerjaan,
            'bidang'        => $p->bidang?->nama_bidang,
            'perusahaan'    => $p->perusahaan?->nama,
            'nilai_kontrak' => 'Rp ' . number_format((float) $p->nilai_kontrak, 0, ',', '.'),
            'progres'       => $p->progres_persen . '%',
            'status_waktu'  => $p->status_waktu,
            'sisa_hari'     => $p->sisa_hari,
        ])->values()->toArray();
    }

    private function toolPekerjaanDetail(array $input): array
    {
        $pekerjaan = null;

        if (!empty($input['id'])) {
            $pekerjaan = Pekerjaan::with(['bidang', 'perusahaan', 'jenisPekerjaan', 'statusPekerjaan'])
                ->find((int) $input['id']);
        } elseif (!empty($input['no_spk'])) {
            $pekerjaan = Pekerjaan::with(['bidang', 'perusahaan', 'jenisPekerjaan', 'statusPekerjaan'])
                ->where('no_spk', 'like', '%' . $input['no_spk'] . '%')
                ->first();
        }

        if (!$pekerjaan) {
            return ['error' => 'Pekerjaan tidak ditemukan'];
        }

        return [
            'id'             => $pekerjaan->id,
            'no_spk'         => $pekerjaan->no_spk,
            'nama'           => $pekerjaan->nama_pekerjaan,
            'bidang'         => $pekerjaan->bidang?->nama_bidang,
            'jenis'          => $pekerjaan->jenisPekerjaan?->nama_jenis,
            'perusahaan'     => $pekerjaan->perusahaan?->nama,
            'nilai_pagu'     => 'Rp ' . number_format((float) $pekerjaan->nilai_pagu, 0, ',', '.'),
            'nilai_kontrak'  => 'Rp ' . number_format((float) $pekerjaan->nilai_kontrak, 0, ',', '.'),
            'tanggal_mulai'  => $pekerjaan->tanggal_mulai?->format('d/m/Y'),
            'tanggal_akhir'  => $pekerjaan->tanggal_akhir?->format('d/m/Y'),
            'progres_persen' => $pekerjaan->progres_persen . '%',
            'status_waktu'   => $pekerjaan->status_waktu,
            'sisa_hari'      => $pekerjaan->sisa_hari,
            'catatan'        => $pekerjaan->catatan,
        ];
    }

    private function toolLaporanHarian(array $input): array
    {
        $tanggal = $input['tanggal'] ?? today()->format('Y-m-d');
        $limit   = min((int) ($input['limit'] ?? 10), 20);

        $query = LaporanHarian::with(['pekerjaan', 'user'])
            ->whereDate('tanggal_laporan', $tanggal);

        if (!empty($input['pekerjaan_id'])) {
            $query->where('pekerjaan_id', (int) $input['pekerjaan_id']);
        }

        $items = $query->latest('submitted_at')->limit($limit)->get();

        if ($items->isEmpty()) {
            return ['pesan' => "Tidak ada laporan pada tanggal {$tanggal}", 'data' => []];
        }

        return [
            'tanggal' => $tanggal,
            'jumlah'  => $items->count(),
            'data'    => $items->map(fn ($l) => [
                'id'        => $l->id,
                'pekerjaan' => $l->pekerjaan?->nama_pekerjaan,
                'jenis'     => $l->jenis,
                'user'      => $l->user?->name,
                'catatan'   => $l->catatan,
                'status'    => $l->status,
                'submitted' => $l->submitted_at?->format('H:i'),
            ])->toArray(),
        ];
    }

    private function toolPersonilProyek(array $input): array
    {
        if (empty($input['pekerjaan_id'])) {
            return ['error' => 'pekerjaan_id wajib diisi'];
        }

        $pekerjaan = Pekerjaan::find((int) $input['pekerjaan_id']);
        if (!$pekerjaan) {
            return ['error' => 'Pekerjaan tidak ditemukan'];
        }

        $personil = PekerjaanPersonil::with('tenagaAhli')
            ->where('pekerjaan_id', $pekerjaan->id)
            ->where('is_active', true)
            ->get();

        return [
            'pekerjaan' => $pekerjaan->nama_pekerjaan,
            'jumlah'    => $personil->count(),
            'data'      => $personil->map(fn ($p) => [
                'nama'            => $p->tenagaAhli?->nama,
                'jabatan_kontrak' => $p->jabatan_kontrak,
                'mulai_tugas'     => $p->tanggal_mulai_tugas?->format('d/m/Y'),
            ])->toArray(),
        ];
    }

    private function toolMilestonePekerjaan(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) {
            return ['error' => 'Pekerjaan tidak ditemukan'];
        }

        $milestones = MilestonePekerjaan::where('pekerjaan_id', $pekerjaan->id)
            ->orderBy('urutan')->get();

        return [
            'pekerjaan' => $pekerjaan->nama_pekerjaan,
            'jumlah'    => $milestones->count(),
            'data'      => $milestones->map(fn ($m) => [
                'id'                    => $m->id,
                'urutan'                => $m->urutan,
                'nama'                  => $m->nama,
                'tanggal_target'        => $m->tanggal_target?->format('d/m/Y'),
                'tanggal_selesai'       => $m->tanggal_selesai_aktual?->format('d/m/Y'),
                'progres_target_persen' => $m->progres_target_persen . '%',
                'status'                => $m->status,
                'sumber'                => $m->sumber,
            ])->toArray(),
        ];
    }

    private function toolTerminPekerjaan(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) {
            return ['error' => 'Pekerjaan tidak ditemukan'];
        }

        $termin = TerminPembayaran::where('pekerjaan_id', $pekerjaan->id)
            ->orderBy('nomor_termin')->get();

        return [
            'pekerjaan' => $pekerjaan->nama_pekerjaan,
            'jumlah'    => $termin->count(),
            'data'      => $termin->map(fn ($t) => [
                'id'                    => $t->id,
                'nomor'                 => $t->nomor_termin,
                'nama'                  => $t->nama_termin,
                'nilai'                 => 'Rp ' . number_format((float) $t->nilai_termin, 0, ',', '.'),
                'syarat_progres'        => $t->persen_progres_syarat . '%',
                'status'                => $t->status,
                'syarat_terpenuhi'      => $t->is_syarat_terpenuhi,
                'tanggal_pengajuan'     => $t->tanggal_pengajuan?->format('d/m/Y'),
            ])->toArray(),
        ];
    }

    private function toolUpdateProgres(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) {
            return ['error' => 'Pekerjaan tidak ditemukan'];
        }

        $progres = (float) ($input['progres_persen'] ?? -1);
        if ($progres < 0 || $progres > 100) {
            return ['error' => 'progres_persen harus antara 0-100'];
        }

        $oldProgres = $pekerjaan->progres_persen;
        $pekerjaan->update([
            'progres_persen' => $progres,
            'updated_by'     => auth()->id(),
        ]);

        return [
            'sukses'      => true,
            'pekerjaan'   => $pekerjaan->nama_pekerjaan,
            'progres_dari'=> $oldProgres . '%',
            'progres_ke'  => $progres . '%',
            'pesan'       => "Progres '{$pekerjaan->nama_pekerjaan}' berhasil diupdate dari {$oldProgres}% ke {$progres}%.",
        ];
    }

    private function toolMilestoneSelesai(array $input): array
    {
        $milestone = MilestonePekerjaan::find((int) ($input['milestone_id'] ?? 0));
        if (!$milestone) {
            return ['error' => 'Milestone tidak ditemukan'];
        }

        $milestone->update([
            'status'                 => 'selesai',
            'tanggal_selesai_aktual' => today(),
        ]);

        return [
            'sukses'    => true,
            'milestone' => $milestone->nama,
            'pesan'     => "Milestone '{$milestone->nama}' ditandai selesai pada " . today()->format('d/m/Y') . ".",
        ];
    }

    private function toolApproveTermin(array $input): array
    {
        $termin = TerminPembayaran::with('pekerjaan')->find((int) ($input['termin_id'] ?? 0));
        if (!$termin) {
            return ['error' => 'Termin tidak ditemukan'];
        }

        if ($termin->status !== 'diajukan') {
            return ['error' => "Termin ini berstatus '{$termin->status}', hanya termin 'diajukan' yang bisa di-approve"];
        }

        $termin->update([
            'status'              => 'disetujui',
            'tanggal_persetujuan' => today(),
            'approved_by'         => auth()->id(),
            'catatan_ppk'         => $input['catatan'] ?? $termin->catatan_ppk,
        ]);

        return [
            'sukses'   => true,
            'termin'   => $termin->nama_termin,
            'pekerjaan'=> $termin->pekerjaan?->nama_pekerjaan,
            'pesan'    => "Termin '{$termin->nama_termin}' untuk '{$termin->pekerjaan?->nama_pekerjaan}' berhasil disetujui.",
        ];
    }
}
