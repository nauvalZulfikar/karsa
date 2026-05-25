<?php
/**
 * Strict validation of last-created pekerjaan from Playwright/CLI smoke test.
 * Exits 0 on full pass, 1 if any field mismatches expectation.
 */
require __DIR__ . '/../../vendor/autoload.php';
$a = require __DIR__ . '/../../bootstrap/app.php';
$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = \App\Models\Pekerjaan::where('id', '>', 6)->latest('id')->first();
if (!$p) {
    fwrite(STDERR, "✗ NO new pekerjaan beyond seeded #6\n");
    exit(1);
}

$termin = \DB::table('termin_pembayaran')->where('pekerjaan_id', $p->id)->count();
$milestone = \DB::table('milestone_pekerjaan')->where('pekerjaan_id', $p->id)->count();
$vendor = $p->perusahaan?->nama ?? '(none)';

echo "=== Pekerjaan #{$p->id} ===\n";
echo "  nama:       {$p->nama_pekerjaan}\n";
echo "  no_spk:     {$p->no_spk}\n";
echo "  no_spmk:    {$p->no_spmk}\n";
echo "  nilai_pagu: {$p->nilai_pagu}\n";
echo "  nilai_kontrak: {$p->nilai_kontrak}\n";
echo "  tanggal_spk:   {$p->tanggal_spk}\n";
echo "  tanggal_mulai: {$p->tanggal_mulai}\n";
echo "  tanggal_akhir: {$p->tanggal_akhir}\n";
echo "  hari_kerja:    {$p->hari_kerja}\n";
echo "  vendor:        {$vendor}\n";
echo "  termin_count:    {$termin}\n";
echo "  milestone_count: {$milestone}\n";

$errs = [];
if (!str_contains(strtolower($p->nama_pekerjaan), 'geoteknik')) $errs[] = "nama: '{$p->nama_pekerjaan}' (expect contains 'geoteknik')";
if (!str_contains((string) $p->no_spk, '602.1/09/SPK')) $errs[] = "no_spk: '{$p->no_spk}' (expect 602.1/09/SPK/...)";
if ((float) $p->nilai_kontrak !== 99594750.0) $errs[] = "nilai_kontrak: {$p->nilai_kontrak} (expect 99594750)";
$mulai = (string) $p->tanggal_mulai;
if (!str_starts_with($mulai, '2026-01-05')) $errs[] = "tanggal_mulai: '{$mulai}' (expect 2026-01-05)";
$akhir = (string) $p->tanggal_akhir;
if (!str_starts_with($akhir, '2026-02-03') && !str_starts_with($akhir, '2026-02-04')) {
    $errs[] = "tanggal_akhir: '{$akhir}' (expect 2026-02-03 atau 2026-02-04)";
}
if (!str_contains((string) $vendor, 'ITERGO')) $errs[] = "vendor: '{$vendor}' (expect contains ITERGO)";
if ($termin < 1) $errs[] = "termin_count: 0 (expect >=1)";
if ($milestone < 1) $errs[] = "milestone_count: 0 (expect >=1)";

if (empty($errs)) {
    echo "\n✅✅✅ ALL CHECKS PASS — ZERO BUGS\n";
    exit(0);
}
echo "\n❌ VALIDATION FAILED:\n";
foreach ($errs as $e) echo "  - $e\n";
exit(1);
