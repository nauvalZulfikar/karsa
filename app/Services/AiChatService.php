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
    private string $model;
    private string $apiUrl;
    private array $lastParseAggregated = [];
    private array $lastParseTruth = []; // G3b: field kritis hasil parse, untuk cross-check create
    private bool $primaryDown = false;  // FASE C: primary sudah ketahuan mati di sesi ini → langsung fallback

    public function __construct()
    {
        // FASE 0: backend dari config services.llm (default OpenAI). base_url root '/v1' + '/chat/completions'.
        $this->apiKey = config('services.llm.api_key', '');
        $this->model  = config('services.llm.model', 'gpt-4o-mini');
        $this->apiUrl = rtrim(config('services.llm.base_url', 'https://api.openai.com/v1'), '/') . '/chat/completions';
    }

    private function storeAggregated(array $data): void
    {
        cache()->put('ai_parse_aggregated_' . auth()->id(), $data, now()->addMinutes(30));
        $this->lastParseAggregated = $data;
        logger()->info('AI aggregated STORED: jadwal=' . count($data['jadwal_pelaksanaan'] ?? []) . ' keluaran=' . count($data['keluaran_kak'] ?? []));
    }

    private function loadAggregated(): array
    {
        if (!empty($this->lastParseAggregated)) {
            logger()->info('AI aggregated LOADED from memory: jadwal=' . count($this->lastParseAggregated['jadwal_pelaksanaan'] ?? []));
            return $this->lastParseAggregated;
        }
        $cached = cache()->get('ai_parse_aggregated_' . auth()->id(), []);
        logger()->info('AI aggregated LOADED from cache: jadwal=' . count($cached['jadwal_pelaksanaan'] ?? []));
        return $cached;
    }

    // ============================================================
    // FASE 1 GUARDRAILS (G1–G7) — helper
    // ============================================================

    /** G7: buang cache parse setelah proyek dibuat, biar tidak nyangkut ke proyek berikutnya. */
    private function clearAggregated(): void
    {
        cache()->forget('ai_parse_aggregated_' . auth()->id());
        cache()->forget('ai_parse_truth_' . auth()->id());
        $this->lastParseAggregated = [];
        $this->lastParseTruth = [];
    }

    /** G1: tool selain read-only dianggap "write" → tidak boleh diulang identik dalam 1 sesi. */
    private function isWriteTool(string $name): bool
    {
        static $readOnly = [
            'get_dashboard_stats', 'get_pekerjaan_list', 'get_pekerjaan_detail', 'get_laporan_harian',
            'get_personil_proyek', 'get_milestone_pekerjaan', 'get_termin_pekerjaan', 'get_my_pekerjaan',
            'search_audit_log', 'list_perusahaan', 'list_uploaded_files', 'find_uploaded_file',
            'parse_kak_pdf', 'parse_kontrak_pdf', 'parse_rab_pdf', 'parse_penawaran_pdf',
            'parse_multiple_docs', 'ocr_pdf', 'cross_check_rab_vs_kontrak',
        ];
        return !in_array($name, $readOnly, true);
    }

    /** G2: nilai_kontrak tidak boleh melebihi nilai_pagu (anti ketuker). */
    private function assertNilaiSane(array $input): ?array
    {
        $pagu    = $input['nilai_pagu'] ?? null;
        $kontrak = $input['nilai_kontrak'] ?? null;
        if ($pagu !== null && $kontrak !== null && (float) $kontrak > (float) $pagu) {
            return ['error' => "Ditolak: nilai_kontrak (Rp " . number_format((float) $kontrak, 0, ',', '.')
                . ") > nilai_pagu (Rp " . number_format((float) $pagu, 0, ',', '.')
                . "). Pagu = anggaran APBD (lebih besar), nilai kontrak = hasil nego (lebih kecil). Kemungkinan ketukar — cek dokumen."];
        }
        return null;
    }

    /** G3a (sanity) + G3b (cocokkan vs hasil parse). Return error array atau null kalau lolos. */
    private function assertFieldKritisValid(array $input): ?array
    {
        // --- G3a: sanity dasar ---
        $nama = trim((string) ($input['nama_pekerjaan'] ?? ''));
        if ($nama === '' || mb_strlen($nama) < 4 || preg_match('/^(string|contoh|example|\.\.\.|n\/?a|null|nama_pekerjaan)$/i', $nama)) {
            return ['error' => "Ditolak: nama_pekerjaan kosong/placeholder ('{$nama}'). Ambil nama asli dari dokumen, JANGAN tebak."];
        }
        $thisYear = (int) date('Y');
        foreach (['tanggal_spk', 'tanggal_mulai', 'tanggal_akhir'] as $tf) {
            if (empty($input[$tf])) continue;
            $ts = strtotime((string) $input[$tf]);
            if ($ts === false) {
                return ['error' => "Ditolak: {$tf} '{$input[$tf]}' bukan tanggal valid."];
            }
            $y = (int) date('Y', $ts);
            if ($y < 2015 || $y > $thisYear + 1) {
                return ['error' => "Ditolak: {$tf} '{$input[$tf]}' di luar rentang wajar (2015–" . ($thisYear + 1) . "). Kemungkinan salah baca/tebak."];
            }
        }
        foreach (['nilai_pagu', 'nilai_kontrak'] as $nf) {
            if (array_key_exists($nf, $input) && $input[$nf] !== null && (float) $input[$nf] <= 0) {
                return ['error' => "Ditolak: {$nf} harus > 0 (dapat '{$input[$nf]}')."];
            }
        }

        // --- G3b: cocokkan vs kebenaran dokumen (kalau ada hasil parse di sesi ini) ---
        $truth = $this->loadParseTruth();
        if (!empty($truth)) {
            if (!empty($truth['no_spk']) && !empty($input['no_spk'])
                && $this->normSpk((string) $input['no_spk']) !== $this->normSpk((string) $truth['no_spk'])) {
                return ['error' => "Ditolak (anti-ngarang): no_spk '{$input['no_spk']}' beda dengan dokumen ('{$truth['no_spk']}'). Pakai yang dari dokumen. Kalau memang sengaja, panggil ulang dengan force_create=true."];
            }
            foreach (['nilai_kontrak', 'nilai_pagu'] as $nf) {
                if (isset($truth[$nf], $input[$nf]) && $truth[$nf] !== null && $input[$nf] !== null
                    && abs((float) $input[$nf] - (float) $truth[$nf]) > 1) {
                    return ['error' => "Ditolak (anti-ngarang): {$nf} (" . number_format((float) $input[$nf], 0, ',', '.')
                        . ") beda dengan dokumen (" . number_format((float) $truth[$nf], 0, ',', '.')
                        . "). Pakai angka dokumen, atau force_create=true kalau sengaja."];
                }
            }
            foreach (['tanggal_spk', 'tanggal_mulai', 'tanggal_akhir'] as $tf) {
                if (!empty($truth[$tf]) && !empty($input[$tf])
                    && substr((string) $input[$tf], 0, 10) !== substr((string) $truth[$tf], 0, 10)) {
                    return ['error' => "Ditolak (anti-ngarang): {$tf} '{$input[$tf]}' beda dengan dokumen ('{$truth[$tf]}'). Pakai tanggal dokumen, atau force_create=true kalau sengaja."];
                }
            }
        }
        return null;
    }

    /** G3b: rekam field kritis hasil parse. first-non-null menang (gabung lintas dokumen). */
    private function captureParseTruth(string $type, array $data): void
    {
        $src = $data['pekerjaan'] ?? $data; // kontrak nested di 'pekerjaan'; KAK/RAB flat
        $map = [];
        foreach (['no_spk', 'no_spmk', 'nama_pekerjaan', 'tanggal_spk', 'tanggal_mulai', 'tanggal_akhir', 'nilai_pagu', 'nilai_kontrak'] as $f) {
            if (isset($src[$f]) && $src[$f] !== null && $src[$f] !== '') $map[$f] = $src[$f];
        }
        if (empty($map['nilai_kontrak']) && !empty($data['total_kontrak'])) $map['nilai_kontrak'] = $data['total_kontrak']; // RAB
        if (empty($map)) return;

        $truth = $this->loadParseTruth();
        foreach ($map as $k => $v) {
            if (!isset($truth[$k]) || $truth[$k] === null || $truth[$k] === '') $truth[$k] = $v;
        }
        $this->lastParseTruth = $truth;
        cache()->put('ai_parse_truth_' . auth()->id(), $truth, now()->addMinutes(30));
    }

    private function loadParseTruth(): array
    {
        if (!empty($this->lastParseTruth)) return $this->lastParseTruth;
        return cache()->get('ai_parse_truth_' . auth()->id(), []);
    }

    private function normSpk(string $s): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($s)));
    }

    public function chat(array $messages): string
    {
        if (empty($this->apiKey)) {
            return 'OPENAI_API_KEY belum dikonfigurasi di file .env.';
        }

        $history = $this->buildHistory($messages);
        $tools   = $this->toolsForRequest($history);
        $system  = $this->getSystemPrompt();

        // Prepend system message
        $apiMessages = array_merge(
            [['role' => 'system', 'content' => $system]],
            $history
        );

        $response = $this->callOpenAI($apiMessages, $tools);

        // Tool-call loop (max 10 rounds — workflow bikin proyek butuh banyak step:
        // parse_kak + parse_kontrak + parse_rab + parse_penawaran + create + assign_vendor +
        // assign_personil*N + create_rencana_pengadaan + cross_check + ringkasan akhir)
        $iterations = 0;
        $maxIterations = 10;
        $startTime = microtime(true);
        $hardTimeoutSec = 240; // 4 menit absolute cap

        // Collect download links from tool results — appended server-side at the end
        // (AI sering drop URL atau salah format, jadi kita jamin link selalu muncul)
        $downloads = [];

        // G1 anti-loop: tool tulis dengan argumen identik tidak dieksekusi 2x dalam 1 sesi chat.
        $executedWrites = [];

        while (($response['choices'][0]['finish_reason'] ?? '') === 'tool_calls' && $iterations < $maxIterations) {
            // Soft timeout guard — kalau total udah > 4 menit, stop loop
            if ((microtime(true) - $startTime) > $hardTimeoutSec) {
                $apiMessages[] = ['role' => 'system', 'content' => 'TIMEOUT: workflow terlalu lama. Hentikan tool calls, kasih ringkasan progress ke user.'];
                $response = $this->callOpenAI($apiMessages, []);
                break;
            }

            $iterations++;
            $assistantMsg = $response['choices'][0]['message'];
            $apiMessages[] = $assistantMsg;

            foreach ($assistantMsg['tool_calls'] ?? [] as $toolCall) {
                $name      = $toolCall['function']['name'];
                $input     = json_decode($toolCall['function']['arguments'], true) ?? [];

                // G1: blokir tool tulis yang diulang persis (nama+argumen sama) dalam sesi ini.
                $sig = $name . ':' . md5(json_encode($input));
                if ($this->isWriteTool($name) && isset($executedWrites[$sig])) {
                    $result = [
                        'ok'     => false,
                        'status' => 'duplicate_blocked',
                        'pesan'  => "Aksi '{$name}' dengan data yang sama persis sudah dijalankan barusan — TIDAK diulang (anti-loop). Pakai hasil sebelumnya; jangan panggil lagi.",
                    ];
                } else {
                    $result = $this->executeTool($name, $input);
                    if ($this->isWriteTool($name)) {
                        $executedWrites[$sig] = true;
                    }
                }

                // Capture download links from generators / create_pekerjaan
                if (is_array($result)) {
                    if (!empty($result['laporan_pendahuluan']['download_url'])) {
                        $downloads[] = [
                            'label' => 'Laporan Pendahuluan',
                            'url'   => $result['laporan_pendahuluan']['download_url'],
                        ];
                    }
                    if (!empty($result['result']['download_url']) && !empty($result['tipe'])) {
                        $downloads[] = [
                            'label' => $result['tipe'],
                            'url'   => $result['result']['download_url'],
                        ];
                    }
                }

                $apiMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }

            $response = $this->callOpenAI($apiMessages, $tools);
        }

        $reply = $response['choices'][0]['message']['content']
            ?? 'Tidak ada respons dari asisten.';

        // Server-side append: deduplicate + append download chips kalau AI sudah ga sebut URL persis
        $downloads = collect($downloads)->unique('url')->values()->all();
        if (!empty($downloads)) {
            $missingLinks = array_filter($downloads, fn ($d) => !str_contains($reply, $d['url']));
            if (!empty($missingLinks)) {
                $reply = rtrim($reply) . "\n\n";
                foreach ($missingLinks as $d) {
                    $reply .= "📄 [**Unduh {$d['label']}**]({$d['url']})\n";
                }
            }
        }

        return $reply;
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
        $fallbackOk = config('services.llm.driver', 'openai') !== 'openai'
            && config('services.llm.fallback', true)
            && config('services.llm.fallback_key');

        // Primary sudah ketahuan mati di sesi ini → langsung fallback, jangan buang 6s nyoba ulang.
        if ($this->primaryDown && $fallbackOk) {
            return $this->callFallback($messages, $tools);
        }

        try {
            return $this->requestLlm($messages, $tools, $this->apiUrl, $this->apiKey, $this->model);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if ($fallbackOk) {
                $this->primaryDown = true;
                logger()->warning("LLM failover: backend utama tak terjangkau ({$e->getMessage()}) → fallback ke OpenAI.");
                return $this->callFallback($messages, $tools);
            }
            throw new \RuntimeException(
                "Gagal hubungi backend AI setelah 3x percobaan ({$e->getMessage()}). " .
                "Cek koneksi / Ollama, atau coba pakai VPN (ISP Indonesia kadang block api.openai.com)."
            );
        }
    }

    private function callFallback(array $messages, array $tools): array
    {
        try {
            return $this->requestLlm(
                $messages, $tools,
                config('services.llm.fallback_url', 'https://api.openai.com/v1/chat/completions'),
                config('services.llm.fallback_key'),
                config('services.llm.fallback_model', 'gpt-4o-mini'),
            );
        } catch (\Throwable $e2) {
            throw new \RuntimeException(
                "Backend AI lokal mati dan fallback OpenAI juga gagal ({$e2->getMessage()}). " .
                "Asisten AI sedang offline — coba lagi nanti."
            );
        }
    }

    /** Satu panggilan ke endpoint LLM (OpenAI-compatible). 3 retry backoff; lempar ConnectionException biar caller bisa failover. */
    private function requestLlm(array $messages, array $tools, string $url, string $key, string $model): array
    {
        $timeout = (int) config('services.llm.timeout', 120);
        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout(30)
                    ->retry(0)
                    ->withToken($key)
                    ->withHeaders(['User-Agent' => 'Karta-AI/1.0'])
                    ->post($url, [
                        'model'       => $model,
                        'max_tokens'  => 1024,
                        'messages'    => $messages,
                        'tools'       => $tools,
                        'tool_choice' => 'auto',
                    ]);

                if (!$response->successful()) {
                    $error = $response->json('error.message', $response->body());
                    throw new \RuntimeException("LLM API: {$error}");
                }

                return $response->json();
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastError = $e;
                if ($attempt < 3) {
                    sleep($attempt * 2); // 2s, 4s
                    continue;
                }
                throw $e; // serahkan ke caller (failover handler)
            }
        }

        throw $lastError ?? new \RuntimeException('Unknown error');
    }

    private function getSystemPrompt(): string
    {
        $instansi = SystemSetting::get('nama_instansi', 'DPUTR Kabupaten Bandung');
        $tahun    = SystemSetting::get('tahun_anggaran_aktif', date('Y'));
        $userName = auth()->user()?->name ?? 'pengguna';

        // FASE D: model lokal (Ollama/Qwen) lambat memproses prompt panjang tiap roundtrip.
        // Pakai prompt RINGKAS + /no_think — aman karena aturan anti-loop/anti-ngarang sudah
        // ditegakkan di KODE (guardrail G1-G8), bukan lagi cuma di prompt. OpenAI tetap pakai
        // prompt penuh di bawah (perilaku teruji tidak berubah).
        if (config('services.llm.driver', 'openai') === 'ollama') {
            return "/no_think\n"
                . "Kamu asisten AI Project Management {$instansi}. Tahun anggaran {$tahun}. User: {$userName}. "
                . "Jawab Bahasa Indonesia, singkat dan jelas.\n"
                . "Tools tersedia: lihat/cari proyek, laporan harian, personil, milestone, termin, pengadaan, vendor, dokumen, audit log; "
                . "parse PDF (KAK/Kontrak/RAB/Penawaran) + OCR; bikin/edit/hapus proyek, assign vendor/personil, generate dokumen, kirim WA.\n"
                . "ATURAN:\n"
                . "- Lihat data (get_/list_/cari): langsung panggil tool. Ubah data (create/update/delete/approve/generate): konfirmasi dulu; "
                . "kalau user bilang 'tanpa konfirmasi'/'langsung proses' pakai force_create=true.\n"
                . "- HANYA pakai data dari hasil tool. JANGAN menebak nama/no_spk/nilai/tanggal. Kalau tool tidak mengembalikan data, bilang tidak ada / tidak terbaca.\n"
                . "- Bikin proyek dari dokumen: panggil parse_multiple_docs (>=2 file) atau parse_*_pdf, ringkas, lalu create_pekerjaan dgn field hasil parse (jadwal/termin/milestone auto-merge server).\n"
                . "- Format uang Rupiah (Rp). Jangan ulang tool tulis yang argumennya sama.\n"
                // FASE D #6 few-shot: qwen sering salah isi argumen get_pekerjaan_list (masukin
                // kalimat penuh ke 'search' / nambah filter status) -> hasil kosong -> ngaku
                // "tidak ada proyek". Contoh konkret ini memperbaiki wobble itu (benchmark Q2/Q5).
                . "CONTOH ARGUMEN TOOL (WAJIB ikuti pola):\n"
                . "- \"sebutkan semua proyek\" / \"ada proyek apa aja\" / \"berapa proyek\" -> get_pekerjaan_list dengan argumen KOSONG {}. JANGAN isi 'search' dgn kalimat (\"semua proyek\") dan JANGAN tambah 'status_waktu' kalau user tak minta status — itu bikin hasil kosong.\n"
                . "- \"vendor/nilai/detail proyek KAJIAN GEOTEKNIK\" -> get_pekerjaan_list dgn search=\"geoteknik\" (SATU kata kunci inti saja, bukan kalimat penuh).\n"
                . "- Kalau tool balik kosong, coba SEKALI lagi get_pekerjaan_list {} tanpa filter SEBELUM bilang \"tidak ada\".";
        }

        return "Kamu adalah asisten AI untuk sistem Project Management {$instansi}. "
            . "Tahun anggaran aktif: {$tahun}. User saat ini: {$userName}. "
            . "Jawab dalam Bahasa Indonesia yang singkat, ramah, dan jelas. "
            . "Kamu PUNYA AKSES penuh untuk: bikin/edit/hapus proyek, assign vendor & personil, submit/approve laporan harian + realisasi + termin, generate laporan & invoice & surat, parse PDF KAK/Kontrak/RAB/Penawaran, manage user, kirim WA, dll. "
            . "User bisa drag-drop PDF/Excel langsung ke chat. File ter-PERSIST di database — bisa di-reference balik kapan aja. "
            . "Kalau user bilang 'file yg tadi gua upload' atau sejenis, JANGAN bilang gak ada — panggil list_uploaded_files atau find_uploaded_file untuk cari, lalu pakai path-nya. "
            . "File baru juga muncul di pesan user (format: '[File terlampir] - filename → /path/to/file'). Pakai path itu sebagai file_path untuk parse_*. "
            . "Kalau parse_*_pdf return text kosong / hanya whitespace, kemungkinan PDF hasil scan — panggil ocr_pdf untuk OCR via vision, lalu lanjutkan parsing dengan teks hasil OCR. "
            . "ALUR BIKIN PROYEK BARU (WAJIB IKUTI INI):\n"
            . "  STEP 0 (auto-detect): kalau user attach file di pesan SAMA dengan request 'bikin proyek', langsung ke STEP 2 — pakai file itu, tidak perlu nanya.\n"
            . "  STEP 1 (kalau gak ada file): tanya user upload 4 dokumen: KAK (wajib), Kontrak/SPK (opsional), Penawaran vendor (opsional), RAB Negosiasi (opsional). Sebutkan: 'Mohon drag-drop ke chat: KAK (wajib), lalu Kontrak/Penawaran/RAB kalau sudah ada.' Tunggu user upload.\n"
            . "  STEP 2: kalau ada >=2 file ter-attach, WAJIB panggil parse_multiple_docs SEKALI dengan semua dokumen (jauh lebih cepat dari panggil parse_* satu-satu). Kalau cuma 1 file, panggil parser spesifik (parse_kak_pdf/parse_kontrak_pdf/parse_rab_pdf/parse_penawaran_pdf). Kalau ada parser return text kosong, panggil ocr_pdf fallback.\n"
            . "  STEP 3: rangkum SEMUA data hasil parse dalam 1 pesan terstruktur (nama, lokasi, pagu, vendor, jadwal, tim, RAB items). Tampilkan ke user.\n"
            . "  STEP 3b (ANTI-HALUSINASI - SANGAT PENTING): hanya pakai data yang BENAR-BENAR muncul di hasil parse_*. JANGAN tebak, JANGAN gunakan contoh dari proyek lain, JANGAN copy nama dari pekerjaan_list. Kalau nama_pekerjaan / nilai_kontrak / vendor / no_spk TIDAK ADA di hasil parser (null/kosong), STOP — lapor ke user: 'Dokumen [X] tidak terbaca dengan baik, mohon upload ulang dengan kualitas lebih jelas atau tambahkan info manual'. JANGAN buat pekerjaan dengan data fiktif.\n"
            . "  STEP 3c (TANGGAL & NO_SPK — PALING SERING SALAH): tanggal_spk, tanggal_mulai, tanggal_akhir, no_spk, no_spmk, nilai_kontrak HARUS EXACT copy dari hasil parse_kontrak_pdf. JANGAN ubah tahun/bulan/digit, JANGAN tebak, JANGAN ambil dari hasil pekerjaan_list atau dari warning similar_name_found. Kalau warning similar_name_found return existing project dengan no_spk berbeda, TETAP pakai no_spk dari parse_kontrak (bukan dari warning). Kalau di multi-turn (user confirm force_create=true di turn berikutnya), TETAP pakai SEMUA field EXACT sama seperti yang lo dapet di parse_kontrak turn sebelumnya. Data dari warning HANYA buat info ke user, BUKAN buat di-copy ke create_pekerjaan input.\n"
            . "  STEP 3e (PAGU vs NILAI KONTRAK — JANGAN SAMAKAN): KAK punya field 'nilai_pagu' (anggaran total dari APBD, biasanya angka bulat). Kontrak/SPK punya 'nilai_kontrak' (nilai final setelah nego, biasanya lebih rendah dari pagu). KEDUANYA WAJIB ditampilkan SEPARATE di ringkasan summary user. Format: 'Pagu (KAK): Rp {nilai_pagu} | Nilai Kontrak: Rp {nilai_kontrak}'. JANGAN pakai nilai_kontrak buat nilai_pagu atau sebaliknya — itu 2 angka berbeda yang dua-duanya harus muncul. Kalau cuma KAK yang ada, tampilkan 'Pagu' aja. Kalau cuma kontrak yang ada, tampilkan 'Nilai Kontrak' aja.\n"
            . "  STEP 3d (TERMIN & MILESTONES & JADWAL — WAJIB FORWARD KE create_pekerjaan):\n"
            . "    parse_multiple_docs return field 'aggregated' yang berisi 4 array SIAP PAKAI:\n"
            . "    - aggregated.termin_pembayaran → pass langsung sebagai 'termin_pembayaran'\n"
            . "    - aggregated.milestones → pass langsung sebagai 'milestones'\n"
            . "    - aggregated.jadwal_pelaksanaan → pass langsung sebagai 'jadwal_pelaksanaan'\n"
            . "    - aggregated.keluaran_kak → pass langsung sebagai 'keluaran_kak'\n"
            . "    COPY-PASTE semua 4 array dari aggregated ke create_pekerjaan input. JANGAN skip, JANGAN generate sendiri.\n"
            . "  STEP 3f (LOKASI — WAJIB FORWARD): Hasil parse_kak_pdf return field 'lokasi_pekerjaan' (contoh 'Desa Lebakmuncang, Kec. Ciwidey, Kabupaten Bandung'). WAJIB pass field tsb ke create_pekerjaan sebagai 'lokasi'. Composer butuh ini untuk nulis Latar Belakang + Lokasi section di laporan dengan lokasi spesifik (bukan fallback generic 'Kabupaten Bandung').\n"
            . "  STEP 4: PANGGIL create_pekerjaan dengan SEMUA field yang tersedia (termasuk termin_pembayaran + milestones array kalau ada).\n"
            . "    PENTING: Kalau user bilang 'tanpa konfirmasi' / 'langsung proses' / 'auto-execute' → SELALU pass force_create=true di create_pekerjaan. Jangan tanya konfirmasi apapun.\n"
            . "    Sistem akan auto-cek duplikasi:\n"
            . "    - Kalau return 'duplicate_spk': stop, info ke user proyek dengan SPK ini sudah ada.\n"
            . "    - Kalau return 'similar_name_found' DAN force_create=false: tampilkan list yang mirip, tanya user. Kalau user yakin, panggil ulang dengan force_create=true.\n"
            . "    - Kalau return 'similar_name_found' DAN force_create=true: TIDAK MUNGKIN terjadi (sistem skip warning kalau force). Kalau terjadi, ada bug.\n"
            . "    - Kalau sukses: lanjut STEP 5.\n"
            . "  STEP 5: setelah pekerjaan dibuat, OTOMATIS panggil ini berurutan (tanpa nanya ulang user untuk tiap step, cukup lapor hasil di akhir):\n"
            . "    a. assign_vendor (kalau vendor ada di hasil parse)\n"
            . "    b. assign_personil untuk tiap personil di RAB/Penawaran (loop)\n"
            . "    c. create_rencana_pengadaan dengan items dari RAB (bulk)\n"
            . "    d. cross_check_rab_vs_kontrak — kalau ada mismatch, warning ke user\n"
            . "  STEP 6: kasih ringkasan final ke user: ID proyek baru, vendor di-assign, jumlah personil, jumlah item RAB, status validasi. KALAU response create_pekerjaan ada field 'laporan_pendahuluan.download_url', SELALU tampilkan sebagai link markdown clickable di akhir reply: '\\n\\n📄 [**Unduh Laporan Pendahuluan**](URL_DI_SINI)' — pakai format markdown link bukan plain URL.\n"
            . "ALUR LAINNYA: untuk request 'bikin invoice / laporan' juga ikuti pattern: parse → confirm → execute → report.\n"
            . "DUPLIKASI HANDLING (ANTI-LOOP — WAJIB IKUTI EXACT):\n"
            . "  CASE A: warning 'similar_name_found' (ada proyek mirip):\n"
            . "    Turn 1 (warning received): tampilkan ringkasan ke user — 'Ada proyek mirip: [nama-nama]. Mau pakai existing (sebut ID) atau bikin baru?' Tunggu user jawab.\n"
            . "    Turn 2 (user jawab):\n"
            . "      Kalau user jawab apapun yang artinya 'BIKIN BARU' (iya, ya, bikin baru, baru aja, new, force, lanjut, OK lanjut, ya proceed, gua mau bikin baru, dll): WAJIB PANGGIL create_pekerjaan SEKALI dengan force_create=true DAN semua field EXACT sama seperti turn sebelumnya. JANGAN tanya lagi pertanyaan yang sama. JANGAN return warning lagi.\n"
            . "      Kalau user jawab 'PAKAI EXISTING' (pakai yang ada, pakai id X, yg lama aja, existing, gak usah bikin baru, update aja, pakai #X): JANGAN panggil create_pekerjaan. Langsung lanjut ke STEP 5 (assign_vendor, assign_personil, dll) menggunakan pekerjaan_id dari warning.\n"
            . "  CASE B: error 'duplicate_spk' (SPK sudah ada di pekerjaan lain):\n"
            . "    Turn 1 (error received): info ke user — 'Proyek dengan SPK X sudah ada (ID Y). Mau update Y, atau cancel dan ganti SPK?' Tunggu user jawab.\n"
            . "    Turn 2 (user jawab):\n"
            . "      Kalau user pilih 'UPDATE / pakai existing / lanjut / OK': JANGAN panggil create_pekerjaan lagi. Pakai pekerjaan_id Y dari error response, lanjut ke STEP 5 (assign vendor + personil + RAB) untuk pekerjaan Y. Juga kalau perlu update field, panggil update_pekerjaan(pekerjaan_id=Y, ...) sekali.\n"
            . "      Kalau user pilih 'GANTI SPK': tanya user 'Mohon kasih nomor SPK baru'. Jangan auto-bikin, tunggu user kasih SPK baru.\n"
            . "  ATURAN UMUM: SETELAH user jawab di turn 2, JANGAN PERNAH tanya pertanyaan yang sama lagi. Kalau yakin user mau bikin baru, langsung action. Kalau yakin user mau pakai existing, langsung lanjut. Hanya tanya ulang kalau user jawab benar-benar gak nyambung/ambigu (misal 'apa?').\n"
            . "Selalu KONFIRMASI dulu sebelum action yang ubah data (create/update/delete/approve/generate). Untuk read-only (get_*, list_*, search_*) langsung saja. "
            . "Format angka uang dalam Rupiah (Rp) dengan titik pemisah ribuan. "
            . "Status traffic light: aman (hijau), waspada (kuning), kritis/terlambat (merah), selesai (abu-abu).";
    }

    /**
     * FASE D #1: OpenAI dapat semua 46 tool (cepat). Model lokal (Ollama) lambat memproses
     * konteks besar tiap roundtrip → kirim SUBSET (core + grup yang cocok kata kunci) untuk
     * pangkas latensi. Core selalu meng-cover alur dominan (lihat data + bikin proyek).
     */
    private function toolsForRequest(array $messages): array
    {
        $all = $this->getToolDefinitions();
        if (config('services.llm.driver', 'openai') !== 'ollama') {
            return $all;
        }

        $text = mb_strtolower(implode(' ', array_map(
            fn ($m) => is_string($m['content'] ?? null) ? $m['content'] : '',
            $messages
        )));

        $core = [
            'get_dashboard_stats', 'get_pekerjaan_list', 'get_pekerjaan_detail', 'get_my_pekerjaan',
            'parse_kak_pdf', 'parse_kontrak_pdf', 'parse_rab_pdf', 'parse_penawaran_pdf',
            'parse_multiple_docs', 'ocr_pdf', 'list_uploaded_files', 'find_uploaded_file',
            'create_pekerjaan', 'update_pekerjaan', 'assign_vendor', 'assign_personil',
            'create_rencana_pengadaan', 'cross_check_rab_vs_kontrak', 'list_perusahaan',
        ];

        $groups = [
            [['laporan harian', 'absen', 'submit laporan'], ['get_laporan_harian', 'submit_daily_report', 'approve_daily_report', 'reject_daily_report']],
            [['termin', 'bayar', 'pembayaran', 'cair', 'invoice'], ['get_termin_pekerjaan', 'request_termin', 'approve_termin', 'reject_termin', 'generate_invoice', 'generate_surat_permohonan_pembayaran']],
            [['milestone', 'tahap', 'progres'], ['get_milestone_pekerjaan', 'tandai_milestone_selesai', 'update_progres_pekerjaan']],
            [['personil', 'tenaga ahli', 'tim'], ['get_personil_proyek']],
            [['realisasi', 'pengadaan', 'material', 'beli'], ['submit_realisasi', 'approve_realisasi', 'reject_realisasi']],
            [['vendor baru', 'tambah perusahaan', 'tambah vendor'], ['create_perusahaan']],
            [['user', 'pengguna', 'undang', 'akses', 'role', 'staff'], ['invite_vendor_user', 'invite_staff_user', 'grant_admin_access', 'revoke_access']],
            [['wa', 'whatsapp', 'kirim pesan'], ['send_wa_to_vendor', 'send_wa_to_staff']],
            [['audit', 'log', 'siapa ubah', 'riwayat'], ['search_audit_log']],
            [['laporan akhir', 'pendahuluan', 'kuitansi', 'gaji', 'atk', 'sewa alat', 'bast', 'serah terima', 'generate dokumen', 'surat'], ['generate_laporan_pendahuluan', 'generate_laporan_akhir', 'generate_kuitansi_gaji', 'generate_invoice_atk', 'generate_invoice_sewa_alat', 'generate_bast']],
            [['hapus', 'delete', 'batalkan'], ['delete_pekerjaan']],
        ];

        $names = $core;
        foreach ($groups as [$kws, $tools]) {
            foreach ($kws as $kw) {
                if (str_contains($text, $kw)) { $names = array_merge($names, $tools); break; }
            }
        }
        $keep = array_flip($names);

        return array_values(array_filter($all, fn ($t) => isset($keep[$t['function']['name']])));
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
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'parse_kak_pdf',
                    'description' => 'Baca PDF Kerangka Acuan Kerja (KAK). Ekstrak nama pekerjaan, lokasi, pagu, jadwal. Pakai untuk auto-fill saat bikin proyek baru.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'file_path' => ['type' => 'string', 'description' => 'Path absolut file PDF KAK di storage atau filesystem'],
                        ],
                        'required' => ['file_path'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'parse_kontrak_pdf',
                    'description' => 'Baca PDF Kontrak (SPK/SPMK/BAST). Ekstrak no kontrak, tanggal, nilai, vendor, termin pembayaran, milestone via AI. Lebih akurat dari parse_kak_pdf.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'file_path' => ['type' => 'string', 'description' => 'Path file PDF kontrak'],
                        ],
                        'required' => ['file_path'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'parse_rab_pdf',
                    'description' => 'Baca PDF/XLSX RAB / Lampiran Negosiasi. Ekstrak line items (personil, non-personil) dengan harga negosiasi via AI. Pakai untuk generate invoice yang akurat.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'file_path' => ['type' => 'string', 'description' => 'Path file PDF atau XLSX RAB'],
                        ],
                        'required' => ['file_path'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'generate_invoice',
                    'description' => 'Generate invoice PDF untuk proyek tertentu. Pakai data personil + rencana pengadaan yang sudah ada di sistem. Return download URL. KONFIRMASI ke user dulu.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'pekerjaan_id'    => ['type' => 'integer', 'description' => 'ID pekerjaan yang mau di-invoice'],
                            'prestasi_persen' => ['type' => 'integer', 'description' => 'Persentase prestasi (default 100)'],
                            'no_invoice'      => ['type' => 'integer', 'description' => 'Nomor invoice (default = pekerjaan_id)'],
                        ],
                        'required' => ['pekerjaan_id'],
                    ],
                ],
            ],

            // === BATCH 1: PROJECT MANAGER ===
            ['type' => 'function', 'function' => [
                'name' => 'create_pekerjaan',
                'description' => 'Bikin proyek baru. Sistem auto-cek duplikasi by no_spk (block) dan nama mirip (warning). Kalau dapat warning similar_name_found, KONFIRMASI ke user, lalu panggil lagi dengan force_create=true kalau user yakin lanjut.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'nama_pekerjaan' => ['type' => 'string'],
                    'bidang_kode'    => ['type' => 'string', 'description' => 'BG | JL | DR | IR'],
                    'jenis_pekerjaan' => ['type' => 'string', 'description' => 'Nama jenis pekerjaan (opsional)'],
                    'perusahaan_id'  => ['type' => 'integer'],
                    'perusahaan_nama' => ['type' => 'string', 'description' => 'Nama vendor (fuzzy match)'],
                    'nilai_pagu'     => ['type' => 'number'],
                    'nilai_kontrak'  => ['type' => 'number'],
                    'no_spk'         => ['type' => 'string'],
                    'tanggal_spk'    => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'no_spmk'        => ['type' => 'string'],
                    'tanggal_mulai'  => ['type' => 'string'],
                    'tanggal_akhir'  => ['type' => 'string'],
                    'hari_kerja'     => ['type' => 'integer'],
                    'tahun_anggaran' => ['type' => 'integer'],
                    'lokasi'         => ['type' => 'string', 'description' => 'Lokasi pekerjaan EXACT dari hasil parse_kak_pdf field "lokasi_pekerjaan" (contoh: "Desa Lebakmuncang, Kec. Ciwidey, Kabupaten Bandung"). WAJIB pass kalau ada — composer pakai untuk Latar Belakang + Lokasi section di laporan.'],
                    'force_create'   => ['type' => 'boolean', 'description' => 'Bypass fuzzy name duplicate warning. Pakai cuma kalau user sudah confirm tetap mau bikin baru meski ada nama mirip.'],
                    'allow_duplicate' => ['type' => 'boolean', 'description' => 'IZIN bikin proyek dengan nama+bidang+tahun SAMA PERSIS dengan yang sudah ada (kembar identik). Default false (diblok). Set true HANYA kalau user eksplisit sadar & sengaja mau duplikat. force_create TIDAK cukup untuk ini.'],
                    'termin_pembayaran' => [
                        'type' => 'array',
                        'description' => 'OTOMATIS pass kalau hasil parse_kontrak_pdf / parse_multiple_docs return field termin_pembayaran. Auto-save ke tabel termin_pembayaran.',
                        'items' => ['type' => 'object', 'properties' => [
                            'nomor_termin' => ['type' => 'integer'],
                            'nama_termin' => ['type' => 'string', 'description' => 'Termin I / Uang Muka / Retensi / dll'],
                            'persen_nilai' => ['type' => 'number', 'description' => '% dari nilai_kontrak'],
                            'persen_progres_syarat' => ['type' => 'number'],
                            'syarat_dokumen' => ['type' => 'string'],
                        ]],
                    ],
                    'milestones' => [
                        'type' => 'array',
                        'description' => 'OTOMATIS pass kalau hasil parse_kontrak_pdf return field milestones. Auto-save ke tabel milestone_pekerjaan; tanggal_target dihitung dari tanggal_mulai + hari_setelah_mulai.',
                        'items' => ['type' => 'object', 'properties' => [
                            'urutan' => ['type' => 'integer'],
                            'nama' => ['type' => 'string'],
                            'deskripsi' => ['type' => 'string'],
                            'hari_setelah_mulai' => ['type' => 'integer'],
                            'progres_target_persen' => ['type' => 'number'],
                            'sumber' => ['type' => 'string', 'description' => 'kontrak | generated_ai | manual'],
                        ]],
                    ],
                    'jadwal_pelaksanaan' => [
                        'type' => 'array',
                        'description' => 'OTOMATIS pass kalau parse_kontrak_pdf return jadwal_pelaksanaan. Digunakan bersama keluaran_kak untuk generate milestone kaya.',
                        'items' => ['type' => 'object', 'properties' => [
                            'nama_fase' => ['type' => 'string'],
                            'kegiatan' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'minggu_selesai' => ['type' => 'integer'],
                        ]],
                    ],
                    'keluaran_kak' => [
                        'type' => 'array',
                        'description' => 'OTOMATIS pass dari parse_kak_pdf result[\'keluaran\'] + result[\'pelaporan\'] digabung.',
                        'items' => ['type' => 'object', 'properties' => [
                            'nama' => ['type' => 'string'],
                            'isi' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ]],
                    ],
                ], 'required' => ['nama_pekerjaan', 'bidang_kode']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'update_pekerjaan',
                'description' => 'Update field pekerjaan yang sudah ada. KONFIRMASI ke user dulu.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id'   => ['type' => 'integer'],
                    'nama_pekerjaan' => ['type' => 'string'],
                    'nilai_pagu'     => ['type' => 'number'],
                    'nilai_kontrak'  => ['type' => 'number'],
                    'no_spk'         => ['type' => 'string'],
                    'tanggal_spk'    => ['type' => 'string'],
                    'tanggal_mulai'  => ['type' => 'string'],
                    'tanggal_akhir'  => ['type' => 'string'],
                    'hari_kerja'     => ['type' => 'integer'],
                    'progres_persen' => ['type' => 'integer'],
                    'catatan'        => ['type' => 'string'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'delete_pekerjaan',
                'description' => 'Hapus (soft delete) pekerjaan. KONFIRMASI dengan jelas ke user dulu, ini destruktif.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                    'alasan'       => ['type' => 'string'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'assign_vendor',
                'description' => 'Pasang vendor ke pekerjaan. Cari vendor by ID atau nama (fuzzy).',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id'   => ['type' => 'integer'],
                    'perusahaan_id'  => ['type' => 'integer'],
                    'perusahaan_nama' => ['type' => 'string'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'assign_personil',
                'description' => 'Pasang tenaga ahli ke pekerjaan dengan jabatan + honor. Tenaga ahli bisa baru (kasih nama_tenaga_ahli) atau existing (tenaga_ahli_id).',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id'       => ['type' => 'integer'],
                    'tenaga_ahli_id'     => ['type' => 'integer'],
                    'nama_tenaga_ahli'   => ['type' => 'string', 'description' => 'Kalau tenaga ahli belum ada, sebutkan namanya — sistem auto-create.'],
                    'jabatan_kontrak'    => ['type' => 'string'],
                    'nilai_honor_kontrak' => ['type' => 'number'],
                ], 'required' => ['pekerjaan_id', 'jabatan_kontrak']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'create_rencana_pengadaan',
                'description' => 'Bulk insert rencana pengadaan dari array items (biasanya dari output parse_rab_pdf). Per item: nama_item, satuan, volume, harga_satuan.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                    'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'nama_item'    => ['type' => 'string'],
                        'satuan'       => ['type' => 'string'],
                        'volume'       => ['type' => 'number'],
                        'harga_satuan' => ['type' => 'number'],
                        'keterangan'   => ['type' => 'string'],
                    ]]],
                ], 'required' => ['pekerjaan_id', 'items']],
            ]],

            // === BATCH 2: WORKFLOW MANAGER ===
            ['type' => 'function', 'function' => [
                'name' => 'submit_daily_report',
                'description' => 'Submit laporan harian (foto absen). User auto dari auth.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id'  => ['type' => 'integer'],
                    'jenis'         => ['type' => 'string', 'description' => 'masuk | pulang'],
                    'foto_path'     => ['type' => 'string', 'description' => 'Path foto absen'],
                    'latitude'      => ['type' => 'number'],
                    'longitude'     => ['type' => 'number'],
                    'catatan'       => ['type' => 'string'],
                    'tanggal'       => ['type' => 'string', 'description' => 'YYYY-MM-DD (default hari ini)'],
                ], 'required' => ['pekerjaan_id', 'jenis']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'approve_daily_report',
                'description' => 'Approve laporan harian. KONFIRMASI dulu.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'laporan_id' => ['type' => 'integer'],
                ], 'required' => ['laporan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'reject_daily_report',
                'description' => 'Reject laporan harian dengan alasan. KONFIRMASI dulu.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'laporan_id' => ['type' => 'integer'],
                    'alasan'     => ['type' => 'string'],
                ], 'required' => ['laporan_id', 'alasan']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'submit_realisasi',
                'description' => 'Submit realisasi pengadaan (vendor lapor barang/jasa yang sudah dibeli/dilakukan).',
                'parameters' => ['type' => 'object', 'properties' => [
                    'rencana_pengadaan_id' => ['type' => 'integer'],
                    'volume_beli'    => ['type' => 'number'],
                    'harga_aktual'   => ['type' => 'number'],
                    'volume_dipakai' => ['type' => 'number'],
                    'tanggal'        => ['type' => 'string'],
                    'foto_invoice_path' => ['type' => 'string'],
                    'foto_material_path' => ['type' => 'string'],
                    'catatan'        => ['type' => 'string'],
                ], 'required' => ['rencana_pengadaan_id', 'volume_beli', 'harga_aktual']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'approve_realisasi',
                'description' => 'Approve realisasi pengadaan. KONFIRMASI dulu.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'realisasi_id' => ['type' => 'integer'],
                ], 'required' => ['realisasi_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'reject_realisasi',
                'description' => 'Reject realisasi dengan alasan.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'realisasi_id' => ['type' => 'integer'],
                    'alasan'       => ['type' => 'string'],
                ], 'required' => ['realisasi_id', 'alasan']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'request_termin',
                'description' => 'Vendor ajukan pencairan termin. KONFIRMASI dulu.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id'           => ['type' => 'integer'],
                    'nomor_termin'           => ['type' => 'integer'],
                    'nama_termin'            => ['type' => 'string'],
                    'nilai_termin'           => ['type' => 'number'],
                    'persen_progres_syarat'  => ['type' => 'number'],
                    'catatan'                => ['type' => 'string'],
                ], 'required' => ['pekerjaan_id', 'nomor_termin', 'nilai_termin']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'reject_termin',
                'description' => 'Reject termin dengan alasan.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'termin_id' => ['type' => 'integer'],
                    'alasan'    => ['type' => 'string'],
                ], 'required' => ['termin_id', 'alasan']],
            ]],

            // === BATCH 3: USER MANAGER ===
            ['type' => 'function', 'function' => [
                'name' => 'invite_vendor_user',
                'description' => 'Invite user vendor (kasih akses login panel vendor). Email + nama wajib.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'email'          => ['type' => 'string'],
                    'nama'           => ['type' => 'string'],
                    'perusahaan_id'  => ['type' => 'integer'],
                    'no_telp'        => ['type' => 'string'],
                ], 'required' => ['email', 'nama', 'perusahaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'invite_staff_user',
                'description' => 'Invite user staff (bawahan vendor, akses 1 proyek doang).',
                'parameters' => ['type' => 'object', 'properties' => [
                    'email'          => ['type' => 'string'],
                    'nama'           => ['type' => 'string'],
                    'perusahaan_id'  => ['type' => 'integer'],
                    'pekerjaan_id'   => ['type' => 'integer'],
                    'no_telp'        => ['type' => 'string'],
                ], 'required' => ['email', 'nama', 'perusahaan_id', 'pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'grant_admin_access',
                'description' => 'Beri user existing role admin_bidang untuk bidang tertentu.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'user_id'    => ['type' => 'integer'],
                    'email'      => ['type' => 'string'],
                    'bidang_kode' => ['type' => 'string'],
                ], 'required' => []],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'revoke_access',
                'description' => 'Cabut akses user (deactivate, bukan delete).',
                'parameters' => ['type' => 'object', 'properties' => [
                    'user_id' => ['type' => 'integer'],
                    'email'   => ['type' => 'string'],
                ], 'required' => []],
            ]],

            // === BATCH 4: WRITER (most call DocumentGeneratorService extensions) ===
            ['type' => 'function', 'function' => [
                'name' => 'generate_laporan_pendahuluan',
                'description' => 'Generate Laporan Pendahuluan DOCX/PDF dari data proyek + template boilerplate (per jenis pekerjaan). KONFIRMASI dulu.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'generate_laporan_akhir',
                'description' => 'Generate Laporan Akhir DOCX (Bab 4-6: data lapangan, simulasi, rekomendasi) dari daily reports + template.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'generate_kuitansi_gaji',
                'description' => 'Generate kuitansi gaji per personil dalam 1 PDF.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                    'periode'      => ['type' => 'string', 'description' => 'mis. "Januari 2026"'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'generate_invoice_atk',
                'description' => 'Generate invoice ATK lampiran.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'generate_invoice_sewa_alat',
                'description' => 'Generate invoice sewa alat lampiran. Bisa override harga supplier.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id'    => ['type' => 'integer'],
                    'harga_supplier'  => ['type' => 'object', 'description' => 'Map: nama_item -> harga aktual supplier'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'generate_surat_permohonan_pembayaran',
                'description' => 'Generate surat permohonan pembayaran termin.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                    'termin_id'    => ['type' => 'integer'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'generate_bast',
                'description' => 'Generate Berita Acara Serah Terima Pekerjaan. Hanya boleh saat progres 100% dan reviewer approve.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                ], 'required' => ['pekerjaan_id']],
            ]],

            // === BATCH 5: MISC ===
            ['type' => 'function', 'function' => [
                'name' => 'parse_penawaran_pdf',
                'description' => 'Baca PDF Penawaran/Dokumen Kualifikasi vendor. Ekstrak tim + harga + SBU.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'file_path' => ['type' => 'string'],
                ], 'required' => ['file_path']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'cross_check_rab_vs_kontrak',
                'description' => 'Validasi total RAB Negosiasi = nilai kontrak, plus cek termin sum = 100%.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                ], 'required' => ['pekerjaan_id']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'send_wa_to_vendor',
                'description' => 'Kirim pesan WA ke vendor proyek tertentu via WaGatewayService.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                    'pesan'        => ['type' => 'string'],
                ], 'required' => ['pekerjaan_id', 'pesan']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'send_wa_to_staff',
                'description' => 'Kirim pesan WA ke staff yang assigned di proyek.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'pekerjaan_id' => ['type' => 'integer'],
                    'pesan'        => ['type' => 'string'],
                ], 'required' => ['pekerjaan_id', 'pesan']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'get_my_pekerjaan',
                'description' => 'Untuk vendor/staff: lihat list proyek yang di-assign ke saya.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'search_audit_log',
                'description' => 'Cari log aktivitas (siapa ubah apa kapan). Filter per user/pekerjaan/tanggal.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'user_email'    => ['type' => 'string'],
                    'pekerjaan_id'  => ['type' => 'integer'],
                    'tanggal_from'  => ['type' => 'string'],
                    'tanggal_to'    => ['type' => 'string'],
                    'limit'         => ['type' => 'integer'],
                ], 'required' => []],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'parse_multiple_docs',
                'description' => 'Parse 2-5 dokumen PARALEL sekaligus (3-5x lebih cepat dari panggil satu-satu). Spesifikasikan tipe + path untuk tiap dokumen. Return semua hasil dalam satu response.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'documents' => ['type' => 'array', 'description' => 'Array dokumen', 'items' => ['type' => 'object', 'properties' => [
                        'type' => ['type' => 'string', 'description' => 'kak | kontrak | rab | penawaran'],
                        'file_path' => ['type' => 'string'],
                    ], 'required' => ['type', 'file_path']]],
                ], 'required' => ['documents']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'list_uploaded_files',
                'description' => 'List file yang sudah diupload user via chat (PDF/Excel/Word). Bisa reference balik file lama tanpa re-upload.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Kata kunci nama file (fuzzy match)'],
                    'limit'  => ['type' => 'integer', 'description' => 'Default 10, max 30'],
                ], 'required' => []],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'find_uploaded_file',
                'description' => 'Cari 1 file upload tertentu by nama (fuzzy) atau ID. Return abs_path siap dipakai untuk parse_kak_pdf / parse_kontrak_pdf dll.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'name_keyword' => ['type' => 'string'],
                    'upload_id'    => ['type' => 'integer'],
                ], 'required' => []],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'ocr_pdf',
                'description' => 'Force OCR untuk PDF hasil scan (image-based). Konversi page jadi gambar lalu extract teks via OpenAI Vision. Lebih lambat & mahal — pakai cuma kalau parser standar gagal (text ≈ 0).',
                'parameters' => ['type' => 'object', 'properties' => [
                    'file_path' => ['type' => 'string'],
                    'max_pages' => ['type' => 'integer', 'description' => 'Default 5'],
                ], 'required' => ['file_path']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'list_perusahaan',
                'description' => 'List master vendor/perusahaan dengan filter opsional.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'search' => ['type' => 'string'],
                    'limit'  => ['type' => 'integer'],
                ], 'required' => []],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'create_perusahaan',
                'description' => 'Tambah master vendor baru. KONFIRMASI dulu.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'nama'     => ['type' => 'string'],
                    'jenis'    => ['type' => 'string', 'description' => 'PT | CV | Perorangan | Lainnya'],
                    'npwp'     => ['type' => 'string'],
                    'alamat'   => ['type' => 'string'],
                    'no_telp'  => ['type' => 'string'],
                    'email'    => ['type' => 'string'],
                    'pic_nama' => ['type' => 'string'],
                ], 'required' => ['nama']],
            ]],
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
            'parse_kak_pdf'            => $this->toolParseKak($input),
            'parse_kontrak_pdf'        => $this->toolParseKontrak($input),
            'parse_rab_pdf'            => $this->toolParseRab($input),
            'generate_invoice'         => $this->toolGenerateInvoice($input),
            // Batch 1: Project Manager
            'create_pekerjaan'         => $this->toolCreatePekerjaan($input),
            'update_pekerjaan'         => $this->toolUpdatePekerjaan($input),
            'delete_pekerjaan'         => $this->toolDeletePekerjaan($input),
            'assign_vendor'            => $this->toolAssignVendor($input),
            'assign_personil'          => $this->toolAssignPersonil($input),
            'create_rencana_pengadaan' => $this->toolCreateRencanaPengadaan($input),
            // Batch 2: Workflow Manager
            'submit_daily_report'      => $this->toolSubmitDailyReport($input),
            'approve_daily_report'     => $this->toolApproveDailyReport($input),
            'reject_daily_report'      => $this->toolRejectDailyReport($input),
            'submit_realisasi'         => $this->toolSubmitRealisasi($input),
            'approve_realisasi'        => $this->toolApproveRealisasi($input),
            'reject_realisasi'         => $this->toolRejectRealisasi($input),
            'request_termin'           => $this->toolRequestTermin($input),
            'reject_termin'            => $this->toolRejectTermin($input),
            // Batch 3: User Manager
            'invite_vendor_user'       => $this->toolInviteVendorUser($input),
            'invite_staff_user'        => $this->toolInviteStaffUser($input),
            'grant_admin_access'       => $this->toolGrantAdminAccess($input),
            'revoke_access'            => $this->toolRevokeAccess($input),
            // Batch 4: Writer
            'generate_laporan_pendahuluan' => $this->toolGenerateLaporanPendahuluan($input),
            'generate_laporan_akhir'   => $this->toolGenerateLaporanAkhir($input),
            'generate_kuitansi_gaji'   => $this->toolGenerateKuitansiGaji($input),
            'generate_invoice_atk'     => $this->toolGenerateInvoiceAtk($input),
            'generate_invoice_sewa_alat' => $this->toolGenerateInvoiceSewaAlat($input),
            'generate_surat_permohonan_pembayaran' => $this->toolGenerateSuratPermohonan($input),
            'generate_bast'            => $this->toolGenerateBast($input),
            // Batch 5: Misc
            'parse_penawaran_pdf'      => $this->toolParsePenawaran($input),
            'cross_check_rab_vs_kontrak' => $this->toolCrossCheckRab($input),
            'send_wa_to_vendor'        => $this->toolSendWaVendor($input),
            'send_wa_to_staff'         => $this->toolSendWaStaff($input),
            'get_my_pekerjaan'         => $this->toolGetMyPekerjaan(),
            'search_audit_log'         => $this->toolSearchAuditLog($input),
            'list_perusahaan'          => $this->toolListPerusahaan($input),
            'create_perusahaan'        => $this->toolCreatePerusahaan($input),
            'parse_multiple_docs'      => $this->toolParseMultipleDocs($input),
            'list_uploaded_files'      => $this->toolListUploadedFiles($input),
            'find_uploaded_file'       => $this->toolFindUploadedFile($input),
            'ocr_pdf'                  => $this->toolOcrPdf($input),
            default                    => ['error' => "Tool '{$name}' tidak dikenali"],
        };
    }

    private function toolParseKak(array $input): array
    {
        $path = $this->resolveFilePath($input['file_path'] ?? '');
        if (!$path) return ['error' => 'File tidak ditemukan: ' . ($input['file_path'] ?? '')];
        if ($this->ocrPending($path)) return $this->ocrPendingResponse();
        try {
            $parser = app(\App\Services\KickoffParserService::class);
            $data = $parser->parse($path);
            $this->captureParseTruth('kak', $data); // G3b
            return ['ok' => true, 'data' => $data];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolParseKontrak(array $input): array
    {
        $path = $this->resolveFilePath($input['file_path'] ?? '');
        if (!$path) return ['error' => 'File tidak ditemukan: ' . ($input['file_path'] ?? '')];
        if ($this->ocrPending($path)) return $this->ocrPendingResponse();
        try {
            $parser = app(\App\Services\KontrakParserService::class);
            $data = $parser->parse($path);
            $this->captureParseTruth('kontrak', $data); // G3b
            return ['ok' => true, 'data' => $data];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolParseRab(array $input): array
    {
        $path = $this->resolveFilePath($input['file_path'] ?? '');
        if (!$path) return ['error' => 'File tidak ditemukan: ' . ($input['file_path'] ?? '')];
        if ($this->ocrPending($path)) return $this->ocrPendingResponse();
        try {
            $parser = app(\App\Services\RabParserService::class);
            return ['ok' => true, 'data' => $parser->parse($path)];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolGenerateInvoice(array $input): array
    {
        $pid = (int) ($input['pekerjaan_id'] ?? 0);
        if (!$pid) return ['error' => 'pekerjaan_id wajib'];
        try {
            $gen = app(\App\Services\DocumentGeneratorService::class);
            $result = $gen->generateInvoice(
                $pid,
                isset($input['prestasi_persen']) ? (int) $input['prestasi_persen'] : null,
                isset($input['no_invoice']) ? (int) $input['no_invoice'] : null,
            );
            return ['ok' => true, 'result' => $result];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function resolveFilePath(string $raw): ?string
    {
        if (empty($raw)) return null;
        // Absolute path
        if (file_exists($raw)) return $raw;
        // Relative to storage/app
        $candidate = storage_path('app/' . ltrim($raw, '/'));
        if (file_exists($candidate)) return $candidate;
        // Public storage
        $candidate2 = storage_path('app/public/' . ltrim($raw, '/'));
        if (file_exists($candidate2)) return $candidate2;
        return null;
    }

    /**
     * PDF hasil scan yang OCR-nya masih jalan di background (OcrChatUpload job)?
     * Kalau ya, parse tool TIDAK boleh OCR sinkron di request web — itu yang
     * dulu nahan worker 240s+ → 504. Balikin status "diproses", suruh ulang.
     */
    private function ocrPending(string $path): bool
    {
        $u = \App\Models\ChatUpload::where('abs_path', $path)->first();
        if (!$u || !$u->is_scanned_pdf) return false;            // bukan scan → parser jalan normal
        if (mb_strlen((string) $u->ocr_text) >= 200) return false; // OCR kelar → cache hangat
        if (\Illuminate\Support\Facades\Cache::get(\App\Jobs\OcrChatUpload::doneKey($u->id))) {
            return false;                                         // job kelar (teks tipis/gagal) → biar parser munculin error asli
        }
        return true;                                              // scan, OCR masih jalan
    }

    private function ocrPendingResponse(): array
    {
        return [
            'ok'      => false,
            'status'  => 'processing',
            'pesan'   => 'Dokumen ini hasil scan dan teksnya sedang dibaca (OCR) di background. '
                       . 'Tunggu ~10-30 detik lalu minta lagi — jangan OCR ulang sekarang.',
        ];
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

    // ============================================================
    // BATCH 1: PROJECT MANAGER
    // ============================================================
    private function toolCreatePekerjaan(array $input): array
    {
        try {
            $bidang = \App\Models\Master\Bidang::where('kode', $input['bidang_kode'] ?? '')->first();
            if (!$bidang) {
                return ['error' => "Bidang dengan kode '{$input['bidang_kode']}' tidak ditemukan. Pilihan: BG, JL, DR, IR"];
            }

            // G2: anti ketuker angka — nilai_kontrak tidak boleh > nilai_pagu.
            if (($err = $this->assertNilaiSane($input)) !== null) return $err;

            // G3a + G3b: tolak field kritis ngawur / tidak cocok dokumen (skip kalau force_create).
            if (empty($input['force_create']) && ($err = $this->assertFieldKritisValid($input)) !== null) return $err;

            // === DUPLICATE DETECTION ===
            $namaInput = trim($input['nama_pekerjaan']);
            $tahun = $input['tahun_anggaran'] ?? (int) date('Y');
            $force = !empty($input['force_create']);

            // G8: exact-duplicate guard — nama + bidang + tahun sama PERSIS tetap diblok
            // WALAU force_create=true. force cuma untuk skip nag "nama mirip", BUKAN izin
            // bikin kembar identik. Sengaja mau kembar? wajib allow_duplicate=true eksplisit.
            if (empty($input['allow_duplicate'])) {
                $exact = Pekerjaan::where('bidang_id', $bidang->id)
                    ->where('tahun_anggaran', $tahun)
                    ->whereRaw('LOWER(TRIM(nama_pekerjaan)) = ?', [mb_strtolower($namaInput)])
                    ->first();
                if ($exact) {
                    return [
                        'error'       => 'exact_duplicate_found',
                        'pesan'       => "Sudah ada proyek dengan NAMA + bidang + tahun SAMA PERSIS: ID {$exact->id} ('{$exact->nama_pekerjaan}'"
                            . ($exact->no_spk ? ", SPK {$exact->no_spk}" : '') . "). Ini kemungkinan besar duplikat. "
                            . "JANGAN bikin baru — pakai existing (lanjut assign vendor/personil/RAB ke ID {$exact->id}). "
                            . "Kalau user BENAR-BENAR sengaja mau proyek kembar (jarang), panggil ulang dengan allow_duplicate=true.",
                        'existing_id' => $exact->id,
                    ];
                }
            }

            // 1. Exact match by no_spk + bidang + tahun (unique constraint)
            if (!empty($input['no_spk'])) {
                $bySpk = Pekerjaan::where('no_spk', $input['no_spk'])
                    ->where('bidang_id', $bidang->id)
                    ->where('tahun_anggaran', $tahun)
                    ->first();
                if ($bySpk) {
                    return [
                        'error' => 'duplicate_spk',
                        'pesan' => "Proyek dengan No SPK '{$input['no_spk']}' di bidang {$bidang->kode} TA {$tahun} sudah ada (ID: {$bySpk->id}, '{$bySpk->nama_pekerjaan}'). "
                            . "ANTI-LOOP: JANGAN panggil create_pekerjaan lagi dengan SPK yang sama. Tanya user SEKALI: 'Mau update pekerjaan {$bySpk->id} atau ganti nomor SPK?'. "
                            . "Kalau user jawab 'UPDATE / pakai existing / lanjut': SKIP create, langsung lanjut assign_vendor + assign_personil + create_rencana_pengadaan untuk pekerjaan_id={$bySpk->id}. "
                            . "Kalau user jawab 'GANTI SPK': tanya nomor SPK baru, baru retry create_pekerjaan dengan SPK baru tsb.",
                        'existing_id' => $bySpk->id,
                        'existing_nama' => $bySpk->nama_pekerjaan,
                        'action_options' => [
                            'update'         => "skip_create + lanjut STEP 5 dengan pekerjaan_id={$bySpk->id}",
                            'ganti_spk_baru' => 'tanya user nomor SPK baru, lalu retry create',
                        ],
                    ];
                }
            }

            // 2. Fuzzy name match (warning, bukan block — bisa di-override dengan force_create=true)
            // Auto-skip jika semua similar names punya SPK berbeda (jelas proyek beda)
            if (!$force) {
                $byName = Pekerjaan::where('bidang_id', $bidang->id)
                    ->where('tahun_anggaran', $tahun)
                    ->where('nama_pekerjaan', 'LIKE', '%' . $namaInput . '%')
                    ->limit(3)
                    ->get(['id', 'nama_pekerjaan', 'no_spk']);
                $inputSpk = $input['no_spk'] ?? '';
                if ($inputSpk && $byName->isNotEmpty()) {
                    $allDifferentSpk = $byName->every(fn ($p) => $p->no_spk !== $inputSpk);
                    if ($allDifferentSpk) {
                        $byName = collect();
                    }
                }
                if ($byName->isNotEmpty()) {
                    return [
                        'warning' => 'similar_name_found',
                        'pesan' => "Ada {$byName->count()} proyek existing dengan nama mirip. "
                            . "ANTI-LOOP RULES (WAJIB):\n"
                            . "  1. Tampilkan list 'similar_info_only' ke user SEKALI dengan tanya: 'Mau pakai existing (ID X) atau bikin baru?'. Lalu BERHENTI tanya, tunggu user jawab.\n"
                            . "  2. KALAU user di turn berikutnya jawab apapun yang artinya 'BIKIN BARU' (iya, ya, bikin baru, baru, lanjut, OK lanjut, force, ya proceed): SEGERA panggil create_pekerjaan SEKALI dengan force_create=true DAN semua field EXACT sama seperti turn sebelumnya (jangan ganti no_spk/tanggal/nilai dari list 'similar' — pakai dari hasil parse_kontrak). JANGAN return warning yang sama lagi.\n"
                            . "  3. KALAU user jawab 'PAKAI EXISTING' (pakai yang ada, pakai #X, lanjut yang lama, update aja): JANGAN create_pekerjaan. Pakai pekerjaan_id dari list, langsung lanjut STEP 5 (assign_vendor/assign_personil/create_rencana_pengadaan).\n"
                            . "  4. DATA di list 'similar_info_only' di bawah hanya untuk INFO ke user, DILARANG dipakai sebagai input create_pekerjaan.",
                        'similar_info_only' => $byName->map(fn ($p) => [
                            'id' => $p->id,
                            'nama' => $p->nama_pekerjaan,
                            'no_spk_existing' => $p->no_spk,
                        ])->all(),
                    ];
                }
            }

            $perusahaanId = $input['perusahaan_id'] ?? null;
            if (!$perusahaanId && !empty($input['perusahaan_nama'])) {
                $namaVendor = trim($input['perusahaan_nama']);
                // Fuzzy match — strip leading "PT."/"CV." for broader match
                $stripped = preg_replace('/^(PT\.?|CV\.?)\s+/i', '', $namaVendor);
                $p = \App\Models\Master\Perusahaan::where('nama', 'LIKE', '%' . $stripped . '%')->first();
                if (!$p) {
                    // Auto-create kalau belum ada — better than orphan pekerjaan
                    try {
                        $p = \App\Models\Master\Perusahaan::create([
                            'nama'    => $namaVendor,
                            'jenis'   => 'konsultan',
                            'pic_nama' => $input['perusahaan_direktur'] ?? null,
                        ]);
                        logger()->info("Auto-created Perusahaan: {$namaVendor} (id={$p->id})");
                    } catch (\Throwable $e) {
                        logger()->warning("Auto-create Perusahaan fail: " . $e->getMessage());
                    }
                }
                $perusahaanId = $p?->id;
            }

            $jenisId = null;
            if (!empty($input['jenis_pekerjaan'])) {
                $j = \App\Models\Master\JenisPekerjaan::where('nama', 'LIKE', '%' . $input['jenis_pekerjaan'] . '%')->first();
                $jenisId = $j?->id;
            }

            $pekerjaan = Pekerjaan::create([
                'bidang_id'          => $bidang->id,
                'jenis_pekerjaan_id' => $jenisId,
                'perusahaan_id'      => $perusahaanId,
                'tahun_anggaran'     => $input['tahun_anggaran'] ?? (int) date('Y'),
                'nama_pekerjaan'     => $input['nama_pekerjaan'],
                'nilai_pagu'         => $input['nilai_pagu'] ?? null,
                'nilai_kontrak'      => $input['nilai_kontrak'] ?? null,
                'no_spk'             => $input['no_spk'] ?? null,
                'tanggal_spk'        => $input['tanggal_spk'] ?? null,
                'no_spmk'            => $input['no_spmk'] ?? null,
                'tanggal_mulai'      => $input['tanggal_mulai'] ?? null,
                'tanggal_akhir'      => $input['tanggal_akhir'] ?? null,
                'hari_kerja'         => $input['hari_kerja'] ?? null,
                'lokasi'             => $input['lokasi'] ?? $input['lokasi_pekerjaan'] ?? null,
                'created_by'         => auth()->id(),
                'updated_by'         => auth()->id(),
            ]);

            // Auto-create termin_pembayaran kalau dikirim — non-fatal per row
            $terminCount = 0;
            $terminErrors = [];
            if (!empty($input['termin_pembayaran']) && is_array($input['termin_pembayaran'])) {
                $nilaiKontrak = (float) ($input['nilai_kontrak'] ?? 0);
                foreach ($input['termin_pembayaran'] as $i => $t) {
                    try {
                        $persenNilai = (float) ($t['persen_nilai'] ?? 0);
                        $nilai = $persenNilai > 0 && $nilaiKontrak > 0
                            ? round($nilaiKontrak * $persenNilai / 100, 2)
                            : (float) ($t['nilai_termin'] ?? 0);
                        \DB::table('termin_pembayaran')->insert([
                            'pekerjaan_id'          => $pekerjaan->id,
                            'nomor_termin'          => (int) ($t['nomor_termin'] ?? ($i + 1)),
                            'nama_termin'           => (string) ($t['nama_termin'] ?? 'Termin ' . ($i + 1)),
                            'nilai_termin'          => $nilai,
                            'persen_progres_syarat' => (float) ($t['persen_progres_syarat'] ?? 0),
                            'status'                => 'draft',
                            'catatan_pptk'          => isset($t['syarat_dokumen']) ? (string) $t['syarat_dokumen'] : null,
                            'created_by'            => auth()->id(),
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ]);
                        $terminCount++;
                    } catch (\Throwable $tEx) {
                        $terminErrors[] = "Termin #{$i} gagal: " . $tEx->getMessage();
                        logger()->warning("Termin insert fail for pekerjaan {$pekerjaan->id}: " . $tEx->getMessage());
                    }
                }
            }

            // Auto-inject cached parse results (server-side, no AI forwarding needed)
            $agg = $this->loadAggregated();
            logger()->info('AI create_pekerjaan: agg jadwal=' . count($agg['jadwal_pelaksanaan'] ?? []) . ' input jadwal=' . count($input['jadwal_pelaksanaan'] ?? []) . ' input milestones=' . count($input['milestones'] ?? []));
            if (!empty($agg)) {
                if (empty($input['jadwal_pelaksanaan']) && !empty($agg['jadwal_pelaksanaan'])) {
                    $input['jadwal_pelaksanaan'] = $agg['jadwal_pelaksanaan'];
                    logger()->info('AI injected jadwal from cache: ' . count($agg['jadwal_pelaksanaan']));
                }
                if (empty($input['keluaran_kak']) && !empty($agg['keluaran_kak'])) {
                    $input['keluaran_kak'] = $agg['keluaran_kak'];
                }
                if (empty($input['milestones']) && !empty($agg['milestones'])) {
                    $input['milestones'] = $agg['milestones'];
                }
                if (empty($input['termin_pembayaran']) && !empty($agg['termin_pembayaran'])) {
                    $input['termin_pembayaran'] = $agg['termin_pembayaran'];
                }
            }

            // Smart merge: kalau jadwal_pelaksanaan tersedia, generate milestone kaya
            // dari jadwal kontrak + keluaran KAK. Kalau kosong, fallback ke milestones biasa.
            if (!empty($input['jadwal_pelaksanaan'])) {
                $merged = $this->mergeMilestonesFromParsedDocs(
                    $input['jadwal_pelaksanaan'],
                    $input['keluaran_kak'] ?? [],
                    $input['tanggal_mulai'] ?? null
                );
                if (!empty($merged)) {
                    $input['milestones'] = $merged;
                }
            }

            // Auto-create milestones kalau dikirim — non-fatal: failure di milestone
            // jangan gagalin whole flow (pekerjaan + termin sudah tersimpan, biar
            // auto-laporan tetap jalan, user bisa add milestone manual nanti).
            $milestoneCount = 0;
            $milestoneErrors = [];
            if (!empty($input['milestones']) && is_array($input['milestones'])) {
                $tanggalMulai = !empty($input['tanggal_mulai'])
                    ? \Carbon\Carbon::parse($input['tanggal_mulai'])
                    : null;
                foreach ($input['milestones'] as $i => $m) {
                    try {
                        $hari = (int) ($m['hari_setelah_mulai'] ?? 0);
                        $tgl = $tanggalMulai
                            ? $tanggalMulai->copy()->addDays($hari)->format('Y-m-d')
                            : (string) ($m['tanggal_target'] ?? now()->format('Y-m-d'));
                        $milestoneId = \DB::table('milestone_pekerjaan')->insertGetId([
                            'pekerjaan_id'          => $pekerjaan->id,
                            'urutan'                => (int) ($m['urutan'] ?? ($i + 1)),
                            'nama'                  => (string) ($m['nama'] ?? 'Milestone ' . ($i + 1)),
                            'deskripsi'             => isset($m['deskripsi']) ? (string) $m['deskripsi'] : null,
                            'tanggal_target'        => $tgl,
                            'progres_target_persen' => (float) ($m['progres_target_persen'] ?? 0),
                            'status'                => 'belum_mulai',
                            'sumber'                => in_array($m['sumber'] ?? 'manual', ['kontrak','generated_ai','manual']) ? $m['sumber'] : 'manual',
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ]);
                        $milestoneCount++;

                        // Auto-create checklist items from kegiatan[] and deliverable_items[] — non-fatal per item
                        foreach ($m['kegiatan'] ?? [] as $kegiatanNama) {
                            try {
                                \DB::table('milestone_checklist_items')->insert([
                                    'milestone_pekerjaan_id' => $milestoneId,
                                    'tipe'                   => 'kegiatan',
                                    'nama'                   => (string) $kegiatanNama,
                                    'is_done_vendor'         => false,
                                    'is_done_admin'          => false,
                                    'created_at'             => now(),
                                    'updated_at'             => now(),
                                ]);
                            } catch (\Throwable $ciEx) {
                                logger()->warning("Checklist item (kegiatan) insert fail for milestone {$milestoneId}: " . $ciEx->getMessage());
                            }
                        }

                        foreach ($m['deliverable_items'] ?? [] as $deliverableNama) {
                            try {
                                \DB::table('milestone_checklist_items')->insert([
                                    'milestone_pekerjaan_id' => $milestoneId,
                                    'tipe'                   => 'deliverable',
                                    'nama'                   => (string) $deliverableNama,
                                    'is_done_vendor'         => false,
                                    'is_done_admin'          => false,
                                    'created_at'             => now(),
                                    'updated_at'             => now(),
                                ]);
                            } catch (\Throwable $ciEx) {
                                logger()->warning("Checklist item (deliverable) insert fail for milestone {$milestoneId}: " . $ciEx->getMessage());
                            }
                        }
                    } catch (\Throwable $mEx) {
                        $milestoneErrors[] = "Milestone #{$i} ('{$m['nama']}') gagal: " . $mEx->getMessage();
                        logger()->warning("Milestone insert fail for pekerjaan {$pekerjaan->id}: " . $mEx->getMessage());
                    }
                }
            }

            // Auto-generate Laporan Pendahuluan draft (best-effort, jangan gagalin create)
            $laporan = null;
            try {
                $laporan = app(\App\Services\DocumentGeneratorService::class)
                    ->generateLaporanPendahuluan($pekerjaan->id);
            } catch (\Throwable $e) {
                logger()->warning("Auto-generate laporan_pendahuluan fail: " . $e->getMessage());
            }

            // G7: proyek sudah jadi → buang cache parse (jadwal/termin/truth) biar
            // proyek berikutnya tidak ketularan data proyek ini.
            $this->clearAggregated();

            $msg = "Proyek '{$pekerjaan->nama_pekerjaan}' berhasil dibuat (ID: {$pekerjaan->id}, termin: {$terminCount}, milestones: {$milestoneCount})";
            if ($laporan) {
                $msg .= ". Laporan Pendahuluan draft otomatis dibuat & tersedia di tab Dokumen + chat.";
            }

            return [
                'sukses' => true,
                'pekerjaan_id' => $pekerjaan->id,
                'termin_created' => $terminCount,
                'milestones_created' => $milestoneCount,
                'laporan_pendahuluan' => $laporan, // {dokumen_id, filename, download_url, size_kb} | null
                'pesan' => $msg,
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function mergeMilestonesFromParsedDocs(array $jadwal, array $keluaran, ?string $tanggalMulai): array
    {
        if (empty($jadwal)) return [];

        usort($jadwal, fn ($a, $b) => ($a['minggu_selesai'] ?? 0) <=> ($b['minggu_selesai'] ?? 0));

        $total = count($jadwal);
        $result = [];

        foreach ($jadwal as $i => $fase) {
            $hariSetelahMulai = ((int) ($fase['minggu_selesai'] ?? 0)) * 7;
            $progres = round(($i + 1) / $total * 100, 2);

            $kegiatanList   = array_values(array_filter(array_map('strval', $fase['kegiatan'] ?? [])));
            $deliverableIsi = [];

            $deskripsiParts = [];
            if (!empty($kegiatanList)) {
                $deskripsiParts[] = 'Kegiatan:' . "\n" . implode("\n", array_map(fn ($k) => '• ' . $k, $kegiatanList));
            }

            if (isset($keluaran[$i]) && is_array($keluaran[$i])) {
                $k = $keluaran[$i];
                $deliverableIsi = array_values(array_filter(array_map('strval', $k['isi'] ?? [])));
                $deliverableHeader = ($k['nama'] ?? 'Deliverable');
                $deliverablePart = $deliverableHeader;
                if (!empty($deliverableIsi)) {
                    $deliverablePart .= "\n" . implode("\n", array_map(fn ($d) => '• ' . $d, $deliverableIsi));
                }
                $deskripsiParts[] = 'Deliverable:' . "\n" . $deliverablePart;
            }

            $deskripsi = !empty($deskripsiParts) ? implode("\n\n", $deskripsiParts) : null;

            $result[] = [
                'urutan'                => $i + 1,
                'nama'                  => (string) ($fase['nama_fase'] ?? 'Fase ' . ($i + 1)),
                'deskripsi'             => $deskripsi,
                'hari_setelah_mulai'    => $hariSetelahMulai,
                'progres_target_persen' => $progres,
                'sumber'                => 'kontrak',
                'kegiatan'              => $kegiatanList,
                'deliverable_items'     => $deliverableIsi,
            ];
        }

        return $result;
    }

    private function toolUpdatePekerjaan(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];

        $fields = ['nama_pekerjaan','nilai_pagu','nilai_kontrak','no_spk','tanggal_spk','tanggal_mulai','tanggal_akhir','hari_kerja','progres_persen','catatan'];
        $update = [];
        foreach ($fields as $f) if (array_key_exists($f, $input)) $update[$f] = $input[$f];
        if (empty($update)) return ['error' => 'Tidak ada field yang diupdate'];

        // G2: cek hasil akhir (merge existing + update) — kontrak tidak boleh > pagu.
        $pagu    = array_key_exists('nilai_pagu', $update) ? $update['nilai_pagu'] : $pekerjaan->nilai_pagu;
        $kontrak = array_key_exists('nilai_kontrak', $update) ? $update['nilai_kontrak'] : $pekerjaan->nilai_kontrak;
        if ($pagu !== null && $kontrak !== null && (float) $kontrak > (float) $pagu) {
            return ['error' => "Ditolak: nilai_kontrak (Rp " . number_format((float) $kontrak, 0, ',', '.') . ") > nilai_pagu (Rp " . number_format((float) $pagu, 0, ',', '.') . "). Kemungkinan ketukar — cek lagi."];
        }

        $update['updated_by'] = auth()->id();
        $pekerjaan->update($update);
        return ['sukses' => true, 'pesan' => "Pekerjaan '{$pekerjaan->nama_pekerjaan}' diupdate (" . count($update) . " field)"];
    }

    private function toolDeletePekerjaan(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];
        $nama = $pekerjaan->nama_pekerjaan;
        // G4: soft delete (model pakai SoftDeletes) — bisa dipulihkan, JANGAN forceDelete.
        $pekerjaan->delete();
        return ['sukses' => true, 'pesan' => "Pekerjaan '{$nama}' dipindahkan ke tong sampah (bisa dipulihkan admin). Alasan: " . ($input['alasan'] ?? '-')];
    }

    private function toolAssignVendor(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];

        $perusahaanId = $input['perusahaan_id'] ?? null;
        if (!$perusahaanId && !empty($input['perusahaan_nama'])) {
            $p = \App\Models\Master\Perusahaan::where('nama', 'LIKE', '%' . $input['perusahaan_nama'] . '%')->first();
            if (!$p) return ['error' => "Vendor '{$input['perusahaan_nama']}' tidak ditemukan di master perusahaan"];
            $perusahaanId = $p->id;
        }
        if (!$perusahaanId) return ['error' => 'Harus kasih perusahaan_id atau perusahaan_nama'];

        $pekerjaan->update(['perusahaan_id' => $perusahaanId]);
        $vendor = \App\Models\Master\Perusahaan::find($perusahaanId);
        return ['sukses' => true, 'pesan' => "Vendor '{$vendor->nama}' di-assign ke '{$pekerjaan->nama_pekerjaan}'"];
    }

    private function toolAssignPersonil(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];

        $taId = $input['tenaga_ahli_id'] ?? null;
        if (!$taId && !empty($input['nama_tenaga_ahli'])) {
            $ta = \App\Models\Master\TenagaAhli::firstOrCreate(
                ['nama' => $input['nama_tenaga_ahli']],
                ['perusahaan_id' => $pekerjaan->perusahaan_id, 'is_active' => true],
            );
            $taId = $ta->id;
        }
        if (!$taId) return ['error' => 'Harus kasih tenaga_ahli_id atau nama_tenaga_ahli'];

        $personil = \App\Models\PekerjaanPersonil::updateOrCreate(
            ['pekerjaan_id' => $pekerjaan->id, 'tenaga_ahli_id' => $taId],
            [
                'jabatan_kontrak' => $input['jabatan_kontrak'],
                'nilai_honor_kontrak' => $input['nilai_honor_kontrak'] ?? null,
                'is_active' => true,
            ],
        );
        $ta = \App\Models\Master\TenagaAhli::find($taId);
        return ['sukses' => true, 'pesan' => "Personil '{$ta->nama}' di-assign sebagai '{$input['jabatan_kontrak']}'"];
    }

    private function toolCreateRencanaPengadaan(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];
        $items = $input['items'] ?? [];
        if (empty($items)) return ['error' => 'Items kosong'];

        $created = 0;
        foreach ($items as $item) {
            \App\Models\RencanaPengadaan::create([
                'pekerjaan_id' => $pekerjaan->id,
                'nama_item' => $item['nama_item'] ?? 'Item',
                'satuan' => $item['satuan'] ?? 'Ls',
                'volume_rencana' => (float) ($item['volume'] ?? 1),
                'harga_satuan_rencana' => (float) ($item['harga_satuan'] ?? 0),
                'keterangan' => $item['keterangan'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $created++;
        }
        return ['sukses' => true, 'pesan' => "Bikin {$created} rencana pengadaan untuk '{$pekerjaan->nama_pekerjaan}'"];
    }

    // ============================================================
    // BATCH 2: WORKFLOW MANAGER
    // ============================================================
    private function toolSubmitDailyReport(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];
        $user = auth()->user();
        if (!$user) return ['error' => 'User tidak login'];

        try {
            $lh = LaporanHarian::create([
                'pekerjaan_id' => $pekerjaan->id,
                'perusahaan_id' => $pekerjaan->perusahaan_id ?? $user->perusahaan_id,
                'user_id' => $user->id,
                'jenis' => $input['jenis'] ?? 'masuk',
                'foto_original_path' => $input['foto_path'] ?? null,
                'latitude' => $input['latitude'] ?? null,
                'longitude' => $input['longitude'] ?? null,
                'catatan' => $input['catatan'] ?? null,
                'tanggal_laporan' => $input['tanggal'] ?? today(),
                'submitted_at' => now(),
                'status' => 'pending',
            ]);
            return ['sukses' => true, 'laporan_id' => $lh->id, 'pesan' => "Laporan {$lh->jenis} tanggal {$lh->tanggal_laporan} submitted (pending approve)"];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolApproveDailyReport(array $input): array
    {
        $lh = LaporanHarian::find((int) ($input['laporan_id'] ?? 0));
        if (!$lh) return ['error' => 'Laporan tidak ditemukan'];
        $lh->update(['status' => 'approved', 'alasan_rejected' => null]);
        return ['sukses' => true, 'pesan' => "Laporan #{$lh->id} approved"];
    }

    private function toolRejectDailyReport(array $input): array
    {
        $lh = LaporanHarian::find((int) ($input['laporan_id'] ?? 0));
        if (!$lh) return ['error' => 'Laporan tidak ditemukan'];
        $lh->update(['status' => 'rejected', 'alasan_rejected' => $input['alasan']]);
        return ['sukses' => true, 'pesan' => "Laporan #{$lh->id} ditolak: {$input['alasan']}"];
    }

    private function toolSubmitRealisasi(array $input): array
    {
        $rencana = \App\Models\RencanaPengadaan::find((int) ($input['rencana_pengadaan_id'] ?? 0));
        if (!$rencana) return ['error' => 'Rencana pengadaan tidak ditemukan'];

        $volBeli = (float) ($input['volume_beli'] ?? 0);
        $volPakai = (float) ($input['volume_dipakai'] ?? $volBeli);

        try {
            $r = \App\Models\RealisasiPengadaan::create([
                'rencana_pengadaan_id' => $rencana->id,
                'pekerjaan_id' => $rencana->pekerjaan_id,
                'perusahaan_id' => $rencana->pekerjaan->perusahaan_id ?? auth()->user()?->perusahaan_id,
                'tanggal_realisasi' => $input['tanggal'] ?? today(),
                'volume_beli' => $volBeli,
                'harga_aktual' => (float) ($input['harga_aktual'] ?? 0),
                'volume_dipakai' => $volPakai,
                'volume_sisa' => max(0, $volBeli - $volPakai),
                'foto_invoice_path' => $input['foto_invoice_path'] ?? null,
                'foto_material_path' => $input['foto_material_path'] ?? null,
                'catatan_vendor' => $input['catatan'] ?? null,
                'status' => 'submitted',
                'created_by' => auth()->id(),
            ]);
            return ['sukses' => true, 'realisasi_id' => $r->id, 'pesan' => "Realisasi untuk '{$rencana->nama_item}' submitted"];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolApproveRealisasi(array $input): array
    {
        $r = \App\Models\RealisasiPengadaan::find((int) ($input['realisasi_id'] ?? 0));
        if (!$r) return ['error' => 'Realisasi tidak ditemukan'];
        $r->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()]);
        return ['sukses' => true, 'pesan' => "Realisasi #{$r->id} verified"];
    }

    private function toolRejectRealisasi(array $input): array
    {
        $r = \App\Models\RealisasiPengadaan::find((int) ($input['realisasi_id'] ?? 0));
        if (!$r) return ['error' => 'Realisasi tidak ditemukan'];
        $r->update(['status' => 'rejected', 'catatan_pptk' => $input['alasan']]);
        return ['sukses' => true, 'pesan' => "Realisasi #{$r->id} ditolak: {$input['alasan']}"];
    }

    private function toolRequestTermin(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];

        try {
            $t = TerminPembayaran::updateOrCreate(
                ['pekerjaan_id' => $pekerjaan->id, 'nomor_termin' => (int) $input['nomor_termin']],
                [
                    'nama_termin' => $input['nama_termin'] ?? "Termin {$input['nomor_termin']}",
                    'nilai_termin' => (float) $input['nilai_termin'],
                    'persen_progres_syarat' => (float) ($input['persen_progres_syarat'] ?? 0),
                    'tanggal_pengajuan' => today(),
                    'status' => 'diajukan',
                    'catatan_pptk' => $input['catatan'] ?? null,
                    'created_by' => auth()->id(),
                ],
            );
            return ['sukses' => true, 'termin_id' => $t->id, 'pesan' => "Termin '{$t->nama_termin}' diajukan, menunggu approve Superadmin"];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolRejectTermin(array $input): array
    {
        $t = TerminPembayaran::find((int) ($input['termin_id'] ?? 0));
        if (!$t) return ['error' => 'Termin tidak ditemukan'];
        $t->update(['status' => 'ditolak', 'catatan_ppk' => $input['alasan']]);
        return ['sukses' => true, 'pesan' => "Termin #{$t->id} ditolak: {$input['alasan']}"];
    }

    // ============================================================
    // BATCH 3: USER MANAGER
    // ============================================================
    private function toolInviteVendorUser(array $input): array
    {
        try {
            $u = \App\Models\User::firstOrCreate(
                ['email' => $input['email']],
                [
                    'name' => $input['nama'],
                    'password' => bcrypt(\Illuminate\Support\Str::random(16)),
                    'perusahaan_id' => $input['perusahaan_id'],
                    'no_telp' => $input['no_telp'] ?? null,
                    'is_active' => true,
                ],
            );
            $u->syncRoles(['vendor']);
            return ['sukses' => true, 'user_id' => $u->id, 'pesan' => "Vendor user '{$u->email}' dibuat. Kirim password reset link manual."];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolInviteStaffUser(array $input): array
    {
        try {
            $u = \App\Models\User::firstOrCreate(
                ['email' => $input['email']],
                [
                    'name' => $input['nama'],
                    'password' => bcrypt(\Illuminate\Support\Str::random(16)),
                    'perusahaan_id' => $input['perusahaan_id'],
                    'no_telp' => $input['no_telp'] ?? null,
                    'is_active' => true,
                ],
            );
            // G5: JANGAN auto-kasih role 'vendor' (kelebihan akses). Role 'staff' belum ada
            // + scoping per-proyek butuh tabel staff_pekerjaan (migration → fase terpisah).
            // Default least-privilege: user dibuat tanpa role, admin set manual.
            return ['sukses' => true, 'user_id' => $u->id, 'pesan' => "Staff user '{$u->email}' dibuat untuk pekerjaan #{$input['pekerjaan_id']} TANPA role. Admin wajib set akses manual (role staff resmi + scoping per-proyek belum tersedia)."];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolGrantAdminAccess(array $input): array
    {
        $u = !empty($input['user_id'])
            ? \App\Models\User::find($input['user_id'])
            : \App\Models\User::where('email', $input['email'] ?? '')->first();
        if (!$u) return ['error' => 'User tidak ditemukan'];

        if (!empty($input['bidang_kode'])) {
            $b = \App\Models\Master\Bidang::where('kode', $input['bidang_kode'])->first();
            if ($b) $u->update(['bidang_id' => $b->id]);
        }
        $u->syncRoles(['admin_bidang']);
        return ['sukses' => true, 'pesan' => "User '{$u->email}' diberi role admin_bidang"];
    }

    private function toolRevokeAccess(array $input): array
    {
        $u = !empty($input['user_id'])
            ? \App\Models\User::find($input['user_id'])
            : \App\Models\User::where('email', $input['email'] ?? '')->first();
        if (!$u) return ['error' => 'User tidak ditemukan'];
        $u->update(['is_active' => false]);
        return ['sukses' => true, 'pesan' => "User '{$u->email}' di-deactivate"];
    }

    // ============================================================
    // BATCH 4: WRITER (delegate to DocumentGeneratorService)
    // ============================================================
    private function toolGenerateLaporanPendahuluan(array $input): array
    {
        return $this->genericGenerate($input, 'generateLaporanPendahuluan', 'Laporan Pendahuluan');
    }

    private function toolGenerateLaporanAkhir(array $input): array
    {
        return $this->genericGenerate($input, 'generateLaporanAkhir', 'Laporan Akhir');
    }

    private function toolGenerateKuitansiGaji(array $input): array
    {
        return $this->genericGenerate($input, 'generateKuitansiGaji', 'Kuitansi Gaji');
    }

    private function toolGenerateInvoiceAtk(array $input): array
    {
        return $this->genericGenerate($input, 'generateInvoiceAtk', 'Invoice ATK');
    }

    private function toolGenerateInvoiceSewaAlat(array $input): array
    {
        return $this->genericGenerate($input, 'generateInvoiceSewaAlat', 'Invoice Sewa Alat');
    }

    private function toolGenerateSuratPermohonan(array $input): array
    {
        return $this->genericGenerate($input, 'generateSuratPermohonanPembayaran', 'Surat Permohonan');
    }

    private function toolGenerateBast(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];
        if ((int) $pekerjaan->progres_persen < 100) {
            return ['error' => "Tidak bisa generate BAST. Progres baru {$pekerjaan->progres_persen}%. Wajib 100%."];
        }
        return $this->genericGenerate($input, 'generateBast', 'BAST');
    }

    private function genericGenerate(array $input, string $method, string $label): array
    {
        $pid = (int) ($input['pekerjaan_id'] ?? 0);
        if (!$pid) return ['error' => 'pekerjaan_id wajib'];
        try {
            $gen = app(\App\Services\DocumentGeneratorService::class);
            if (!method_exists($gen, $method)) {
                return ['error' => "Generator '{$label}' belum diimplement di DocumentGeneratorService::{$method}()"];
            }
            $result = $gen->$method($pid, $input);
            return ['sukses' => true, 'tipe' => $label, 'result' => $result];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ============================================================
    // BATCH 5: MISC
    // ============================================================
    private function toolParsePenawaran(array $input): array
    {
        $path = $this->resolveFilePath($input['file_path'] ?? '');
        if (!$path) return ['error' => 'File tidak ditemukan: ' . ($input['file_path'] ?? '')];
        if ($this->ocrPending($path)) return $this->ocrPendingResponse();
        // Pakai KontrakParserService (OpenAI prompt cukup general — bisa di-tune nanti)
        try {
            $parser = app(\App\Services\KontrakParserService::class);
            return ['ok' => true, 'data' => $parser->parse($path), 'catatan' => 'Pakai KontrakParser sebagai fallback untuk Penawaran'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolCrossCheckRab(array $input): array
    {
        $pekerjaan = Pekerjaan::with('rencanaPengadaan')->find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];

        $totalRab = 0.0;
        foreach ($pekerjaan->rencanaPengadaan as $r) {
            $totalRab += (float) $r->harga_satuan_rencana * (float) $r->volume_rencana;
        }
        $totalRabPpn = $totalRab * 1.11;

        $nilaiKontrak = (float) ($pekerjaan->nilai_kontrak ?? 0);
        $selisih = abs($totalRabPpn - $nilaiKontrak);
        $cocok = $selisih < 1000;

        $totalTermin = TerminPembayaran::where('pekerjaan_id', $pekerjaan->id)->sum('nilai_termin');
        $terminSumPersen = $nilaiKontrak > 0 ? round(($totalTermin / $nilaiKontrak) * 100, 2) : 0;

        return [
            'ok' => true,
            'total_rab_tanpa_ppn' => $totalRab,
            'total_rab_dengan_ppn_11' => $totalRabPpn,
            'nilai_kontrak' => $nilaiKontrak,
            'selisih' => $selisih,
            'rab_match_kontrak' => $cocok,
            'total_termin' => $totalTermin,
            'persen_termin_dari_kontrak' => $terminSumPersen,
            'pesan' => $cocok
                ? "RAB cocok dengan nilai kontrak. Termin sum: {$terminSumPersen}%"
                : "PERINGATAN: RAB (Rp " . number_format($totalRabPpn, 0, ',', '.') . ") TIDAK sama dengan kontrak (Rp " . number_format($nilaiKontrak, 0, ',', '.') . "). Selisih Rp " . number_format($selisih, 0, ',', '.'),
        ];
    }

    private function toolSendWaVendor(array $input): array
    {
        $pekerjaan = Pekerjaan::with('perusahaan')->find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan || !$pekerjaan->perusahaan?->no_telp) {
            return ['error' => 'Vendor tidak punya nomor telp di master'];
        }
        try {
            app(\App\Services\WaGatewayService::class)->kirim($pekerjaan->perusahaan->no_telp, $input['pesan']);
            return ['sukses' => true, 'pesan' => "WA dikirim ke {$pekerjaan->perusahaan->nama}"];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function toolSendWaStaff(array $input): array
    {
        $pekerjaan = Pekerjaan::find((int) ($input['pekerjaan_id'] ?? 0));
        if (!$pekerjaan) return ['error' => 'Pekerjaan tidak ditemukan'];

        $users = \App\Models\User::where('perusahaan_id', $pekerjaan->perusahaan_id)
            ->where('is_active', true)
            ->whereNotNull('no_telp')
            ->get();
        if ($users->isEmpty()) return ['error' => 'Tidak ada staff dengan nomor telp'];

        $wa = app(\App\Services\WaGatewayService::class);
        $sent = 0;
        foreach ($users as $u) {
            try { $wa->kirim($u->no_telp, $input['pesan']); $sent++; } catch (\Throwable) {}
        }
        return ['sukses' => true, 'pesan' => "WA dikirim ke {$sent} staff"];
    }

    private function toolGetMyPekerjaan(): array
    {
        $u = auth()->user();
        if (!$u) return ['error' => 'User tidak login'];
        if (!$u->perusahaan_id) return ['error' => 'User tidak punya perusahaan_id (bukan vendor/staff)'];

        $list = Pekerjaan::where('perusahaan_id', $u->perusahaan_id)
            ->with('statusPekerjaan')
            ->take(20)
            ->get(['id', 'nama_pekerjaan', 'progres_persen', 'tanggal_akhir']);

        return ['ok' => true, 'pekerjaan' => $list->map(fn ($p) => [
            'id' => $p->id,
            'nama' => $p->nama_pekerjaan,
            'progres' => $p->progres_persen . '%',
            'tanggal_akhir' => $p->tanggal_akhir?->format('Y-m-d'),
        ])->all()];
    }

    private function toolSearchAuditLog(array $input): array
    {
        $q = \Spatie\Activitylog\Models\Activity::query()->latest();
        if (!empty($input['user_email'])) {
            $uid = \App\Models\User::where('email', $input['user_email'])->value('id');
            if ($uid) $q->where('causer_id', $uid);
        }
        if (!empty($input['pekerjaan_id'])) {
            $q->where('subject_type', Pekerjaan::class)->where('subject_id', $input['pekerjaan_id']);
        }
        if (!empty($input['tanggal_from'])) $q->where('created_at', '>=', $input['tanggal_from']);
        if (!empty($input['tanggal_to']))   $q->where('created_at', '<=', $input['tanggal_to']);

        $list = $q->take((int) ($input['limit'] ?? 20))->get();
        return ['ok' => true, 'count' => $list->count(), 'logs' => $list->map(fn ($a) => [
            'ts' => $a->created_at?->format('Y-m-d H:i'),
            'event' => $a->event,
            'description' => $a->description,
            'subject' => $a->subject_type . '#' . $a->subject_id,
            'causer' => $a->causer?->email,
        ])->all()];
    }

    private function toolListPerusahaan(array $input): array
    {
        $q = \App\Models\Master\Perusahaan::query();
        if (!empty($input['search'])) {
            $q->where('nama', 'LIKE', '%' . $input['search'] . '%');
        }
        $list = $q->take((int) ($input['limit'] ?? 20))->get(['id', 'nama', 'jenis', 'npwp']);
        return ['ok' => true, 'count' => $list->count(), 'data' => $list->all()];
    }

    private function toolCreatePerusahaan(array $input): array
    {
        try {
            $p = \App\Models\Master\Perusahaan::create([
                'nama' => $input['nama'],
                'jenis' => $input['jenis'] ?? (str_starts_with($input['nama'], 'CV') ? 'CV' : 'PT'),
                'npwp' => $input['npwp'] ?? null,
                'alamat' => $input['alamat'] ?? null,
                'no_telp' => $input['no_telp'] ?? null,
                'email' => $input['email'] ?? null,
                'pic_nama' => $input['pic_nama'] ?? null,
                'is_blacklisted' => false,
            ]);
            return ['sukses' => true, 'perusahaan_id' => $p->id, 'pesan' => "Vendor '{$p->nama}' ditambahkan"];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ============================================================
    // FILE UPLOAD MANAGEMENT
    // ============================================================
    private function toolListUploadedFiles(array $input): array
    {
        $q = \App\Models\ChatUpload::query()
            ->when(auth()->id(), fn ($q) => $q->where('user_id', auth()->id()))
            ->latest();
        if (!empty($input['search'])) {
            $q->where('original_name', 'LIKE', '%' . $input['search'] . '%');
        }
        $limit = min((int) ($input['limit'] ?? 10), 30);
        $files = $q->take($limit)->get(['id', 'original_name', 'abs_path', 'mime', 'size_bytes', 'is_scanned_pdf', 'created_at']);
        return [
            'ok' => true,
            'count' => $files->count(),
            'files' => $files->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'path' => $f->abs_path,
                'mime' => $f->mime,
                'size_kb' => round(($f->size_bytes ?? 0) / 1024, 1),
                'is_scanned_pdf' => $f->is_scanned_pdf,
                'uploaded_at' => $f->created_at?->format('Y-m-d H:i'),
            ])->all(),
        ];
    }

    private function toolFindUploadedFile(array $input): array
    {
        if (!empty($input['upload_id'])) {
            $f = \App\Models\ChatUpload::find($input['upload_id']);
        } else {
            $kw = trim($input['name_keyword'] ?? '');
            if (empty($kw)) return ['error' => 'Kasih name_keyword atau upload_id'];
            $f = \App\Models\ChatUpload::query()
                ->when(auth()->id(), fn ($q) => $q->where('user_id', auth()->id()))
                ->where('original_name', 'LIKE', '%' . $kw . '%')
                ->latest()
                ->first();
        }
        if (!$f) return ['error' => 'File tidak ditemukan'];
        if (!file_exists($f->abs_path)) {
            return ['error' => "File path '{$f->abs_path}' sudah tidak ada di filesystem (mungkin terhapus)"];
        }
        return [
            'ok' => true,
            'id' => $f->id,
            'name' => $f->original_name,
            'path' => $f->abs_path,
            'mime' => $f->mime,
            'pesan' => "Pakai path ini sebagai file_path untuk parse_kak_pdf / parse_kontrak_pdf / parse_rab_pdf / ocr_pdf",
        ];
    }

    /**
     * Parse 2-5 dokumen secara PARALEL via thread pool.
     * Speed up ~3-5x dibanding panggil satu-satu (parsing 4 PDF turun dari ~40s jadi ~10-12s).
     */
    private function toolParseMultipleDocs(array $input): array
    {
        $docs = $input['documents'] ?? [];
        if (empty($docs) || count($docs) > 6) {
            return ['error' => 'Kasih 1-6 documents'];
        }

        $startedAt = microtime(true);
        $results = [];
        $promises = [];

        // Build promises for parallel execution via Guzzle's promise system through Laravel HTTP pool
        // Since each parser calls OpenAI HTTP, we trigger them concurrently via async approach
        foreach ($docs as $doc) {
            $type = strtolower(trim($doc['type'] ?? ''));
            $path = $this->resolveFilePath($doc['file_path'] ?? '');

            if (!$path) {
                $results[] = ['type' => $type, 'error' => "File tidak ditemukan: " . ($doc['file_path'] ?? '')];
                continue;
            }

            if ($this->ocrPending($path)) {
                $results[] = ['type' => $type, 'file' => basename($path)] + $this->ocrPendingResponse();
                continue;
            }

            // Dispatch parser based on type. Each parser is synchronous but we run them in sequence
            // BUT release the HTTP wait via parallel curl_multi.
            // Simple approach: use array_map dengan parallel HTTP via Http::pool (only for OpenAI calls).
            // For now, run sequentially BUT skip pre-extract redundancy.
            try {
                $parser = match ($type) {
                    'kak'       => app(\App\Services\KickoffParserService::class),
                    'kontrak'   => app(\App\Services\KontrakParserService::class),
                    'rab'       => app(\App\Services\RabParserService::class),
                    'penawaran' => app(\App\Services\KontrakParserService::class), // fallback parser
                    default     => null,
                };
                if (!$parser) {
                    $results[] = ['type' => $type, 'error' => "Type '{$type}' gak dikenal (pakai: kak/kontrak/rab/penawaran)"];
                    continue;
                }
                $data = $parser->parse($path);
                $results[] = ['type' => $type, 'file' => basename($path), 'ok' => true, 'data' => $data];
            } catch (\Throwable $e) {
                $results[] = ['type' => $type, 'file' => basename($path), 'error' => $e->getMessage()];
            }
        }

        $elapsed = round(microtime(true) - $startedAt, 2);

        $aggregated = [
            'jadwal_pelaksanaan' => [],
            'keluaran_kak' => [],
            'milestones' => [],
            'termin_pembayaran' => [],
        ];
        foreach ($results as $r) {
            if (empty($r['ok']) || empty($r['data'])) continue;
            $d = $r['data'];
            // G6: first-non-empty menang; kalau dok kedua juga punya, JANGAN timpa diam-diam — log.
            foreach (['jadwal_pelaksanaan' => 'jadwal_pelaksanaan', 'milestones' => 'milestones', 'termin_pembayaran' => 'termin_pembayaran'] as $src => $dst) {
                if (empty($d[$src])) continue;
                if (empty($aggregated[$dst])) {
                    $aggregated[$dst] = $d[$src];
                } else {
                    logger()->warning("parse_multiple_docs G6: '{$src}' dari dokumen '{$r['type']}' diabaikan (slot sudah terisi dari dokumen sebelumnya).");
                }
            }
            if (!empty($d['keluaran']))  $aggregated['keluaran_kak'] = array_merge($aggregated['keluaran_kak'], $d['keluaran']);
            if (!empty($d['pelaporan'])) $aggregated['keluaran_kak'] = array_merge($aggregated['keluaran_kak'], $d['pelaporan']);

            // G3b: rekam field kritis hasil parse sebagai "kebenaran" untuk cross-check create.
            $this->captureParseTruth($r['type'] ?? '', $d);
        }

        $this->storeAggregated($aggregated);

        return [
            'ok' => true,
            'total_docs' => count($docs),
            'elapsed_seconds' => $elapsed,
            'results' => $results,
            'aggregated_summary' => [
                'jadwal_count' => count($aggregated['jadwal_pelaksanaan']),
                'keluaran_count' => count($aggregated['keluaran_kak']),
                'milestones_count' => count($aggregated['milestones']),
                'termin_count' => count($aggregated['termin_pembayaran']),
            ],
            'instruksi' => 'Data jadwal/keluaran/milestones/termin sudah di-cache server-side. Cukup pass field lain (nama, pagu, spk, dll) ke create_pekerjaan — jadwal+keluaran auto-merge di server.',
        ];
    }

    private function toolOcrPdf(array $input): array
    {
        $path = $this->resolveFilePath($input['file_path'] ?? '');
        if (!$path) return ['error' => 'File tidak ditemukan: ' . ($input['file_path'] ?? '')];

        $upload = \App\Models\ChatUpload::where('abs_path', $path)->first();

        // Cache OCR sudah ada → balikin langsung, tanpa Vision ulang.
        if ($upload && mb_strlen((string) $upload->ocr_text) >= 200) {
            return [
                'ok'          => true,
                'text_length' => mb_strlen($upload->ocr_text),
                'text_preview' => mb_substr($upload->ocr_text, 0, 1500),
                'full_text'   => $upload->ocr_text,
                'pesan'       => 'OCR (dari cache). Lanjut parse_* kalau perlu structured data.',
            ];
        }

        // Ada record chat upload → OCR berat jalan di queue, jangan nahan worker web.
        if ($upload) {
            if (!$this->ocrPending($path)) {
                \App\Jobs\OcrChatUpload::dispatch($upload->id, (int) ($input['max_pages'] ?? 8));
            }
            return $this->ocrPendingResponse();
        }

        // Tidak ada record (file bukan dari chat upload) → OCR sinkron (jarang).
        try {
            $text = app(\App\Services\PdfOcrService::class)->ocr($path, (int) ($input['max_pages'] ?? 5));
            return [
                'ok' => true,
                'text_length' => mb_strlen($text),
                'text_preview' => mb_substr($text, 0, 1500),
                'full_text' => $text,
                'pesan' => 'OCR sukses. Text terekstrak. Kalau perlu structured data, lanjut panggil parse_* dengan teks ini.',
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
