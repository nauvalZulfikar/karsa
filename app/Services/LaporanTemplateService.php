<?php

namespace App\Services;

use App\Models\Pekerjaan;
use Illuminate\Support\Str;

/**
 * Render laporan DOCX dari template per jenis_pekerjaan.
 *
 * Layout:
 *   storage/laporan_templates/
 *     {jenis}_{tipe}.docx                  — template DOCX original
 *     configs/{jenis}_{tipe}.json          — substitution map + resolvers
 *
 * Lookup order (untuk pekerjaan):
 *   1. by jenis_pekerjaan.kode (lowercase)
 *   2. by bidang.kode (geoteknik → 'geoteknik', drainase → 'drainase', dll)
 *   3. fallback 'generic'
 *
 * Kalau gak ada template match → return null, caller fallback ke generator basic.
 */
class LaporanTemplateService
{
    public function __construct(
        private string $templatesDir = '',
    ) {
        $this->templatesDir = $templatesDir ?: storage_path('laporan_templates');
    }

    /**
     * Render laporan utk pekerjaan + tipe (pendahuluan|akhir).
     * Return ['ok'=>true, 'path'=>..., 'filename'=>...] atau null kalau no template.
     *
     * @param string $tipe 'pendahuluan' | 'akhir'
     */
    public function render(Pekerjaan $pekerjaan, string $tipe = 'pendahuluan'): ?array
    {
        $jenis = $this->detectJenis($pekerjaan);
        if (!$jenis) return null;

        $configFile = $this->templatesDir . "/configs/{$jenis}_{$tipe}.json";
        $tmplFile   = $this->templatesDir . "/{$jenis}_{$tipe}.docx";

        if (!file_exists($configFile) || !file_exists($tmplFile)) {
            return null;
        }

        $config = json_decode(file_get_contents($configFile), true);
        if (!is_array($config) || empty($config['substitutions'])) {
            return null;
        }

        $subs = $this->resolveSubstitutions($config['substitutions'], $pekerjaan);
        $outPath = $this->makeOutputPath($pekerjaan->id, $tipe, $jenis);

        // Pass subs via temp JSON file (Windows shell-quoting double quote dalam JSON gak reliable)
        $subsFile = tempnam(sys_get_temp_dir(), 'karta_subs_') . '.json';
        file_put_contents($subsFile, json_encode($subs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $cmd = sprintf(
            '%s "%s" --template "%s" --output "%s" --subs-file "%s" 2>&1',
            config('services.python.bin', 'python3'),
            base_path('scripts/laporan_render.py'),
            $tmplFile,
            $outPath,
            $subsFile
        );

        exec($cmd, $out, $exitCode);
        $stdout = implode("\n", $out);
        @unlink($subsFile);

        if ($exitCode !== 0 || str_starts_with($stdout, 'ERR:')) {
            throw new \RuntimeException("Python renderer fail: {$stdout}");
        }

        return [
            'ok'           => true,
            'jenis'        => $jenis,
            'tipe'         => $tipe,
            'template'     => basename($tmplFile),
            'output_path'  => $outPath,
            'relative_path' => 'dokumen/generated/' . $pekerjaan->id . '/' . basename($outPath),
            'filename'     => basename($outPath),
            'size_bytes'   => filesize($outPath),
            'python_log'   => $stdout,
        ];
    }

    /** Detect jenis utk template lookup. */
    private function detectJenis(Pekerjaan $pekerjaan): ?string
    {
        // 1. dari jenis_pekerjaan relation
        if ($pekerjaan->jenisPekerjaan?->kode) {
            return strtolower($pekerjaan->jenisPekerjaan->kode);
        }
        if ($pekerjaan->jenisPekerjaan?->nama) {
            $slug = Str::slug($pekerjaan->jenisPekerjaan->nama);
            $first = explode('-', $slug)[0] ?? null;
            if ($first) return $first;
        }
        // 2. dari nama_pekerjaan (keyword match)
        $nama = strtolower($pekerjaan->nama_pekerjaan ?? '');
        $keywords = ['geoteknik', 'drainase', 'jalan', 'jembatan', 'gedung', 'irigasi', 'topografi'];
        foreach ($keywords as $kw) {
            if (str_contains($nama, $kw)) return $kw;
        }
        return null;
    }

    /** Resolve token values (`:vendor`, `:lokasi_detail`, dll) dari pekerjaan. */
    private function resolveSubstitutions(array $rawSubs, Pekerjaan $pekerjaan): array
    {
        $resolved = [];
        $tokenValues = $this->tokenValues($pekerjaan);
        foreach ($rawSubs as $literal => $tokenOrValue) {
            if (str_starts_with($literal, '_')) continue; // comment keys
            $value = is_string($tokenOrValue) && str_starts_with($tokenOrValue, ':')
                ? ($tokenValues[$tokenOrValue] ?? $tokenOrValue)
                : $tokenOrValue;
            // Skip kalau resolved value sama dengan literal (no-op) atau kosong
            if (empty($value) || $value === $literal) continue;
            $resolved[$literal] = $value;
        }
        return $resolved;
    }

    /** Build token-to-value map dari pekerjaan record. */
    private function tokenValues(Pekerjaan $pekerjaan): array
    {
        $vendor = $pekerjaan->perusahaan?->nama ?? '';
        // Smart project subject: bersihkan "Kajian/Studi/Analisis" prefix utk subject
        $subject = $pekerjaan->nama_pekerjaan ?? '';
        $subject = preg_replace('/^(kajian|studi|analisis|perencanaan|ded|penyusunan)\s+/i', '', $subject);
        return [
            ':vendor'                  => $vendor,
            ':vendor_short'            => $vendor,
            ':nama_pekerjaan'          => $pekerjaan->nama_pekerjaan ?? '',
            ':nama_pekerjaan_short'    => mb_substr($pekerjaan->nama_pekerjaan ?? '', 0, 50),
            ':project_subject'         => $subject,
            ':project_subject_short'   => mb_substr($subject, 0, 30),
            ':lokasi_detail'           => $pekerjaan->lokasi ?? 'Kabupaten Bandung, Jawa Barat',
            ':no_spk'                  => $pekerjaan->no_spk ?? '',
            ':tahun'                   => (string) ($pekerjaan->tahun_anggaran ?? date('Y')),
            ':pemberi_kerja'           => 'Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung',
            ':pemberi_kerja_short'     => 'DPUTR',
        ];
    }

    private function makeOutputPath(int $pekerjaanId, string $tipe, string $jenis): string
    {
        $dir = storage_path("app/private/dokumen/generated/{$pekerjaanId}");
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $stamp = now()->format('YmdHis');
        return $dir . "/laporan_{$tipe}_{$jenis}_template_{$stamp}.docx";
    }
}
