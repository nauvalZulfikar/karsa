<?php
/**
 * Smoke test: generate Laporan Pendahuluan DOCX for last-created project.
 * Uses pekerjaan ID from tmp/smoke/last-success-pekerjaan-id.txt.
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\DocumentGeneratorService;
use App\Models\Pekerjaan;

$idFile = __DIR__ . '/../../tmp/smoke/last-success-pekerjaan-id.txt';
if (!file_exists($idFile)) {
    fwrite(STDERR, "✗ No previous smoke success. Run create_project_from_docs.php first.\n");
    exit(1);
}
$pid = (int) trim(file_get_contents($idFile));
if (!$pid) {
    fwrite(STDERR, "✗ Bad pekerjaan_id in $idFile\n");
    exit(1);
}

$pekerjaan = Pekerjaan::find($pid);
if (!$pekerjaan) {
    fwrite(STDERR, "✗ Pekerjaan #$pid not found in DB (was it deleted?)\n");
    exit(1);
}

echo "=== GENERATE LAPORAN PENDAHULUAN ===\n";
echo "Pekerjaan #$pid: {$pekerjaan->nama_pekerjaan}\n";
echo "Vendor: " . ($pekerjaan->perusahaan?->nama ?? '(none)') . "\n";
echo "No SPK: {$pekerjaan->no_spk}\n";
echo "Periode: {$pekerjaan->tanggal_mulai} → {$pekerjaan->tanggal_akhir}\n\n";

@set_time_limit(0);
$t0 = microtime(true);
try {
    $result = app(DocumentGeneratorService::class)->generateLaporanPendahuluan($pid, []);
    $dur = round(microtime(true) - $t0, 1);
    echo "✓ Generated in {$dur}s\n";
    echo "Result:\n";
    print_r($result);

    // Verify file actually exists and has size
    $path = $result['path'] ?? $result['file_path'] ?? $result['absolute_path'] ?? null;
    if ($path && file_exists($path)) {
        $size = round(filesize($path) / 1024, 1);
        echo "\n✅ FILE OK: $path ({$size} KB)\n";
        exit(0);
    } elseif ($path) {
        echo "\n⚠️  Path reported but file not found: $path\n";
        exit(1);
    } else {
        echo "\n⚠️  No path returned. Inspect result above.\n";
        exit(1);
    }
} catch (\Throwable $e) {
    $dur = round(microtime(true) - $t0, 1);
    fwrite(STDERR, "✗ EXCEPTION after {$dur}s: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
