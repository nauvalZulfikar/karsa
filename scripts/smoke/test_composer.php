<?php
// Test LaporanComposerService standalone against pekerjaan #18.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pekerjaan = App\Models\Pekerjaan::with(['perusahaan', 'jenisPekerjaan'])->find(18);
if (!$pekerjaan) {
    echo "Pekerjaan #18 not found. Create it first via smoke create script.\n";
    exit(1);
}
echo "Pekerjaan: #{$pekerjaan->id} | {$pekerjaan->nama_pekerjaan}\n";
echo "Vendor: " . ($pekerjaan->perusahaan?->nama ?? 'NULL') . "\n";
echo "Direktur (pic_nama): " . ($pekerjaan->perusahaan?->pic_nama ?? 'NULL') . "\n";

echo "\n=== Calling compose() ===\n";
$t0 = microtime(true);
try {
    $result = app(App\Services\LaporanComposerService::class)->compose($pekerjaan, 'pendahuluan');
    $elapsed = round(microtime(true) - $t0, 1);
    echo "✓ Composed in {$elapsed}s\n";
    echo "  Mode: {$result['mode']}\n";
    echo "  Jenis: {$result['jenis']}\n";
    echo "  Tipe: {$result['tipe']}\n";
    echo "  File: {$result['filename']}\n";
    echo "  Size: " . round($result['size_bytes']/1024, 1) . " KB\n";
    echo "  Sections: " . implode(', ', $result['sections']) . "\n";
    if (!empty($result['quality'])) {
        echo "  Quality reports:\n";
        foreach ($result['quality'] as $sec => $q) {
            echo "    - {$sec}: score={$q['score']}/10 (passed={$q['gates_passed']}/{$q['gates_total']})";
            if (!empty($q['issues'])) {
                echo " issues: " . implode(' | ', $q['issues']);
            }
            echo "\n";
        }
    }
    echo "\nOutput file: {$result['output_path']}\n";
} catch (\Throwable $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
