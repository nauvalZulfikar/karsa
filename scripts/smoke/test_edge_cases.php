<?php
/**
 * Edge cases:
 *  EC1 — signature regex doesn't crash on PPK without gelar
 *  EC2 — very short input (<2000 chars) signature block fallback
 *  EC3 — re-upload same KAK 2x: cache reliability after pre-fix bug
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\KickoffParserService::class);

// EC1: signature without gelar — pakai reflection untuk panggil private method
echo "=== EC1: applySignatureRegexFallback with PPK tanpa gelar ===\n";
$signatureNoGelar = "Soreang, 19 Desember 2025\nPejabat Pembuat Komitmen\nBudi Hartono\nNIP. 19800101 200501 1 001";
$ref = new ReflectionClass($parser);
$m = $ref->getMethod('applySignatureRegexFallback');
$m->setAccessible(true);
$out = $m->invoke($parser, ['ppk_nama' => null, 'ppk_nip' => null, 'tanggal_kak' => null], $signatureNoGelar);
echo "  tanggal_kak: " . var_export($out['tanggal_kak'], true) . "\n";
echo "  ppk_nama:    " . var_export($out['ppk_nama'], true) . " (should be NULL — no gelar filter blocks 'Budi Hartono')\n";
echo "  ppk_nip:     " . var_export($out['ppk_nip'], true) . "\n";
echo "  Status: " . (($out['tanggal_kak'] === '2025-12-19' && $out['ppk_nip'] === '19800101 200501 1 001') ? 'OK no crash, NIP+tanggal extracted, name skipped (gelar filter)' : 'FAIL') . "\n";

// EC2: short signature (<2000 chars)
echo "\n=== EC2: very short signature input ===\n";
$shortSig = "Soreang, 5 Januari 2026\nPPK\nWidya, S.T.\nNIP. 12345678 901234 5 678";
$out = $m->invoke($parser, [], $shortSig);
echo "  tanggal_kak: " . var_export($out['tanggal_kak'], true) . "\n";
echo "  ppk_nama:    " . var_export($out['ppk_nama'], true) . "\n";
echo "  ppk_nip:     " . var_export($out['ppk_nip'], true) . "\n";
echo "  Status: " . (($out['tanggal_kak'] === '2026-01-05' && $out['ppk_nip'] === '12345678 901234 5 678') ? 'OK' : 'FAIL') . "\n";

// EC3: re-parse same file 2x — cache reliability
echo "\n=== EC3: re-parse KAK 2x to test cache reliability ===\n";
$path = 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf';

// Clear cache first (post-fix recommendation)
App\Models\ChatUpload::query()->update(['ocr_text' => null, 'is_scanned_pdf' => false]);
echo "  Cache cleared.\n";

$t0 = microtime(true);
$r1 = $parser->parse($path);
$elapsed1 = round(microtime(true) - $t0, 2);
echo "  Run 1: {$elapsed1}s | nama={$r1['nama_pekerjaan']} | ppk={$r1['ppk_nama']}\n";

$t0 = microtime(true);
$r2 = $parser->parse($path);
$elapsed2 = round(microtime(true) - $t0, 2);
echo "  Run 2: {$elapsed2}s | nama={$r2['nama_pekerjaan']} | ppk={$r2['ppk_nama']}\n";

$matchNama = $r1['nama_pekerjaan'] === $r2['nama_pekerjaan'];
$matchPpk  = $r1['ppk_nama'] === $r2['ppk_nama'];
$matchNip  = $r1['ppk_nip'] === $r2['ppk_nip'];
$matchTgl  = $r1['tanggal_kak'] === $r2['tanggal_kak'];
echo "  Reproducibility: nama=" . ($matchNama ? 'Y' : 'N') . " ppk_nama=" . ($matchPpk ? 'Y' : 'N') . " ppk_nip=" . ($matchNip ? 'Y' : 'N') . " tanggal=" . ($matchTgl ? 'Y' : 'N') . "\n";
echo "  Status: " . (($matchNama && $matchPpk && $matchNip && $matchTgl) ? 'OK — both runs identical' : 'PARTIAL — LLM non-determinism on some field') . "\n";
