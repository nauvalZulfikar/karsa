<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;

/**
 * Parse KAK (Kerangka Acuan Kerja) — dokumen TOR pemerintah Indonesia.
 *
 * Yang ADA di KAK (true source of truth):
 *   nama_pekerjaan, lokasi, nilai_pagu, durasi (kalender/kerja),
 *   ppk_nama+jabatan, sumber_dana, kbli, tenaga_ahli, tanggal_kak (signing date).
 *
 * Yang TIDAK ADA di KAK (jangan tebak, return null):
 *   no_spk, no_spmk, tanggal_spk, tanggal_spmk, tanggal_mulai, tanggal_akhir,
 *   nilai_kontrak, nama_perusahaan — itu semua di dokumen SPK/SPMK/Kontrak.
 *
 * Strategy: text extract → OCR fallback → GPT-4o-mini JSON mode (sama dgn
 * KontrakParserService) supaya layout-resilient & semantic-aware.
 */
class KickoffParserService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.llm_batch.api_key', '');
        $this->apiUrl = rtrim(config('services.llm_batch.base_url', 'https://api.openai.com/v1'), '/') . '/chat/completions';
    }

    public function parse(string $filePath): array
    {
        [$text, $signatureBlock] = $this->extractText($filePath);

        if (empty(trim($text))) {
            throw new \RuntimeException(
                'Dokumen KAK tidak dapat dibaca. Pastikan PDF adalah file digital (bukan hasil scan/foto buruk).'
            );
        }

        return $this->extractFields($text, $signatureBlock);
    }

    /**
     * @return array{0:string, 1:string} [textForLLM (truncated 14000), signatureBlock (last 2000 of FULL text)]
     */
    private function extractText(string $filePath): array
    {
        // Step 1: ALWAYS try fresh Smalot parse first — digital PDF lossless &
        // termasuk signature block. Cache ocr_text bisa stale/truncated tanpa signature.
        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($filePath);
            $text   = trim($pdf->getText());
        } catch (\Throwable $e) {
            $text = '';
        }

        // Step 2: kalau Smalot return < 200 chars → mungkin scanned PDF.
        //   Cek cache dulu, kalau ga ada baru OCR.
        if (mb_strlen($text) < 200) {
            $cached = \App\Models\ChatUpload::where('abs_path', $filePath)->first();
            if ($cached && mb_strlen((string) $cached->ocr_text) >= 200) {
                $text = $cached->ocr_text;
            } else {
                try {
                    $ocrText = trim(app(PdfOcrService::class)->ocr($filePath, 8));
                    if (mb_strlen($ocrText) >= 200) {
                        $text = $ocrText;
                        \App\Models\ChatUpload::where('abs_path', $filePath)->update([
                            'is_scanned_pdf' => true,
                            'ocr_text' => mb_substr($ocrText, 0, 20000),
                        ]);
                    }
                } catch (\Throwable $e) {
                    if (mb_strlen($text) < 50) {
                        throw new \RuntimeException('PDF KAK tidak terbaca + OCR fallback gagal: ' . $e->getMessage());
                    }
                }
            }
        }

        $fullText = preg_replace('/\s+/', ' ', $text);
        // Signature block diambil dari TEKS PENUH (bukan truncated 14000) supaya
        // signature di akhir dokumen yang lebih dari 14000 char tetap ke-capture.
        $signatureBlock = $this->extractSignatureBlock($fullText);
        return [mb_substr($fullText, 0, 14000), $signatureBlock];
    }

    /**
     * Ambil bagian akhir dokumen (~2000 char) sebagai "signature block hint".
     * PPK signature umumnya di halaman terakhir KAK.
     */
    private function extractSignatureBlock(string $fullText): string
    {
        $len = mb_strlen($fullText);
        if ($len <= 2000) return $fullText;
        return mb_substr($fullText, $len - 2000);
    }

    private function extractFields(string $documentText, string $signatureBlock): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY belum dikonfigurasi.');
        }

        $prompt = <<<PROMPT
        Kamu ahli membaca dokumen KAK (Kerangka Acuan Kerja) / TOR pemerintah Indonesia.
        Ekstrak data dari KAK ini dan kembalikan sebagai JSON.

        ATURAN PENTING — APA YANG ADA DI KAK vs APA YANG TIDAK ADA:
        - KAK berisi: nama pekerjaan, lokasi, pagu, durasi (hari kalender/kerja),
          PPK (Pejabat Pembuat Komitmen), sumber dana, KBLI, tenaga ahli, tanggal tandatangan KAK.
        - KAK TIDAK berisi (jangan tebak, return null!): nomor SPK, nomor SPMK,
          tanggal SPK, tanggal SPMK, tanggal mulai pekerjaan, tanggal akhir pekerjaan,
          nilai kontrak, nama vendor/perusahaan pelaksana.
          Field-field itu ada di dokumen SPK/SPMK/Kontrak, BUKAN di KAK.
        - Tanggal yang ada di akhir KAK (misal "Soreang, 19 Desember 2025") itu
          tanggal tandatangan KAK oleh PPK — masukkan ke field tanggal_kak, BUKAN tanggal_spk.
        - Durasi sering ditulis "30 (Tiga Puluh) hari kalender" — extract jadi
          durasi_hari=30, satuan_waktu="kalender". Kalau "hari kerja" → satuan_waktu="kerja".

        BLOK TANDA TANGAN (bagian akhir dokumen, prioritaskan untuk ppk_nama, ppk_nip, tanggal_kak):
        ---SIGNATURE_BLOCK_START---
        $signatureBlock
        ---SIGNATURE_BLOCK_END---

        STRUKTUR OUTPUT JSON (semua field ada, isi null kalau tidak ditemukan).
        PENTING: tanggal harus EXACT copy dari dokumen — jangan ubah tahun!

        {
          "nama_pekerjaan": "string, ambil dari header 'NAMA PEKERJAAN:' atau bagian 'Pekerjaan : ...'",
          "lokasi_pekerjaan": "string, ambil dari 'Lokasi Kegiatan' (alamat lengkap desa/kec/kab)",
          "nilai_pagu": "angka murni tanpa Rp/titik/koma",
          "durasi_hari": "integer (cuma angka, contoh: 30)",
          "satuan_waktu": "string, 'kalender' atau 'kerja'",
          "tanggal_kak": "YYYY-MM-DD — tanggal di blok tanda tangan AKHIR dokumen (misal 'Soreang, 19 Desember 2025' → '2025-12-19'). Tahun harus PERSIS sesuai dokumen, jangan tebak!",
          "ppk_nama": "NAMA ORANG (bukan jabatan!) — ambil dari blok tanda tangan akhir. Ciri: ada gelar (S.T., M.T., M.PSDA, dll) atau diikuti baris NIP. JANGAN ambil 'Kepala Bidang...' karena itu jabatan, bukan nama. Kalau di signature block ga ada nama, isi null.",
          "ppk_jabatan": "jabatan PPK (misal 'Kepala Bidang Bangunan Gedung dan Pengembangan Permukiman') — dari label 'PPK : ...' di section organisasi.",
          "ppk_nip": "NIP PPK di blok tanda tangan akhir, format apa adanya",
          "instansi": "nama dinas/SKPD (misal 'Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung')",
          "tahun_anggaran": "integer — tahun anggaran yang disebut di dokumen, EXACT (jangan default ke tahun sekarang)",
          "sumber_dana": "contoh: 'APBD Kabupaten Bandung'",
          "nama_program": "kalau ada",
          "nama_kegiatan": "kalau ada",
          "kode_rekening": "kalau ada",
          "kbli": "kode KBLI yang disyaratkan, contoh '70209'",
          "sbu": "SBU yang disyaratkan, contoh '1.SI.00'",
          "tenaga_ahli": [
            {
              "posisi": "string, contoh 'Team Leader / Tenaga Ahli Geoteknik'",
              "kategori": "ahli atau pendukung",
              "kualifikasi_pendidikan": "string, contoh 'S1 Teknik Sipil'",
              "kualifikasi_sertifikat": "string, contoh 'SKK Ahli Muda Geoteknik Jenjang 7'",
              "pengalaman_tahun": "integer atau null",
              "jumlah_orang": "integer",
              "durasi_bulan": "float, contoh 1 atau 0.5"
            }
          ],
          "keluaran": [
            {
              "nama": "Laporan Pendahuluan",
              "isi": ["sub-item 1 sesuai teks dokumen", "sub-item 2"]
            }
          ],
          "pelaporan": [
            {
              "nama": "Laporan Akhir",
              "isi": ["sub-item 1 sesuai teks dokumen"]
            }
          ],
          "catatan": "string, ringkasan poin penting maks 200 char"
        }

        INSTRUKSI keluaran & pelaporan:
        Ekstrak Section 11 (Keluaran) dan Section 12 (Pelaporan) dari KAK. Setiap deliverable sebagai objek {nama, isi[]}.
        "isi" berisi sub-item/bullet sesuai teks dokumen (exact wording). Kalau section tidak ada, kembalikan array kosong [].

        WAJIB diisi (kalau benar-benar tidak ada di dokumen, null):
        - nama_pekerjaan, lokasi_pekerjaan, nilai_pagu, durasi_hari, satuan_waktu, ppk_nama

        Dokumen KAK:
        PROMPT;

        $response = Http::timeout(120)
            ->connectTimeout(30)
            ->retry(2, 2000)
            ->withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model'           => config('services.llm_batch.model', 'gpt-4o-mini'),
                'max_tokens'      => 2048,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => 'Kamu adalah extractor data dari dokumen KAK (Kerangka Acuan Kerja) pemerintah Indonesia. Selalu kembalikan JSON valid sesuai struktur yang diminta. Jangan tebak — kalau field tidak ada di dokumen, isi null.'],
                    ['role' => 'user',   'content' => $prompt . "\n\n" . $documentText],
                ],
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message', $response->body());
            throw new \RuntimeException("OpenAI API: {$error}");
        }

        $raw  = $response->json('choices.0.message.content', '{}');
        $data = json_decode($raw, true) ?? [];

        $normalized = $this->normalize($data);

        // Regex backstop untuk signature block — gpt-4o-mini sering miss
        // ppk_nama / ppk_nip / tanggal_kak. Format KAK pemerintah cukup standard,
        // jadi regex bisa fill gaps deterministically.
        return $this->applySignatureRegexFallback($normalized, $signatureBlock);
    }

    /**
     * Regex fallback: extract tanggal_kak, ppk_nama, ppk_nip dari signature block
     * KALAU LLM ga isi. Pola signature KAK pemerintah cukup standard:
     *   "Soreang, 19 Desember 2025
     *    Pejabat Pembuat Komitmen
     *    [Nama Orang dengan gelar]
     *    NIP. xxxxxxxxxxxxxx"
     */
    private function applySignatureRegexFallback(array $result, string $signatureBlock): array
    {
        $months = [
            'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
            'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
            'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12',
        ];

        // Tanggal KAK: SELALU prioritaskan regex dari signature block — format
        // "Kota, DD Bulan YYYY" sangat standard di KAK pemerintah, lebih reliable
        // daripada LLM (yang kadang halusinasi tahun). LLM hanya backup.
        $datePattern = '/(\d{1,2})\s+(' . implode('|', array_keys($months)) . ')\s+(\d{4})/i';
        if (preg_match($datePattern, $signatureBlock, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = $months[strtolower($m[2])];
            $result['tanggal_kak'] = "{$m[3]}-{$month}-{$day}";
        }

        // NIP: "NIP. xxxxxxxxxxxxxxxx"
        if (empty($result['ppk_nip'])) {
            if (preg_match('/NIP\.?\s*:?\s*([\d\s]{16,30})/i', $signatureBlock, $m)) {
                $result['ppk_nip'] = trim($m[1]);
            }
        }

        // PPK nama: ambil baris TEPAT SEBELUM "NIP." — itu kanonik di KAK pemerintah.
        // Pattern: capture text "Pejabat Pembuat Komitmen [NAMA] NIP." dimana
        // [NAMA] adalah nama orang dengan gelar (Widya Astuti, S.T., MPSDA.)
        if (empty($result['ppk_nama'])) {
            // Step 1: extract teks antara "Pejabat Pembuat Komitmen" dan "NIP"
            if (preg_match('/Pejabat\s+Pembuat\s+Komitmen\s+(.+?)\s+NIP/is', $signatureBlock, $m)) {
                $candidate = trim($m[1]);
                // Filter: harus mengandung gelar (penanda nama orang)
                if (preg_match('/(?:S\.?T|S\.?E|M\.?T|M\.?M|M\.?Sc|M\.?PSDA|MPSDA|S\.?Sos|S\.?Pd|M\.?Pd|S\.?H|M\.?H|M\.?Si|S\.?I\.?P|Ph\.?D|Drs|Dra|Ir|Dr)\.?/i', $candidate)) {
                    $result['ppk_nama'] = $candidate;
                }
            }
            // Fallback: cari pattern nama dengan gelar yang langsung diikuti NIP
            if (empty($result['ppk_nama'])) {
                if (preg_match('/([A-Z][A-Za-z\']+(?:\s+[A-Z][A-Za-z\']+){1,4}(?:,\s*(?:S\.?T|S\.?E|M\.?T|M\.?M|M\.?Sc|M\.?PSDA|MPSDA|S\.?Sos|S\.?Pd|M\.?Pd|S\.?H|M\.?H|M\.?Si|S\.?I\.?P|Ph\.?D|Drs|Dra|Ir|Dr)\.?){1,3}\.?)\s+NIP/i', $signatureBlock, $m)) {
                    $result['ppk_nama'] = trim($m[1]);
                }
            }

            // Post-process: strip leading acronym tokens (PPK / KPA / PA / PPTK / PPHP) yang
            // ke-capture jadi name word ketika input pendek/tidak ada "Pejabat Pembuat Komitmen"
            // full phrase. Acronyms ini bukan nama orang, drop dari prefix.
            if (!empty($result['ppk_nama'])) {
                $result['ppk_nama'] = preg_replace(
                    '/^(?:PPK|KPA|PA|PPTK|PPHP|PPK\/PPTK)\s+/i',
                    '',
                    $result['ppk_nama']
                );
                // Trim leftover whitespace & guard against empty
                $result['ppk_nama'] = trim($result['ppk_nama']) ?: null;
            }
        }

        return $result;
    }

    private function normalize(array $data): array
    {
        $result = [
            // KAK fields (yang beneran di KAK)
            'nama_pekerjaan'   => $this->str($data['nama_pekerjaan']  ?? null),
            'lokasi_pekerjaan' => $this->str($data['lokasi_pekerjaan'] ?? null),
            'nilai_pagu'       => $this->num($data['nilai_pagu']      ?? null),
            'durasi_hari'      => $this->intOrNull($data['durasi_hari'] ?? null),
            'satuan_waktu'     => $this->normalizeSatuan($data['satuan_waktu'] ?? null),
            'tanggal_kak'      => $this->date($data['tanggal_kak']    ?? null),
            'ppk_nama'         => $this->str($data['ppk_nama']        ?? null),
            'ppk_jabatan'      => $this->str($data['ppk_jabatan']     ?? null),
            'ppk_nip'          => $this->cleanNip($data['ppk_nip']    ?? null),
            'instansi'         => $this->str($data['instansi']        ?? null),
            'tahun_anggaran'   => $this->intOrNull($data['tahun_anggaran'] ?? null) ?? (int) date('Y'),
            'sumber_dana'      => $this->str($data['sumber_dana']     ?? null),
            'nama_program'     => $this->str($data['nama_program']    ?? null),
            'nama_kegiatan'    => $this->str($data['nama_kegiatan']   ?? null),
            'kode_rekening'    => $this->str($data['kode_rekening']   ?? null),
            'kbli'             => $this->str($data['kbli']            ?? null),
            'sbu'              => $this->str($data['sbu']             ?? null),
            'tenaga_ahli'      => $this->normalizeTenagaAhli($data['tenaga_ahli'] ?? []),
            'keluaran'         => $this->normalizeKeluaran($data['keluaran'] ?? []),
            'pelaporan'        => $this->normalizeKeluaran($data['pelaporan'] ?? []),
            'catatan'          => $this->str($data['catatan']         ?? null),

            // Field LEGACY — sengaja null karena KAK ga punya data ini
            // (caller harus ambil dari KontrakParserService untuk SPK/SPMK/kontrak)
            'no_spk'           => null,
            'tanggal_spk'      => null,
            'no_spmk'          => null,
            'tanggal_spmk'     => null,
            'nilai_kontrak'    => null,
            'perusahaan_nama'  => null,
            'tanggal_mulai'    => null,
            'tanggal_akhir'    => null,
            'hari_kerja'       => $this->intOrNull($data['durasi_hari'] ?? null),
        ];

        return $result;
    }

    private function str(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function cleanNip(mixed $v): ?string
    {
        $s = $this->str($v);
        if ($s === null) return null;
        // Strip leading "NIP." / "NIP :" / "NIP" prefix kalau LLM nyangkut
        $s = preg_replace('/^NIP\.?\s*:?\s*/i', '', $s);
        return trim($s) ?: null;
    }

    private function num(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        $clean = preg_replace('/[^0-9.]/', '', (string) $v);
        return is_numeric($clean) ? $clean : null;
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        $clean = preg_replace('/[^0-9]/', '', (string) $v);
        return $clean === '' ? null : (int) $clean;
    }

    private function date(mixed $v): ?string
    {
        if (empty($v)) return null;
        try {
            return \Carbon\Carbon::parse((string) $v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeSatuan(mixed $v): ?string
    {
        if (empty($v)) return null;
        $s = strtolower((string) $v);
        if (str_contains($s, 'kalender')) return 'kalender';
        if (str_contains($s, 'kerja'))    return 'kerja';
        return null;
    }

    private function normalizeTenagaAhli(mixed $list): array
    {
        if (!is_array($list)) return [];
        $result = [];
        foreach ($list as $item) {
            if (!is_array($item)) continue;
            $kategori = strtolower((string) ($item['kategori'] ?? 'ahli'));
            $result[] = [
                'posisi'                  => $this->str($item['posisi'] ?? null),
                'kategori'                => in_array($kategori, ['ahli', 'pendukung']) ? $kategori : 'ahli',
                'kualifikasi_pendidikan'  => $this->str($item['kualifikasi_pendidikan'] ?? null),
                'kualifikasi_sertifikat'  => $this->str($item['kualifikasi_sertifikat'] ?? null),
                'pengalaman_tahun'        => $this->intOrNull($item['pengalaman_tahun'] ?? null),
                'jumlah_orang'            => $this->intOrNull($item['jumlah_orang'] ?? null) ?? 1,
                'durasi_bulan'            => isset($item['durasi_bulan']) ? (float) $item['durasi_bulan'] : null,
            ];
        }
        return $result;
    }

    private function normalizeStringList(mixed $list): array
    {
        if (!is_array($list)) return [];
        $result = [];
        foreach ($list as $item) {
            $s = $this->str(is_array($item) ? json_encode($item) : $item);
            if ($s !== null) $result[] = $s;
        }
        return $result;
    }

    private function normalizeKeluaran(mixed $list): array
    {
        if (!is_array($list)) return [];
        $result = [];
        foreach ($list as $item) {
            if (!is_array($item)) continue;
            $nama = $this->str($item['nama'] ?? null);
            if ($nama === null) continue;
            $isi = [];
            if (is_array($item['isi'] ?? null)) {
                foreach ($item['isi'] as $sub) {
                    $s = $this->str(is_array($sub) ? json_encode($sub) : $sub);
                    if ($s !== null) $isi[] = $s;
                }
            }
            $result[] = ['nama' => $nama, 'isi' => $isi];
        }
        return $result;
    }
}
