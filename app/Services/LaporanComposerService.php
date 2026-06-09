<?php

namespace App\Services;

use App\Models\Pekerjaan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * Compose (bukan substitute) laporan teknis berdasarkan playbook di
 * docs/LAPORAN_WRITING_PLAYBOOK.md.
 *
 * Per section, putuskan strategy:
 *   - REUSE : load cached generic content via SectionsLibrary
 *   - COMPOSE : call OpenAI dengan section-pattern prompt + project facts
 *   - HYBRID : load preset + inject facts
 *
 * Assemble final DOCX via PhpWord (BAB structure + paragraph runs).
 * Validate via QualityGateService sebelum return.
 */
class LaporanComposerService
{
    private string $apiKey;
    private string $model  = 'gpt-4o-mini';
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private SectionsLibrary $sections,
        private QualityGateService $gates,
    ) {
        $this->apiKey = (string) config('services.openai.api_key', '');
    }

    /**
     * Compose laporan untuk pekerjaan.
     *
     * @param string $tipe 'pendahuluan' | 'akhir'
     * @return array{ok: bool, output_path: string, filename: string, relative_path: string,
     *               size_bytes: int, jenis: string, tipe: string, sections: array,
     *               quality: array, mode: string}
     */
    public function compose(Pekerjaan $pekerjaan, string $tipe = 'pendahuluan'): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured (services.openai.api_key).');
        }

        $jenis = $this->sections->detectJenis($pekerjaan);
        $facts = $this->sections->buildFactSlots($pekerjaan);
        // Adjust tipe_laporan based on tipe arg
        $facts['tipe_laporan'] = $tipe === 'akhir' ? 'Laporan Akhir' : 'Laporan Pendahuluan';

        $plan = $this->getSectionPlan($jenis, $tipe);

        $composed = [];
        $qualityReports = [];
        foreach ($plan as $section => $strategy) {
            $content = match ($strategy) {
                'reuse'   => $this->sectionReuse($jenis, $section),
                'hybrid'  => $this->sectionHybrid($jenis, $section, $pekerjaan),
                'compose' => $this->sectionCompose($jenis, $section, $pekerjaan, $facts),
                default   => null,
            };

            if ($content === null) {
                logger()->warning("LaporanComposer: section '{$section}' returned null, skipping");
                continue;
            }

            // Quality gate (only for composed content; reuse/hybrid content is pre-validated)
            if ($strategy === 'compose') {
                $report = $this->gates->check($content, $section, $pekerjaan);
                $qualityReports[$section] = $report;
                if (!$report['passed'] && $report['score'] < 6) {
                    // Retry once with stricter prompt
                    $content = $this->sectionCompose($jenis, $section, $pekerjaan, $facts, retry: true, prevIssues: $report['issues']);
                    $report2 = $this->gates->check($content, $section, $pekerjaan);
                    $qualityReports[$section] = $report2;
                }
            }

            $composed[$section] = $content;
        }

        // Assembly: prefer template-based path (preserve images + layout)
        $assembled = $this->assembleViaTemplate($pekerjaan, $tipe, $jenis, $composed, $facts);
        $mode = 'composer_template';

        // Fallback: PhpWord from scratch (no images, but content correct)
        if ($assembled === null) {
            $assembled = $this->assembleDocx($pekerjaan, $tipe, $jenis, $composed, $facts);
            $mode = 'composer_phpword';
        }

        return [
            'ok'            => true,
            'mode'          => $mode,
            'jenis'         => $jenis,
            'tipe'          => $tipe,
            'output_path'   => $assembled['absPath'],
            'relative_path' => $assembled['relativePath'],
            'filename'      => $assembled['filename'],
            'size_bytes'    => $assembled['size'],
            'sections'      => array_keys($composed),
            'quality'       => $qualityReports,
        ];
    }

    /**
     * Template-based assembly: load existing DOCX template (preserve 27 images +
     * layout), replace project-specific section bodies with composer content
     * via Python script. Keeps teori dasar paragraphs verbatim.
     *
     * Returns null kalau template tidak ada — caller fallback ke PhpWord assembly.
     */
    private function assembleViaTemplate(Pekerjaan $pekerjaan, string $tipe, string $jenis, array $composed, array $facts): ?array
    {
        $templatePath = storage_path("laporan_templates/{$jenis}_{$tipe}.docx");
        if (!file_exists($templatePath)) {
            return null;
        }

        $stamp = now()->format('YmdHis');
        $filename = "laporan_{$tipe}_{$jenis}_template_{$stamp}.docx";
        $relativePath = "dokumen/generated/{$pekerjaan->id}/{$filename}";
        $absPath = Storage::disk('local')->path($relativePath);
        if (!is_dir(dirname($absPath))) mkdir(dirname($absPath), 0755, true);

        // Build composed sections JSON (sections that template-replacer can handle)
        $relevant = [
            'kata_pengantar', 'latar_belakang', 'maksud_tujuan', 'lokasi_pekerjaan',
            'ruang_lingkup', 'dasar_hukum', 'gambaran_umum_lokasi',
        ];
        $composedForTemplate = [];
        foreach ($relevant as $sec) {
            if (isset($composed[$sec])) {
                $composedForTemplate[$sec] = $composed[$sec];
            }
        }
        if (empty($composedForTemplate)) {
            logger()->info("Composer template: no relevant composed sections, skip template path");
            return null;
        }

        // Build global string subs map untuk cover + signature + outside-body text
        // Source of leaks (referensi proyek lama yg di-replace dgn proyek baru)
        $stringSubs = $this->buildStringSubs($facts);

        // Write temp JSON files
        $tmpDir = storage_path('app/private/tmp');
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
        $composedJson = $tmpDir . '/composer_' . $pekerjaan->id . '_' . $stamp . '_composed.json';
        $subsJson     = $tmpDir . '/composer_' . $pekerjaan->id . '_' . $stamp . '_subs.json';
        file_put_contents($composedJson, json_encode($composedForTemplate, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($subsJson,     json_encode($stringSubs,         JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $cmd = sprintf(
            '%s "%s" --template "%s" --output "%s" --composed-file "%s" --string-subs-file "%s" 2>&1',
            config('services.python.bin', 'python3'),
            base_path('scripts/laporan_compose_template.py'),
            $templatePath,
            $absPath,
            $composedJson,
            $subsJson
        );
        exec($cmd, $out, $exitCode);
        $stdout = implode("\n", $out);
        @unlink($composedJson);
        @unlink($subsJson);

        if ($exitCode !== 0 || str_contains($stdout, 'ERR:')) {
            logger()->warning("Composer template script fail (exit={$exitCode}): {$stdout}");
            return null;
        }

        if (!file_exists($absPath)) {
            logger()->warning("Composer template: script ran but output file missing: {$absPath}");
            return null;
        }

        logger()->info("Composer template OK: " . trim($stdout));
        return [
            'absPath' => $absPath,
            'relativePath' => $relativePath,
            'filename' => $filename,
            'size' => (int) filesize($absPath),
        ];
    }

    /**
     * Build global string-substitution map utk replace project-specific literals
     * dari template (e.g. "PT. Itergo Buana Utama" → vendor proyek baru) di
     * cover page, signature, dan teks outside body sections.
     */
    private function buildStringSubs(array $facts): array
    {
        $vendor       = $facts['vendor']            ?? '';
        $vendorShort  = $facts['vendor_short']      ?? '';
        $direktur     = $facts['direktur_nama']     ?? '';
        $namaPek      = $facts['nama_pekerjaan']    ?? '';
        $subject      = $facts['project_subject']   ?? '';
        $subjectShort = $facts['project_subject_short'] ?? '';
        $lokasi       = $facts['lokasi_detail']     ?? '';

        $subs = [];
        // Itergo template-specific literals (geoteknik template default vendor)
        if ($vendor) {
            $subs['PT. Itergo Buana Utama'] = $vendor;
            $subs['PT. ITERGO BUANA UTAMA'] = $vendor;
            // Purna Wahana template-specific literals (topografi template default vendor)
            $subs['PT. Purna Wahana Lestari'] = $vendor;
            $subs['PT. PURNA WAHANA LESTARI'] = $vendor;
            $subs['Purna Wahana Lestari']     = $vendor;
        }
        if ($vendorShort) {
            $subs['Itergo Buana Utama'] = $vendorShort;
        }
        if ($direktur && !str_contains($direktur, 'belum diisi')) {
            // Geoteknik template (Itergo direktur)
            $subs['AHMAD SAMSUDIN'] = $direktur;
            $subs['Ahmad Samsudin'] = $direktur;
            // Topografi template (Purna Wahana direktur)
            $subs['IIM MULNADI, ST'] = $direktur;
            $subs['Iim Mulnadi, ST'] = $direktur;
            $subs['IIM MULNADI']     = $direktur;
            $subs['Iim Mulnadi']     = $direktur;
        }
        if ($subject) {
            $subs['Sekolah Rakyat (SR) Ciwidey'] = $subject;
            $subs['Sekolah Rakyat Ciwidey']     = $subject;
            $subs['SR Ciwidey']                  = $subjectShort ?: $subject;
            // Topografi-specific subject phrases
            $subs['Kajian Topografi dan Pematangan Lahan Rencana Pembangunan Sekolah Rakyat'] = $subject;
            $subs['Kajian Topografi & Pematangan Lahan Sekolah Rakyat'] = $subject;
        }
        if ($lokasi) {
            $subs['Desa Lebakmuncang, Kecamatan Ciwidey, Kabupaten Bandung'] = $lokasi;
            $subs['Desa Lebakmuncang, Kecamatan Ciwidey, Kabupaten Bandung, Provinsi Jawa Barat'] = $lokasi;
        }
        // Sekolah Rakyat references in body text (often outside section body that composer replaces)
        if ($subject) {
            $subs['Sekolah Rakyat di Desa Lebakmuncang'] = $subject;
            $subs['pembangunan Sekolah Rakyat']         = 'pembangunan ' . $subject;
        }
        $subs['Kementerian Sosial'] = $facts['pemberi_kerja']       ?? 'Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung';
        $subs['Kemensos']           = $facts['pemberi_kerja_short'] ?? 'DPUTR';

        // Filter: keep only subs where replacement is different from original
        $subs = array_filter($subs, fn($v, $k) => !empty($v) && $v !== $k, ARRAY_FILTER_USE_BOTH);
        return $subs;
    }

    /**
     * Section plan per jenis × tipe — sesuai Playbook §5 Decision Matrix.
     * Key = section name; Value = strategy.
     */
    private function getSectionPlan(string $jenis, string $tipe): array
    {
        // Universal base
        $base = [
            'kata_pengantar'   => 'compose',
            'latar_belakang'   => 'compose',
            'maksud_tujuan'    => 'compose',
            'lokasi_pekerjaan' => 'compose',
            'ruang_lingkup'    => 'compose',
            'dasar_hukum'      => 'hybrid',
        ];

        // Per-jenis additions
        $jenisAdd = match ($jenis) {
            'geoteknik' => [
                'metodologi_pekerjaan' => 'reuse',
                'metode_analisis'      => 'reuse',
                'teori_dasar'          => 'reuse',
                'longsoran'            => 'reuse',
                'investigasi_tanah'    => 'reuse',
            ],
            'topografi' => [
                'gambaran_umum_lokasi' => 'compose',
                'metodologi_pemetaan'  => 'reuse',
            ],
            'jalan' => [
                'gambaran_umum_lokasi' => 'compose',
                'metodologi_pelaksanaan' => 'reuse',
                'personil_standard'    => 'reuse',
            ],
            default => [],
        };

        // Akhir-only additions
        $akhirAdd = [];
        if ($tipe === 'akhir') {
            $akhirAdd = [
                'hasil_analisis' => 'compose',
                'kesimpulan'     => 'compose',
                'rekomendasi'    => 'compose',
            ];
        }

        return array_merge($base, $jenisAdd, $akhirAdd);
    }

    // -------------------- SECTION HANDLERS --------------------

    private function sectionReuse(string $jenis, string $section): ?string
    {
        return $this->sections->get($jenis, $section);
    }

    private function sectionHybrid(string $jenis, string $section, Pekerjaan $pekerjaan): ?string
    {
        // For now: try {section}_preset.md or {section}.md
        $content = $this->sections->get($jenis, $section . '_preset') ?? $this->sections->get($jenis, $section);
        if ($content === null) {
            // Compose as fallback
            return $this->sectionCompose($jenis, $section, $pekerjaan, $this->sections->buildFactSlots($pekerjaan));
        }
        return $this->sections->inject($content, $pekerjaan);
    }

    private function sectionCompose(
        string $jenis,
        string $section,
        Pekerjaan $pekerjaan,
        array $facts,
        bool $retry = false,
        array $prevIssues = []
    ): string {
        $sectionPrompt = $this->getSectionPrompt($section, $jenis);
        $factsBlock = $this->formatFactsBlock($facts);
        $lexicon = $this->getVocabLexicon($jenis);
        $antiLeak = $this->getAntiLeakConstraints($pekerjaan, $facts);

        $userPrompt = "{$sectionPrompt}\n\n"
            . "## Project Facts\n{$factsBlock}\n\n"
            . "## Vocab Lexicon ({$jenis})\n{$lexicon}\n\n"
            . "## Anti-leak Constraints\n{$antiLeak}\n";

        if ($retry && !empty($prevIssues)) {
            $userPrompt .= "\n## RETRY NOTE\nVersi sebelumnya gagal quality gate:\n- " . implode("\n- ", $prevIssues)
                . "\nPerbaiki masalah di atas. JANGAN ulangi.\n";
        }

        $messages = [
            ['role' => 'system', 'content' => 'Lo adalah konsultan teknis senior yang menulis laporan untuk Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung. Tulis dalam Bahasa Indonesia formal pemerintahan dengan passive voice dominan dan kalimat majemuk panjang 25-50 kata. Output HANYA konten section tanpa heading title dan tanpa markdown formatting (plain paragraf).'],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        return $this->callOpenAI($messages);
    }

    /** Section composition pattern dari Playbook §2.x */
    private function getSectionPrompt(string $section, string $jenis): string
    {
        return match ($section) {
            'kata_pengantar' => <<<EOT
Tulis KATA PENGANTAR laporan dengan 4 paragraf, ~150 kata total, mengikuti arc:

P1: Religious greeting + vendor + nama_pekerjaan + lokasi_short. Diawali:
    "Puji dan syukur kami panjatkan ke hadirat Tuhan Yang Maha Esa, karena
     atas rahmat dan karunia-Nya, {vendor} sebagai penyedia jasa dapat
     melaksanakan tugas dan kewajibannya pada pekerjaan {nama_pekerjaan},
     {lokasi_short}, sesuai dengan KAK, maka dari itu {vendor} menyiapkan
     {tipe_laporan} ini."

P2: Tujuan dokumen + ringkasan isi (3-5 topik yang dibahas dalam laporan)

P3: Harapan/aspirasi dokumen ini akan menjadi dasar/acuan teknis untuk
    pelaksanaan pekerjaan

P4: Penutup terima kasih: "Akhir kata, kami mengucapkan terima kasih kepada
    seluruh pihak yang telah memberikan dukungan, kerja sama, dan kepercayaan
    dalam pelaksanaan pekerjaan ini."

Setelah P4, tambahkan signature block. PISAHKAN tiap baris dengan baris kosong
supaya rendering DOCX benar (tiap baris jadi paragraf terpisah):

Tim Penyusun

{vendor}


{direktur_nama}

{direktur_jabatan}
EOT,

            'latar_belakang' => <<<EOT
Tulis LATAR BELAKANG (sub-bab I.1) dengan 4-6 paragraf, mengikuti arc:

P1: Macro context tentang sektor/subjek (e.g. infrastruktur sipil,
    pendidikan, transportasi) — 3 kalimat
P2: Konteks regional/Kabupaten Bandung + lokasi spesifik — 3 kalimat
P3: Problem statement teknis spesifik (kenapa kajian ini perlu) — 3 kalimat
P4: Solution frame — peran kajian {nama_pekerjaan} dalam menjawab problem — 3 kalimat
P5 (opsional): Closing aspiratif — 2 kalimat

Style: kalimat majemuk panjang 30-50 kata, passive voice, banyak konjungsi
(sehingga, serta, oleh karena itu, berdasarkan, yang).
EOT,

            'maksud_tujuan' => <<<EOT
Tulis MAKSUD DAN TUJUAN (sub-bab I.2) dengan format EXACT berikut (pakai
double newline persis sesuai contoh — TIAP bullet harus di baris baru tersendiri):

Maksud dari kegiatan ini adalah {verb infinitif} {aktivitas-utama} pada {lokasi}.

Adapun tujuan dari kegiatan ini adalah sebagai berikut:

- Memperoleh {data-yang-dihasilkan} secara akurat dan komprehensif.
- Mengetahui {aspek-yang-dianalisis} pada lokasi pekerjaan.
- Menyusun {output-perencanaan} yang sesuai dengan kebutuhan.
- Menghitung {kuantitas-target} berdasarkan hasil pengujian.
- Mengevaluasi {parameter-target} terhadap standar yang berlaku.
- Memberikan rekomendasi teknis sebagai dasar perencanaan/pelaksanaan lanjutan.

PENTING: setiap bullet "- " HARUS dimulai pada baris baru tersendiri (newline
sebelumnya), JANGAN inline di satu baris.
EOT,

            'lokasi_pekerjaan' => <<<EOT
Tulis LOKASI PEKERJAAN (sub-bab I.3) dengan 1-2 paragraf:

P1: "Lokasi pekerjaan {nama_pekerjaan_short} berada di {lokasi_detail}.
    {Aksesibilitas singkat — bisa lewat jalan kabupaten/desa}."

P2 (opsional): "Secara administratif, wilayah ini merupakan bagian dari
              kawasan {kawasan-deskripsi}. {Karakteristik topografi singkat}."

Akhiri dengan: "Berikut ditampilkan kondisi lapangan pada lokasi pekerjaan."
JANGAN tulis caption gambar (Gambar 1.1 dll) — itu akan di-add oleh template.
EOT,

            'ruang_lingkup' => <<<EOT
Tulis RUANG LINGKUP PEKERJAAN dengan 1 baris intro + bulleted list 6-9 items:

Intro: "Ruang lingkup pekerjaan dalam {kajian/DED/penyusunan} {nama_pekerjaan_short} meliputi:"

Lalu bulleted list (prefix "- "), masing-masing item adalah noun phrase yang
diawali kata kerja substantif:
- Pelaksanaan {aktivitas-1} di lapangan.
- Pengumpulan dan pengolahan data {data-type}.
- Penyusunan {output-1}.
- Analisis {analisis-target}.
- Perencanaan {output-perencanaan}.
- Penyusunan laporan teknis {kajian-name}.

Sesuaikan items dengan jenis pekerjaan.
EOT,

            'gambaran_umum_lokasi' => <<<EOT
Tulis GAMBARAN UMUM LOKASI (BAB II) dengan 5 sub-section:

II.1 Letak Geografis dan Administratif (2 paragraf): alamat administratif
     lengkap + ringkasan koordinat/luas. Tulis "II.1 Letak Geografis dan
     Administratif" sebagai sub-heading.

II.2 Kondisi Fisik dan Topografi Wilayah (1-2 paragraf): deskripsi terrain,
     vegetasi, penggunaan lahan. Tulis "II.2 Kondisi Fisik dan Topografi
     Wilayah" sebagai sub-heading.

II.3 Penggunaan Lahan Existing (1 paragraf): lahan terbangun vs non-terbangun.

II.4 Kondisi Hidrologi dan Drainase Alami (1-2 paragraf): sistem drainase
     alami, alur aliran air, potensi genangan saat hujan.

II.5 Aksesibilitas dan Infrastruktur Sekitar (1 paragraf): akses jalan,
     infrastruktur pendukung (listrik, permukiman).

Format: setiap sub-section diawali oleh heading-nya (e.g. "II.1 Letak
Geografis dan Administratif") dalam baris terpisah, lalu paragraf.
EOT,

            'hasil_analisis' => <<<EOT
Tulis HASIL SURVEI DAN ANALISIS (BAB V/IV) — section ini bersifat
PLACEHOLDER karena data hasil survey aktual belum dimasukkan ke sistem.

Tulis 2-3 paragraf yang:
P1: Menjelaskan bahwa hasil survei lapangan sedang dalam proses pengumpulan
    dan pengolahan, mencakup {aktivitas-survei-utama untuk jenis ini}.
P2: Menjabarkan struktur analisis yang akan dilakukan (parameter teknis
    yang akan dianalisis: SF untuk geoteknik / cut-fill volume untuk
    topografi / LHR & perkerasan untuk jalan).
P3: Menyatakan bahwa hasil detail akan dilengkapi pada laporan akhir
    setelah seluruh data survei terkumpul dan diolah.

Tutup dengan: "Detail hasil analisis akan disajikan dalam bab-bab berikutnya
pada Laporan Akhir."
EOT,

            'kesimpulan' => <<<EOT
Tulis KESIMPULAN (sub-bab terakhir) dengan format:

Intro: "Berdasarkan hasil {analisis/kajian/pemetaan} {topik-utama} yang
       dilakukan pada {lokasi} dengan luas area {luas_lahan}, dapat
       disimpulkan sebagai berikut:"

Bulleted list 5-7 findings (prefix "- "), setiap bullet WAJIB mengandung
angka konkret:
- {Finding 1 dengan SF/volume/persentase}
- {Finding 2 dengan reference standar yang dipenuhi}
- ...

Kalau data hasil aktual belum ada, gunakan placeholder angka yang masuk akal
sesuai jenis pekerjaan (e.g. SF target ≥ 1,5; cut-fill seimbang ±5%;
LHR existing X kend/hari proyeksi Y; perkerasan tebal Z cm).
EOT,

            'rekomendasi' => <<<EOT
Tulis REKOMENDASI (atau SARAN) dengan format:

Intro: "Berdasarkan kesimpulan tersebut, maka rekomendasi teknis yang dapat
       diberikan adalah sebagai berikut:"

Bulleted list 4-7 items (prefix "- "), setiap item = imperative recommendation
+ technical justification. Verba: disarankan, wajib, perlu, sebaiknya, harus,
direkomendasikan.

Cakupan: konstruksi → pengawasan → pemeliharaan → kajian lanjutan.
EOT,

            default => "Tulis section '{$section}' dengan style Indonesia formal pemerintahan, passive voice, kalimat majemuk 25-45 kata. Output plain paragraf tanpa markdown.",
        };
    }

    /** Format facts block utk prompt. */
    private function formatFactsBlock(array $facts): string
    {
        $lines = [];
        foreach ($facts as $k => $v) {
            $lines[] = "- {$k}: {$v}";
        }
        return implode("\n", $lines);
    }

    /** Vocab lexicon per jenis dari Playbook §3 */
    private function getVocabLexicon(string $jenis): string
    {
        return match ($jenis) {
            'geoteknik' => "Software: PLAXIS 2D, finite element method, Mohr-Coulomb. "
                . "Parameter: kohesi (c), sudut geser (ɸ), Safety Factor (SF). "
                . "Pengujian: hand boring, sondir (CPT), UDS. "
                . "Perkuatan: DPT (Dinding Penahan Tanah), bronjong, cerucuk, strauss pile, bored pile. "
                . "Standar: SNI 8460:2017, SNI 7749.",
            'topografi' => "Alat: GNSS, Total Station, GPS Geodetik. "
                . "Datum: UTM (Universal Transverse Mercator). "
                . "Output: peta topografi, peta kontur, DEM, SIG. "
                . "Konsep: cut and fill, grading plan, terasering, elevasi rencana. "
                . "Kontrol: Bench Mark (BM), titik kontrol, jaring kontrol.",
            'jalan' => "Standar: MDPJ Bina Marga Revisi 2017, Spesifikasi Umum Bina Marga 2018, PKJI. "
                . "Komponen: perkerasan lentur, perkerasan kaku, geometrik jalan, drainase jalan. "
                . "Lalu lintas: LHR (Lalu Lintas Harian Rata-rata), volume lalu lintas, kapasitas jalan. "
                . "Output: DED (Detail Engineering Design), gambar rencana, RAB. "
                . "Personil: Team Leader, Asisten Tenaga Ahli Geodesi, Asisten Tenaga Ahli Teknik Jalan, Surveyor.",
            default => "Bahasa Indonesia formal pemerintahan/teknis konstruksi.",
        };
    }

    /** Anti-leak constraints. */
    private function getAntiLeakConstraints(Pekerjaan $pekerjaan, array $facts): string
    {
        $forbiddenNames = ['Sekolah Rakyat', 'Soreang', 'Itergo', 'Adhi Citrabhumi', 'Purna Wahana', 'Lebakmuncang', 'Kemensos', 'Ciwidey'];
        // Allow vendor's own name if it contains forbidden token
        $vendor = $facts['vendor'] ?? '';
        $allowed = array_filter($forbiddenNames, fn($n) => str_contains($vendor, $n));
        $blocked = array_diff($forbiddenNames, $allowed);

        $rules = [
            "JANGAN sebut: " . implode(', ', $blocked) . " (kecuali itu memang nama vendor/lokasi proyek ini).",
            "JANGAN gunakan first-person 'saya', 'anda', 'kalian'.",
            "JANGAN gunakan bahasa gaul / informal.",
            "JANGAN biarkan placeholder {xxx} muncul di output.",
            "Vendor proyek ini: {$vendor}. JANGAN sebut vendor lain.",
            "Nama pekerjaan: " . ($facts['nama_pekerjaan'] ?? '') . ". JANGAN sebut proyek lain.",
        ];
        return implode("\n", $rules);
    }

    // -------------------- OPENAI CALL --------------------

    private function callOpenAI(array $messages): string
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout(180)
                    ->connectTimeout(30)
                    ->retry(0)
                    ->withToken($this->apiKey)
                    ->withHeaders(['User-Agent' => 'Karta-Composer/1.0'])
                    ->post($this->apiUrl, [
                        'model'      => $this->model,
                        'max_tokens' => 1400,
                        'temperature' => 0.4,
                        'messages'   => $messages,
                    ]);

                if (!$response->successful()) {
                    $error = $response->json('error.message', $response->body());
                    throw new \RuntimeException("OpenAI API: {$error}");
                }

                $content = $response->json('choices.0.message.content', '');
                if (!is_string($content) || strlen(trim($content)) < 30) {
                    throw new \RuntimeException("OpenAI returned empty/short content");
                }
                return trim($content);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                if ($attempt < 3) {
                    sleep($attempt * 2);
                    continue;
                }
                throw new \RuntimeException("OpenAI connection fail after 3 attempts: " . $e->getMessage());
            }
        }
        throw new \RuntimeException('Unknown error in callOpenAI');
    }

    // -------------------- DOCX ASSEMBLY --------------------

    /**
     * Assemble composed sections jadi DOCX file.
     * Layout: cover → kata pengantar → daftar isi (stub) → BAB I → BAB II → BAB III.
     */
    private function assembleDocx(Pekerjaan $pekerjaan, string $tipe, string $jenis, array $sections, array $facts): array
    {
        $php = new PhpWord();
        $section = $php->addSection([
            'marginTop'    => Converter::cmToTwip(2.5),
            'marginBottom' => Converter::cmToTwip(2.5),
            'marginLeft'   => Converter::cmToTwip(3),
            'marginRight'  => Converter::cmToTwip(2.5),
        ]);

        $titleStyle = ['size' => 24, 'bold' => true];
        $h1Style    = ['size' => 16, 'bold' => true];
        $h2Style    = ['size' => 13, 'bold' => true];
        $bodyStyle  = ['size' => 11];
        $center     = ['alignment' => 'center'];
        $justify    = ['alignment' => 'both', 'lineHeight' => 1.15, 'spaceAfter' => 120];

        // === COVER ===
        $section->addText(strtoupper($facts['tipe_laporan']), $titleStyle, $center);
        $section->addTextBreak(2);
        $section->addText($facts['nama_pekerjaan'], ['size' => 18, 'bold' => true], $center);
        $section->addText($facts['lokasi_detail'], ['size' => 12], $center);
        $section->addTextBreak(6);
        $section->addText('Disusun oleh:', ['size' => 12], $center);
        $section->addText($facts['vendor'], ['size' => 14, 'bold' => true], $center);
        $section->addTextBreak(2);
        $section->addText('Pemberi Kerja:', ['size' => 11], $center);
        $section->addText($facts['pemberi_kerja'], ['size' => 12, 'bold' => true], $center);
        $section->addTextBreak(2);
        $section->addText('Tahun Anggaran ' . $facts['tahun'], ['size' => 12], $center);
        $section->addPageBreak();

        // === KATA PENGANTAR ===
        if (isset($sections['kata_pengantar'])) {
            $section->addText('KATA PENGANTAR', $h1Style);
            $section->addTextBreak(1);
            $this->addParagraphs($section, $sections['kata_pengantar'], $bodyStyle, $justify);
            $section->addPageBreak();
        }

        // === DAFTAR ISI (stub) ===
        $section->addText('DAFTAR ISI', $h1Style);
        $section->addTextBreak(1);
        $section->addText('[Daftar isi akan di-generate otomatis oleh Microsoft Word melalui menu References → Table of Contents.]', $bodyStyle);
        $section->addPageBreak();

        // === BAB I PENDAHULUAN ===
        $section->addText('BAB I PENDAHULUAN', $h1Style);
        $section->addTextBreak(1);

        $babI = [
            'I.1 Latar Belakang'        => $sections['latar_belakang']    ?? null,
            'I.2 Maksud dan Tujuan'     => $sections['maksud_tujuan']     ?? null,
            'I.3 Lokasi Pekerjaan'      => $sections['lokasi_pekerjaan']  ?? null,
            'I.4 Ruang Lingkup Pekerjaan' => $sections['ruang_lingkup']   ?? null,
            'I.5 Dasar Hukum'           => $sections['dasar_hukum']       ?? null,
        ];
        foreach ($babI as $heading => $content) {
            if ($content === null) continue;
            $section->addText($heading, $h2Style);
            $section->addTextBreak(1);
            $this->addParagraphs($section, $content, $bodyStyle, $justify);
            $section->addTextBreak(1);
        }

        // === BAB II ===
        $section->addPageBreak();
        $babIITitle = match ($jenis) {
            'geoteknik' => 'BAB II METODOLOGI PEKERJAAN',
            'topografi' => 'BAB II GAMBARAN UMUM LOKASI',
            'jalan'     => 'BAB II GAMBARAN UMUM LOKASI PEKERJAAN',
            default     => 'BAB II METODOLOGI',
        };
        $section->addText($babIITitle, $h1Style);
        $section->addTextBreak(1);

        $babII = match ($jenis) {
            'geoteknik' => [
                'II.1 Metodologi Pekerjaan' => $sections['metodologi_pekerjaan'] ?? null,
            ],
            'topografi' => [
                '' => $sections['gambaran_umum_lokasi'] ?? null,
            ],
            'jalan' => [
                '' => $sections['gambaran_umum_lokasi'] ?? null,
            ],
            default => [],
        };
        foreach ($babII as $heading => $content) {
            if ($content === null) continue;
            if ($heading) {
                $section->addText($heading, $h2Style);
                $section->addTextBreak(1);
            }
            $this->addParagraphs($section, $content, $bodyStyle, $justify);
            $section->addTextBreak(1);
        }

        // === BAB III (per jenis) ===
        $section->addPageBreak();
        $babIIITitle = match ($jenis) {
            'geoteknik' => 'BAB III DASAR PERENCANAAN',
            'topografi' => 'BAB III METODOLOGI PEMETAAN TOPOGRAFI',
            'jalan'     => 'BAB III METODOLOGI PELAKSANAAN',
            default     => 'BAB III METODOLOGI',
        };
        $section->addText($babIIITitle, $h1Style);
        $section->addTextBreak(1);

        $babIII = match ($jenis) {
            'geoteknik' => [
                'III.1 Metode Analisis' => $sections['metode_analisis'] ?? null,
                'III.2 Teori Dasar'     => $sections['teori_dasar'] ?? null,
                'III.3 Longsoran dan Pergerakan Tanah' => $sections['longsoran'] ?? null,
                'III.4 Investigasi Tanah' => $sections['investigasi_tanah'] ?? null,
            ],
            'topografi' => [
                '' => $sections['metodologi_pemetaan'] ?? null,
            ],
            'jalan' => [
                '' => $sections['metodologi_pelaksanaan'] ?? null,
                'III.x Organisasi dan Kebutuhan Personil' => $sections['personil_standard'] ?? null,
            ],
            default => [],
        };
        foreach ($babIII as $heading => $content) {
            if ($content === null) continue;
            if ($heading) {
                $section->addText($heading, $h2Style);
                $section->addTextBreak(1);
            }
            $this->addMarkdownParagraphs($section, $content, $bodyStyle, $justify);
            $section->addTextBreak(1);
        }

        // === Akhir-only: BAB IV-VI ===
        if ($tipe === 'akhir') {
            $section->addPageBreak();
            $section->addText('BAB IV HASIL SURVEI DAN ANALISIS', $h1Style);
            $section->addTextBreak(1);
            if (isset($sections['hasil_analisis'])) {
                $this->addParagraphs($section, $sections['hasil_analisis'], $bodyStyle, $justify);
            }

            $section->addPageBreak();
            $section->addText('BAB V KESIMPULAN DAN SARAN', $h1Style);
            $section->addTextBreak(1);
            if (isset($sections['kesimpulan'])) {
                $section->addText('V.1 Kesimpulan', $h2Style);
                $section->addTextBreak(1);
                $this->addParagraphs($section, $sections['kesimpulan'], $bodyStyle, $justify);
                $section->addTextBreak(1);
            }
            if (isset($sections['rekomendasi'])) {
                $section->addText('V.2 Rekomendasi', $h2Style);
                $section->addTextBreak(1);
                $this->addParagraphs($section, $sections['rekomendasi'], $bodyStyle, $justify);
            }
        }

        // Save
        $slug = \Illuminate\Support\Str::slug($pekerjaan->nama_pekerjaan);
        $stamp = now()->format('YmdHis');
        $filename = "laporan_{$tipe}_{$jenis}_composed_{$stamp}.docx";
        $relativePath = "dokumen/generated/{$pekerjaan->id}/{$filename}";

        $absPath = Storage::disk('local')->path($relativePath);
        if (!is_dir(dirname($absPath))) mkdir(dirname($absPath), 0755, true);
        \PhpOffice\PhpWord\IOFactory::createWriter($php, 'Word2007')->save($absPath);

        return [
            'absPath' => $absPath,
            'relativePath' => $relativePath,
            'filename' => $filename,
            'size' => (int) filesize($absPath),
        ];
    }

    /**
     * Add paragraf-paragraf dari plain text (split on \n\n or single \n for bulleted).
     * Handle bulleted list (line starting with "- " or "• ") sebagai PhpWord list items.
     */
    private function addParagraphs($section, string $content, array $style, array $paraStyle): void
    {
        // Defensive: split inline " - " bullets (LLM sometimes outputs "intro: - X - Y - Z" on one line)
        $content = preg_replace('/:\s+-\s+/u', ":\n- ", $content);
        $content = preg_replace('/\s+-\s+(?=[A-Z])/u', "\n- ", $content);
        $lines = explode("\n", trim($content));
        $buffer = [];
        $flushPara = function () use (&$buffer, $section, $style, $paraStyle) {
            if (!empty($buffer)) {
                $section->addText(implode(' ', $buffer), $style, $paraStyle);
                $buffer = [];
            }
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $flushPara();
                continue;
            }
            // Bulleted item
            if (preg_match('/^(?:[-•*]|\d+\.)\s+(.+)/u', $trim, $m)) {
                $flushPara();
                $section->addListItem($m[1], 0, $style, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED], $paraStyle);
                continue;
            }
            $buffer[] = $trim;
        }
        $flushPara();
    }

    /**
     * Like addParagraphs but handles markdown headings ## and ### as sub-section
     * (so cached sections with ## headings are rendered properly).
     */
    private function addMarkdownParagraphs($section, string $content, array $style, array $paraStyle): void
    {
        $lines = explode("\n", trim($content));
        $h2 = ['size' => 12, 'bold' => true];
        $h3 = ['size' => 11, 'bold' => true, 'italic' => true];
        $buffer = [];
        $flushPara = function () use (&$buffer, $section, $style, $paraStyle) {
            if (!empty($buffer)) {
                $section->addText(implode(' ', $buffer), $style, $paraStyle);
                $buffer = [];
            }
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $flushPara();
                continue;
            }
            if (str_starts_with($trim, '### ')) {
                $flushPara();
                $section->addText(substr($trim, 4), $h3);
                continue;
            }
            if (str_starts_with($trim, '## ')) {
                $flushPara();
                $section->addText(substr($trim, 3), $h2);
                continue;
            }
            if (preg_match('/^(?:[-•*]|\d+\.)\s+(.+)/u', $trim, $m)) {
                $flushPara();
                $section->addListItem($m[1], 0, $style, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED], $paraStyle);
                continue;
            }
            $buffer[] = $trim;
        }
        $flushPara();
    }
}
