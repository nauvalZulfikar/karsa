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

        return $this->extractFields($text);
    }

    private function extractText(string $pdfPath): string
    {
        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($pdfPath);
            $text   = $pdf->getText();

            $text = preg_replace('/\s+/', ' ', $text);
            return mb_substr($text, 0, 14000);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gagal membaca PDF: ' . $e->getMessage());
        }
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

        Dokumen:
        PROMPT;

        $response = Http::timeout(90)
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
            'pekerjaan'         => $this->normalizePekerjaan($data['pekerjaan'] ?? []),
            'termin_pembayaran' => $this->normalizeTermin($data['termin_pembayaran'] ?? []),
            'milestones'        => $this->normalizeMilestones($data['milestones'] ?? []),
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
}
