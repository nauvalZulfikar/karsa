<?php
require __DIR__ . '/../../vendor/autoload.php';
$a = require __DIR__ . '/../../bootstrap/app.php';
$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['termin_pembayaran', 'milestone_pekerjaan'] as $t) {
    echo "=== $t ===\n";
    foreach (\DB::select("PRAGMA table_info($t)") as $col) {
        $nn = $col->notnull ? 'NOT NULL' : '';
        $df = $col->dflt_value !== null ? "DEFAULT={$col->dflt_value}" : '';
        echo "  {$col->name} {$col->type} $nn $df\n";
    }
    echo "\n";
}
