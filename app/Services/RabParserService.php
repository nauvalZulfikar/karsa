<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;

/**
 * Parse Lampiran Negosiasi (RAB) — line items, harga negosiasi, totals.
 * Strategy: extract text dari PDF/XLSX → kirim ke OpenAI → structured JSON.
 */
class RabParserService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
    }

    public function parse(string $filePath): array
    {
        $text = $this->extractText($filePath);

        if (empty(trim($text))) {
            throw new \RuntimeException(
                'Dokumen RAB tidak dapat dibaca. Pastikan file PDF/XLSX valid (bukan hasil scan/foto).'
            );
        }

        return $this->extractFields($text);
    }

    private function extractText(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            $cached = \App\Models\ChatUpload::where('abs_path', $filePath)->first();
            if ($cached && mb_strlen((string) $cached->ocr_text) >= 200) {
                return mb_substr(preg_replace('/\s+/', ' ', $cached->ocr_text), 0, 14000);
            }

            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($filePath);
                $text = trim($pdf->getText());
            } catch (\Throwable $e) {
                $text = '';
            }

            if (mb_strlen($text) < 200) {
                try {
                    $ocrText = trim(app(PdfOcrService::class)->ocr($filePath, 6));
                    if (mb_strlen($ocrText) >= 200) {
                        $text = $ocrText;
                        \App\Models\ChatUpload::where('abs_path', $filePath)->update([
                            'is_scanned_pdf' => true,
                            'ocr_text' => mb_substr($ocrText, 0, 15000),
                        ]);
                    }
                } catch (\Throwable $e) {
                    if (mb_strlen($text) < 50) {
                        throw new \RuntimeException('PDF RAB tidak terbaca + OCR fallback gagal: ' . $e->getMessage());
                    }
                }
            }

            $text = preg_replace('/\s+/', ' ', $text);
            return mb_substr($text, 0, 14000);
        }

        if (in_array($ext, ['xlsx', 'xls'])) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $lines = [];
                foreach ($spreadsheet->getAllSheets() as $sheet) {
                    $lines[] = "=== Sheet: {$sheet->getTitle()} ===";
                    foreach ($sheet->toArray(null, true, true, false) as $row) {
                        $cells = array_filter(array_map('strval', $row), fn ($v) => trim($v) !== '');
                        if (!empty($cells)) {
                            $lines[] = implode(' | ', $cells);
                        }
                    }
                }
                return mb_substr(implode("\n", $lines), 0, 14000);
            } catch (\Throwable $e) {
                throw new \RuntimeException('Gagal membaca XLSX RAB: ' . $e->getMessage());
            }
        }

        throw new \RuntimeException("Format file '$ext' tidak didukung. Gunakan PDF atau XLSX.");
    }

    private function extractFields(string $documentText): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY belum dikonfigurasi.');
        }

        $prompt = <<<PROMPT
        Kamu ahli membaca Rincian Anggaran Biaya (RAB) / Lampiran Negosiasi proyek pemerintah Indonesia.
        Ekstrak SEMUA line item dari dokumen dan kembalikan sebagai JSON.

        Dokumen biasanya berisi 2 kategori biaya:
        1. BIAYA LANGSUNG PERSONIL (gaji tim: Team Leader, Surveyor, Teknisi, Drafter, dll)
        2. BIAYA LANGSUNG NON-PERSONIL (sewa alat, ATK, sondir, bor log, pelaporan, dll)

        STRUKTUR OUTPUT JSON:
        {
          "nama_pekerjaan": "...",
          "vendor": "PT/CV ...",
          "total_kontrak": 99594750,
          "ppn_persen": 11,
          "subtotal_sebelum_ppn": 89725000,
          "items": [
            {
              "kategori": "personil" atau "non_personil",
              "subkategori": "tenaga_ahli|tenaga_pendukung|kantor|lapangan|pelaporan|rapat|lainnya",
              "uraian": "Team Leader/Tenaga Ahli Geoteknik",
              "volume": 1,
              "satuan": "Org/Bln" atau "Titik" atau "Ls" atau "Bk" dll,
              "waktu": 1,
              "harga_satuan": 17000000,
              "jumlah_harga": 17000000,
              "pajak_persen": 11
            }
          ]
        }

        ATURAN:
        1. Ambil HARGA NEGOSIASI (kolom paling kanan), bukan HPS atau Penawaran Terkoreksi
        2. Semua nilai uang = integer angka murni (no Rp, no titik/koma)
        3. Jika line item adalah subtotal/header/jumlah, JANGAN dimasukkan ke items[]
        4. Hanya ambil items yang punya harga & volume jelas
        5. Untuk satuan, gunakan exact string dari dokumen
        6. kategori: "personil" untuk tim/SDM, "non_personil" untuk barang/jasa/alat

        Dokumen:
        PROMPT;

        $response = Http::timeout(120)
            ->connectTimeout(30)
            ->retry(2, 2000)
            ->withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model'           => 'gpt-4o-mini',
                'max_tokens'      => 3000,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => 'Kamu adalah extractor data RAB pemerintah Indonesia. Selalu kembalikan JSON valid sesuai struktur yang diminta.'],
                    ['role' => 'user',   'content' => $prompt . "\n\n" . $documentText],
                ],
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message', $response->body());
            throw new \RuntimeException("OpenAI API: {$error}");
        }

        $raw = $response->json('choices.0.message.content', '{}');
        $data = json_decode($raw, true) ?? [];

        return $this->normalize($data);
    }

    private function normalize(array $data): array
    {
        return [
            'nama_pekerjaan'        => isset($data['nama_pekerjaan']) ? (string) $data['nama_pekerjaan'] : null,
            'vendor'                => isset($data['vendor']) ? (string) $data['vendor'] : null,
            'total_kontrak'         => isset($data['total_kontrak']) ? (int) $data['total_kontrak'] : null,
            'subtotal_sebelum_ppn'  => isset($data['subtotal_sebelum_ppn']) ? (int) $data['subtotal_sebelum_ppn'] : null,
            'ppn_persen'            => isset($data['ppn_persen']) ? (float) $data['ppn_persen'] : 11.0,
            'items'                 => $this->normalizeItems($data['items'] ?? []),
        ];
    }

    private function normalizeItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (empty($item['uraian']) || empty($item['jumlah_harga'])) {
                continue;
            }
            $kat = strtolower((string) ($item['kategori'] ?? 'non_personil'));
            $kat = in_array($kat, ['personil', 'non_personil']) ? $kat : 'non_personil';

            $result[] = [
                'kategori'      => $kat,
                'subkategori'   => (string) ($item['subkategori'] ?? 'lainnya'),
                'uraian'        => (string) $item['uraian'],
                'volume'        => (float) ($item['volume'] ?? 1),
                'satuan'        => (string) ($item['satuan'] ?? 'Ls'),
                'waktu'         => (float) ($item['waktu'] ?? 1),
                'harga_satuan'  => (int) ($item['harga_satuan'] ?? 0),
                'jumlah_harga'  => (int) $item['jumlah_harga'],
                'pajak_persen'  => (float) ($item['pajak_persen'] ?? 11.0),
            ];
        }
        return $result;
    }
}
