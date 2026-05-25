<?php
/**
 * Test KAK parser against documents that are NOT KAK — make sure parser either:
 *  (a) returns null/empty for KAK-specific fields (gracefully degrades), or
 *  (b) doesn't crash
 *
 * These are real-world documents user might mistakenly route to KAK parser.
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ROOT = 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/PRINT PT. ITERGO BUANA UTAMA GEOTEKNIK';
$docs = [
    'PQ'              => $ROOT . '/PQ - ITERGO BUANA UTAMA.pdf',
    'AdminTeknis'     => $ROOT . '/FILE DOKUMEN ADMINISTRASI DAN TEKNIS.pdf',
    'TA-Rian'         => $ROOT . '/TA/1. Rian Hendriawan, ST_Team Leader.pdf',
];

$parser = app(App\Services\KickoffParserService::class);

foreach ($docs as $name => $path) {
    echo "\n=== [$name] " . basename($path) . " ===\n";
    if (!file_exists($path)) {
        echo "  SKIP: file not found\n";
        continue;
    }
    echo "  size: " . round(filesize($path) / 1024, 1) . " KB\n";
    $t0 = microtime(true);
    try {
        $result = $parser->parse($path);
        $elapsed = round(microtime(true) - $t0, 2);
        echo "  elapsed: {$elapsed}s\n";
        // Show top-level fields (truncated)
        $brief = [];
        foreach (['nama_pekerjaan', 'lokasi_pekerjaan', 'nilai_pagu', 'durasi_hari', 'tanggal_kak', 'ppk_nama', 'ppk_nip', 'instansi', 'sumber_dana', 'kbli'] as $k) {
            $v = $result[$k] ?? null;
            if (is_string($v)) $v = mb_substr($v, 0, 60);
            $brief[$k] = $v;
        }
        echo "  brief:\n";
        foreach ($brief as $k => $v) {
            echo "    {$k}: " . (is_null($v) ? 'NULL' : json_encode($v)) . "\n";
        }
        echo "  tenaga_ahli_count: " . count($result['tenaga_ahli'] ?? []) . "\n";
        echo "  STATUS: ✓ no crash\n";
    } catch (\Throwable $e) {
        $elapsed = round(microtime(true) - $t0, 2);
        echo "  elapsed: {$elapsed}s\n";
        echo "  STATUS: ✗ EXCEPTION: " . $e->getMessage() . "\n";
    }
}
