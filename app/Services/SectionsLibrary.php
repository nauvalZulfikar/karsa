<?php

namespace App\Services;

use App\Models\Pekerjaan;

/**
 * Loader untuk cached generic sections di
 * storage/laporan_templates/sections/{jenis}/{section_name}.md.
 *
 * Section ini di-REUSE verbatim (atau lightly tweaked) — TIDAK di-generate via LLM.
 * Cocok untuk konten yang sama di semua proyek: teori dasar, metodologi standard,
 * dasar hukum preset.
 *
 * Dipakai oleh LaporanComposerService.
 */
class SectionsLibrary
{
    public function __construct(
        private string $sectionsDir = '',
    ) {
        $this->sectionsDir = $sectionsDir ?: storage_path('laporan_templates/sections');
    }

    /**
     * Load section markdown. Return null kalau gak ada.
     *
     * Contoh: get('geoteknik', 'teori_dasar') → string content of
     * storage/laporan_templates/sections/geoteknik/teori_dasar.md
     */
    public function get(string $jenis, string $sectionName): ?string
    {
        $path = $this->sectionsDir . "/{$jenis}/{$sectionName}.md";
        if (!file_exists($path)) return null;
        return $this->stripMarkdown((string) file_get_contents($path));
    }

    /**
     * Strip markdown markers for clean DOCX rendering:
     *   - Remove top-level "## SectionName" headings (handled by caller's own headings)
     *   - Strip "**bold**" → "bold"
     *   - Strip "_italic_" → "italic"
     * Keep "### sub-heading" intact (still rendered as bold by addMarkdownParagraphs).
     */
    private function stripMarkdown(string $content): string
    {
        // Strip "## ..." top-level headings (we render our own headings in composer)
        $content = preg_replace('/^##\s+.*$/m', '', $content);
        // Strip ** bold ** markers
        $content = preg_replace('/\*\*(.+?)\*\*/u', '$1', $content);
        // Strip _italic_ markers
        $content = preg_replace('/(?<!\w)_(.+?)_(?!\w)/u', '$1', $content);
        // Collapse 3+ blank lines into 2
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        return trim($content);
    }

    /**
     * List semua section yang tersedia untuk jenis tertentu.
     */
    public function listSections(string $jenis): array
    {
        $dir = $this->sectionsDir . "/{$jenis}";
        if (!is_dir($dir)) return [];
        $out = [];
        foreach (glob($dir . '/*.md') as $f) {
            $out[] = basename($f, '.md');
        }
        return $out;
    }

    /**
     * Inject project facts ke section content via simple token replace.
     * Token format: `{nama_token}` (curly braces).
     * Token yg disupport sama dengan LaporanTemplateService::tokenValues() minus
     * the leading colon.
     */
    public function inject(string $content, Pekerjaan $pekerjaan): string
    {
        $facts = $this->buildFactSlots($pekerjaan);
        foreach ($facts as $token => $value) {
            $content = str_replace('{' . $token . '}', (string) $value, $content);
        }
        return $content;
    }

    /**
     * Build complete fact-slot dictionary dari pekerjaan record.
     * Sumber: docs/LAPORAN_WRITING_PLAYBOOK.md §9.
     */
    public function buildFactSlots(Pekerjaan $pekerjaan): array
    {
        $vendor       = $pekerjaan->perusahaan?->nama ?? '';
        $vendorShort  = $pekerjaan->perusahaan?->singkatan ?: $this->stripCompanyPrefix($vendor);
        $direktur     = $pekerjaan->perusahaan?->pic_nama
            ?: '(Nama direktur belum diisi di data Perusahaan)';
        $nama         = $pekerjaan->nama_pekerjaan ?? '';
        $subject      = preg_replace('/^(kajian|studi|analisis|perencanaan|ded|penyusunan)\s+/i', '', $nama);

        return [
            'vendor'              => $vendor,
            'vendor_short'        => $vendorShort,
            'direktur_nama'       => $direktur,
            'direktur_jabatan'    => 'Direktur',
            'nama_pekerjaan'      => $nama,
            'project_subject'     => $subject,
            'project_subject_short' => mb_substr($subject, 0, 40),
            'jenis_keyword'       => $this->detectJenis($pekerjaan),
            'tipe_laporan'        => 'Laporan Pendahuluan',
            'lokasi_detail'       => $this->resolveLokasi($pekerjaan),
            'lokasi_short'        => 'Kabupaten Bandung',
            'pemberi_kerja'       => 'Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung',
            'pemberi_kerja_short' => 'DPUTR Kabupaten Bandung',
            'tahun'               => (string) ($pekerjaan->tahun_anggaran ?? date('Y')),
            'no_spk'              => $pekerjaan->no_spk ?? '',
            'no_spmk'             => $pekerjaan->no_spmk ?? '',
            'tanggal_mulai'       => optional($pekerjaan->tanggal_mulai)->format('d F Y') ?? '',
            'tanggal_akhir'       => optional($pekerjaan->tanggal_akhir)->format('d F Y') ?? '',
            'nilai_kontrak_fmt'   => 'Rp ' . number_format((float) $pekerjaan->nilai_kontrak, 0, ',', '.'),
            'luas_lahan'          => '—',
            'koordinat_utm'       => '—',
        ];
    }

    /** Strip "PT./CV. " prefix utk versi pendek vendor. */
    private function stripCompanyPrefix(string $name): string
    {
        return trim(preg_replace('/^(PT\.?|CV\.?)\s+/i', '', $name));
    }

    /** Resolve lokasi from pekerjaan.lokasi column (added 2026-05-20), fallback default. */
    private function resolveLokasi(Pekerjaan $pekerjaan): string
    {
        // Eloquent attribute access — works for fillable columns
        $lokasi = trim((string) ($pekerjaan->lokasi ?? ''));
        if ($lokasi !== '') return $lokasi;
        return 'Kabupaten Bandung, Provinsi Jawa Barat';
    }

    /** Detect jenis dari nama_pekerjaan + jenis_pekerjaan relation. */
    public function detectJenis(Pekerjaan $pekerjaan): string
    {
        // 1. jenis_pekerjaan kode/nama
        if ($pekerjaan->jenisPekerjaan?->kode) {
            return strtolower($pekerjaan->jenisPekerjaan->kode);
        }

        // 2. keyword match di nama_pekerjaan (extended dgn synonym map per Playbook §3)
        $nama = strtolower($pekerjaan->nama_pekerjaan ?? '');
        $synonyms = [
            'geoteknik' => ['geoteknik', 'stabilitas lereng', 'stabilisasi tanah', 'slope', 'longsor', 'dpt', 'dinding penahan'],
            'topografi' => ['topografi', 'pematangan lahan', 'cut and fill', 'cut & fill', 'terasering', 'grading'],
            'jalan'     => ['jalan', 'ded jalan', 'perkerasan', 'bina marga'],
            'drainase'  => ['drainase', 'saluran air', 'gorong'],
            'jembatan'  => ['jembatan', 'bridge'],
            'gedung'    => ['gedung', 'bangunan gedung', 'gedung negara'],
            'irigasi'   => ['irigasi', 'bendung', 'saluran irigasi'],
        ];
        foreach ($synonyms as $jenis => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($nama, $kw)) return $jenis;
            }
        }
        return 'lainnya';
    }
}
