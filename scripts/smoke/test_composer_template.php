<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pekerjaan = App\Models\Pekerjaan::with(['perusahaan','jenisPekerjaan'])->find(19);
if (!$pekerjaan) { echo "Pekerjaan #19 not found\n"; exit(1); }

echo "Pekerjaan #{$pekerjaan->id}: {$pekerjaan->nama_pekerjaan}\n";
echo "Vendor: " . ($pekerjaan->perusahaan?->nama ?? '-') . "\n\n";

$t0 = microtime(true);
$result = app(App\Services\LaporanComposerService::class)->compose($pekerjaan, 'pendahuluan');
$elapsed = round(microtime(true) - $t0, 1);

echo "Composed in {$elapsed}s\n";
echo "Mode: {$result['mode']}\n";
echo "File: {$result['filename']}\n";
echo "Size: " . round($result['size_bytes']/1024, 1) . " KB\n";
echo "Sections: " . implode(', ', $result['sections']) . "\n";
echo "Path: {$result['output_path']}\n";
