<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;

class KontrakParserService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
    }

    public function parse(string $pdfPath): array
    {
        $text = $this->extractText($pdfPath);

        if (empty(trim($text))) {
            throw new \RuntimeException(
                'Dokumen tidak dapat dibaca. Pastikan PDF adalah file digital (bukan hasil scan/foto).'
            );
        }

        $result = $this->extractFields($text);

        $jadwalLowQuality = empty($result['jadwal_pelaksanaan'])
            || count($result['jadwal_pelaksanaan']) <= 1
            || !collect($result['jadwal_pelaksanaan'])->contains(fn ($f) => str_contains(strtoupper($f['nama_fase'] ?? ''), 'KEGIATAN'));

        if ($jadwalLowQuality) {
            try {
                $jadwal = $this->extractJadwalViaVision($pdfPath);
                if (!empty($jadwal) && count($jadwal) > count($result['jadwal_pelaksanaan'] ?? [])) {
                    $result['jadwal_pelaksanaan'] = $jadwal;
                }
            } catch (\Throwable $e) {
                logger()->warning("Vision jadwal extraction failed: {$e->getMessage()}");
            }
        }

        return $result;
    }

    private function extractText(string $pdfPath): string
    {
        // 0. XLSX/XLS handling (penawaran kadang dalam excel)
        $ext = strtolower(pathinfo($pdfPath, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'])) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($pdfPath);
                $lines = [];
                foreach ($spreadsheet->getAllSheets() as $sheet) {
                    $lines[] = "=== Sheet: {$sheet->getTitle()} ===";
                    foreach ($sheet->toArray(null, true, true, false) as $row) {
                        $cells = array_filter(array_map('strval', $row), fn ($v) => trim($v) !== '');
                        if (!empty($cells)) $lines[] = implode(' | ', $cells);
                    }
                }
                return mb_substr(implode("\n", $lines), 0, 14000);
            } catch (\Throwable $e) {
                throw new \RuntimeException('Gagal baca XLSX: ' . $e->getMessage());
            }
        }

        // 1. Cek cache ocr_text di ChatUpload — jauh lebih cepat
        $cached = \App\Models\ChatUpload::where('abs_path', $pdfPath)->first();
        if ($cached && mb_strlen((string) $cached->ocr_text) >= 200) {
            return mb_substr(preg_replace('/\s+/', ' ', $cached->ocr_text), 0, 14000);
        }

        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($pdfPath);
            $text   = trim($pdf->getText());
        } catch (\Throwable $e) {
            $text = '';
        }

        // 2. Scanned PDF fallback: text kosong/sedikit → OCR via Vision
        if (mb_strlen($text) < 200) {
            try {
                $ocrText = trim(app(PdfOcrService::class)->ocr($pdfPath, 6));
                if (mb_strlen($ocrText) >= 200) {
                    $text = $ocrText;
                    \App\Models\ChatUpload::where('abs_path', $pdfPath)->update([
                        'is_scanned_pdf' => true,
                        'ocr_text' => mb_substr($ocrText, 0, 15000),
                    ]);
                }
            } catch (\Throwable $e) {
                if (mb_strlen($text) < 50) {
                    throw new \RuntimeException('PDF tidak terbaca + OCR fallback gagal: ' . $e->getMessage());
                }
            }
        }

        $text = preg_replace('/\s+/', ' ', $text);
        return mb_substr($text, 0, 14000);
    }

    private function extractFields(string $documentText): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY belum dikonfigurasi.');
        }

        $prompt = <<<PROMPT
        Kamu ahli membaca dokumen kontrak dan surat dinas pemerintah Indonesia.
        Ekstrak informasi berikut dari dokumen dan kembalikan sebagai JSON.
        Gunakan null untuk field yang tidak ditemukan di dokumen.
        Semua tanggal harus format YYYY-MM-DD.
        Nilai uang harus berupa angka murni tanpa Rp, titik, atau koma (contoh: 2450000000).

        STRUKTUR OUTPUT JSON:
        {
          "pekerjaan": {
            "nama_pekerjaan": "...",
            "no_spk": "...",
            "tanggal_spk": "YYYY-MM-DD",
            "no_spmk": null,
            "tanggal_spmk": null,
            "nilai_pagu": 0,
            "nilai_kontrak": 0,
            "hari_kerja": 0,
            "satuan_waktu": "kalender atau kerja",
            "tanggal_mulai": null,
            "tanggal_akhir": null,
            "nama_perusahaan": "...",
            "tahun_anggaran": 2026,
            "denda_per_hari_permil": null,
            "catatan": "ringkasan poin penting (maks 200 char)"
          },
          "termin_pembayaran": [
            {
              "nomor_termin": 1,
              "nama_termin": "Termin I / Uang Muka / dst",
              "persen_progres_syarat": 30.0,
              "persen_nilai": 30.0,
              "syarat_dokumen": "deskripsi syarat (opsional)"
            }
          ],
          "milestones": [
            {
              "urutan": 1,
              "nama": "Mobilisasi alat dan material",
              "deskripsi": "...",
              "progres_target_persen": 5.0,
              "hari_setelah_mulai": 14,
              "sumber": "kontrak atau generated_ai"
            }
          ],
          "jadwal_pelaksanaan": [
            {
              "nama_fase": "Fase A / Tahap I / label apapun dari dokumen",
              "kegiatan": ["aktivitas 1", "aktivitas 2"],
              "minggu_selesai": 4
            }
          ]
        }

        ATURAN PENTING:
        1. termin_pembayaran: ekstrak SEMUA termin yang disebutkan di kontrak (uang muka, termin I/II/III, retensi, dll). persen_nilai = % dari nilai kontrak. Jika tidak disebut eksplisit, kosongkan array.
        2. milestones:
           - Jika kontrak menyebut milestone/jadwal eksplisit (misal "minggu ke-4 pekerjaan tanah selesai"), gunakan sumber: "kontrak"
           - Jika tidak ada milestone eksplisit, GENERATE 3-5 milestone wajar berdasarkan jenis pekerjaan dan durasi, dengan sumber: "generated_ai"
           - hari_setelah_mulai: berapa hari dari tanggal mulai (akan dihitung jadi tanggal target)
           - progres_target_persen: target progres kumulatif di milestone tersebut
        3. denda_per_hari_permil: denda keterlambatan dalam permil (1‰ = 1.0). Untuk identifikasi tingkat kekritisan deadline.
        4. jadwal_pelaksanaan: Ekstrak tabel Jadwal Pelaksanaan dari SPK. Deteksi label fase (A/B/C/D, Fase I/II/III, atau apapun).
           minggu_selesai = nomor minggu akhir dari kolom Gantt (integer dari awal kontrak). Kalau tabel tidak ada, kembalikan array kosong [].

        Dokumen:
        PROMPT;

        $response = Http::timeout(120)
            ->connectTimeout(30)
            ->retry(2, 2000)
            ->withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model'           => 'gpt-4o-mini',
                'max_tokens'      => 2048,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => 'Kamu adalah ekstractor data dari dokumen kontrak pemerintah Indonesia. Selalu kembalikan JSON valid sesuai struktur yang diminta.'],
                    ['role' => 'user',   'content' => $prompt . "\n\n" . $documentText],
                ],
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message', $response->body());
            throw new \RuntimeException("OpenAI API: {$error}");
        }

        $raw  = $response->json('choices.0.message.content', '{}');
        $data = json_decode($raw, true) ?? [];

        return [
            'pekerjaan'          => $this->normalizePekerjaan($data['pekerjaan'] ?? []),
            'termin_pembayaran'  => $this->normalizeTermin($data['termin_pembayaran'] ?? []),
            'milestones'         => $this->normalizeMilestones($data['milestones'] ?? []),
            'jadwal_pelaksanaan' => $this->normalizeJadwal($data['jadwal_pelaksanaan'] ?? []),
        ];
    }

    private function normalizePekerjaan(array $data): array
    {
        $result = [];

        foreach (['nama_pekerjaan', 'no_spk', 'no_spmk', 'nama_perusahaan', 'satuan_waktu', 'catatan'] as $f) {
            $result[$f] = isset($data[$f]) && $data[$f] !== null ? (string) $data[$f] : null;
        }

        foreach (['tanggal_spk', 'tanggal_spmk', 'tanggal_mulai', 'tanggal_akhir'] as $f) {
            $result[$f] = $this->normalizeDate($data[$f] ?? null);
        }

        $result['nilai_pagu']     = $this->normalizeNumber($data['nilai_pagu'] ?? null);
        $result['nilai_kontrak']  = $this->normalizeNumber($data['nilai_kontrak'] ?? null);
        $result['hari_kerja']     = isset($data['hari_kerja']) ? (int) $data['hari_kerja'] : null;
        $result['tahun_anggaran'] = isset($data['tahun_anggaran']) ? (int) $data['tahun_anggaran'] : (int) date('Y');
        $result['denda_per_hari_permil'] = isset($data['denda_per_hari_permil'])
            ? (float) $data['denda_per_hari_permil'] : null;

        if (!empty($result['satuan_waktu'])) {
            $s = strtolower($result['satuan_waktu']);
            $result['satuan_waktu'] = str_contains($s, 'kalender') ? 'kalender' : 'kerja';
        }

        if (empty($result['tanggal_mulai']) && !empty($result['tanggal_spmk'])) {
            $result['tanggal_mulai'] = $result['tanggal_spmk'];
        }

        if (empty($result['tanggal_akhir']) && !empty($result['tanggal_mulai']) && !empty($result['hari_kerja'])) {
            try {
                $result['tanggal_akhir'] = \Carbon\Carbon::parse($result['tanggal_mulai'])
                    ->addDays($result['hari_kerja'])->format('Y-m-d');
            } catch (\Throwable) {}
        }

        return $result;
    }

    private function normalizeTermin(array $list): array
    {
        $result = [];
        foreach ($list as $i => $item) {
            $result[] = [
                'nomor_termin'          => (int) ($item['nomor_termin'] ?? ($i + 1)),
                'nama_termin'           => (string) ($item['nama_termin'] ?? 'Termin ' . ($i + 1)),
                'persen_progres_syarat' => isset($item['persen_progres_syarat'])
                    ? (float) $item['persen_progres_syarat'] : 0.0,
                'persen_nilai'          => isset($item['persen_nilai'])
                    ? (float) $item['persen_nilai'] : 0.0,
                'syarat_dokumen'        => isset($item['syarat_dokumen'])
                    ? (string) $item['syarat_dokumen'] : null,
            ];
        }
        return $result;
    }

    private function normalizeMilestones(array $list): array
    {
        $result = [];
        foreach ($list as $i => $item) {
            $result[] = [
                'urutan'                => (int) ($item['urutan'] ?? ($i + 1)),
                'nama'                  => (string) ($item['nama'] ?? 'Milestone ' . ($i + 1)),
                'deskripsi'             => isset($item['deskripsi']) ? (string) $item['deskripsi'] : null,
                'progres_target_persen' => isset($item['progres_target_persen'])
                    ? (float) $item['progres_target_persen'] : 0.0,
                'hari_setelah_mulai'    => isset($item['hari_setelah_mulai'])
                    ? (int) $item['hari_setelah_mulai'] : 0,
                'sumber'                => in_array($item['sumber'] ?? 'manual', ['kontrak', 'generated_ai', 'manual'])
                    ? $item['sumber'] : 'manual',
            ];
        }
        return $result;
    }

    private function normalizeJadwal(array $list): array
    {
        $result = [];
        foreach ($list as $item) {
            if (!is_array($item)) continue;
            $minggu = isset($item['minggu_selesai']) ? (int) $item['minggu_selesai'] : 0;
            if ($minggu <= 0) continue;
            $kegiatan = [];
            if (is_array($item['kegiatan'] ?? null)) {
                foreach ($item['kegiatan'] as $k) {
                    $s = isset($k) && $k !== '' ? (string) $k : null;
                    if ($s !== null) $kegiatan[] = $s;
                }
            }
            $result[] = [
                'nama_fase'    => (string) ($item['nama_fase'] ?? ''),
                'kegiatan'     => $kegiatan,
                'minggu_selesai' => $minggu,
            ];
        }
        return $result;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (empty($value)) return null;
        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeNumber(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $clean = preg_replace('/[^0-9.]/', '', (string) $value);
        return is_numeric($clean) ? $clean : null;
    }

    private function extractJadwalViaVision(string $pdfPath): array
    {
        if (empty($this->apiKey)) return [];

        $candidates = $this->findJadwalCandidatePages($pdfPath);
        if (empty($candidates)) return [];

        $images = [];
        foreach ($candidates as $pageIdx) {
            $png = $this->renderSinglePage($pdfPath, $pageIdx);
            if (!empty($png)) {
                $images[] = ['page' => $pageIdx, 'base64' => $png];
            }
        }
        if (empty($images)) return [];

        $prompt = count($images) > 1
            ? "Kamu menerima " . count($images) . " gambar halaman PDF. Cari yang berisi tabel Jadwal Pelaksanaan / Gantt chart (bukan diagram alir / flowchart). Abaikan halaman yang bukan Gantt chart.\n\n"
            : "";

        $prompt .= <<<'PROMPT'
Dari gambar Gantt chart / Jadwal Pelaksanaan, perhatikan VISUAL: posisi bar berwarna di kolom minggu, arah flow kegiatan, S-curve kalau ada.

Ekstrak dan return HANYA valid JSON (tanpa markdown/komentar):
{
  "jadwal_pelaksanaan": [
    {
      "nama_fase": "label fase persis dari dokumen (misal: A. KEGIATAN PERSIAPAN)",
      "kegiatan": ["sub-kegiatan 1", "sub-kegiatan 2"],
      "minggu_selesai": 1
    }
  ]
}

ATURAN:
- Baca SETIAP baris kegiatan dari tabel, jangan skip
- minggu_selesai = minggu ke-berapa dari awal proyek kegiatan ini selesai (lihat posisi bar di kolom)
- Kalau bar span 2 minggu (misal minggu 2-3), pakai minggu akhir (3)
- Urutan fase sesuai urutan KRONOLOGIS (mana yang mulai duluan), BUKAN urutan baris di tabel
- Kalau tabel terbalik (baris bawah = awal proyek), balik urutannya
PROMPT;

        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($images as $img) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:image/png;base64,' . $img['base64']],
            ];
        }

        $response = Http::timeout(120)
            ->connectTimeout(30)
            ->withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model'      => 'gpt-4o',
                'max_tokens' => 1500,
                'messages'   => [
                    ['role' => 'system', 'content' => 'Kamu ahli membaca Gantt chart dan jadwal proyek dari gambar. Return HANYA JSON valid. Abaikan gambar yang bukan Gantt chart.'],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if (!$response->successful()) return [];

        $raw = $response->json('choices.0.message.content', '');
        $raw = preg_replace('/^```json\s*|```\s*$/m', '', trim($raw));
        $data = json_decode($raw, true);

        return $this->normalizeJadwal($data['jadwal_pelaksanaan'] ?? []);
    }

    private function findJadwalCandidatePages(string $pdfPath): array
    {
        $script = <<<'PY'
import sys, json, fitz
doc = fitz.open(sys.argv[1])
candidates = []
for i in range(len(doc)):
    text = doc[i].get_text().lower()
    if 'jadwal pelaksanaan' not in text:
        continue
    # Skip TOC (early pages with lots of text)
    if i < 10 and len(doc[i].get_text().strip()) > 500:
        continue
    # Check current page and next page
    cur_len = len(doc[i].get_text().strip())
    next_idx = i + 1 if i + 1 < len(doc) else None
    next_len = len(doc[next_idx].get_text().strip()) if next_idx and next_idx < len(doc) else 9999
    # Prefer pages with little text (= image/table)
    if cur_len < 200:
        candidates.append(i)
    if next_idx and next_len < 200:
        candidates.append(next_idx)
# Deduplicate and limit to 4
candidates = sorted(set(candidates))[:4]
print(json.dumps(candidates))
PY;
        $tmpScript = tempnam(sys_get_temp_dir(), 'jadwal_') . '.py';
        file_put_contents($tmpScript, $script);
        $output = trim((string) shell_exec(sprintf('python "%s" "%s" 2>&1', $tmpScript, $pdfPath)));
        @unlink($tmpScript);

        $pages = json_decode($output, true);
        return is_array($pages) ? $pages : [];
    }

    private function renderSinglePage(string $pdfPath, int $pageIndex): ?string
    {
        $script = <<<'PY'
import sys, base64, fitz
doc = fitz.open(sys.argv[1])
idx = int(sys.argv[2])
if idx >= len(doc):
    sys.exit(1)
page = doc[idx]
zoom = 250 / 72
pix = page.get_pixmap(matrix=fitz.Matrix(zoom, zoom), alpha=False)
print(base64.b64encode(pix.tobytes("png")).decode("ascii"))
PY;
        $tmpScript = tempnam(sys_get_temp_dir(), 'render_') . '.py';
        file_put_contents($tmpScript, $script);
        $output = trim((string) shell_exec(sprintf('python "%s" "%s" %d 2>&1', $tmpScript, $pdfPath, $pageIndex)));
        @unlink($tmpScript);

        return !empty($output) && strlen($output) > 1000 ? $output : null;
    }
}
