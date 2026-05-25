<?php
/**
 * Smoke test: KickoffParserService dengan dokumen-dokumen di folder doc-geoteknik.
 * Usage:
 *   cd D:\Downloads\coding project\project_management\dputr-pm
 *   php scripts/smoke/test_kak_parser.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$folder = 'D:\\Downloads\\coding project\\project_management\\doc-geoteknik-stabilitas-tanah';

$files = [
    'KAK'         => $folder . '\\1. KAK Kajian Geoteknik Stabilitas Tanah.pdf',
    'SPK/SPMK'    => $folder . '\\Spk,Spmk,Ba PT Itergo - Kajian Geoteknik Stabilitas Tanah .pdf',
    'Negosiasi'   => $folder . '\\Lampiran Negosiasi Konsultan Konstruksi.pdf',
];

$parser = app(\App\Services\KickoffParserService::class);

foreach ($files as $label => $path) {
    echo "\n=== [$label] " . basename($path) . " ===\n";
    if (!file_exists($path)) {
        echo "FILE NOT FOUND\n";
        continue;
    }
    try {
        $t0 = microtime(true);
        $result = $parser->parse($path);
        $elapsed = round(microtime(true) - $t0, 2);
        echo "elapsed: {$elapsed}s\n";
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
