<?php
/**
 * Cleanup test artifacts before re-running smoke test.
 * Deletes any pekerjaan beyond the 6 seeded ones (id > 6).
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Hard-delete (bypass SoftDeletes) so unique constraints aren't tripped on re-run
$threshold = 6;
$victims = \App\Models\Pekerjaan::withTrashed()
    ->where(function ($q) use ($threshold) {
        $q->where('id', '>', $threshold)
          ->orWhere('nama_pekerjaan', 'LIKE', '%geoteknik%')
          ->orWhere('no_spk', 'LIKE', '%Geoteknik%');
    })
    ->get(['id', 'nama_pekerjaan']);
echo "Hard-deleting " . $victims->count() . " pekerjaan (test + geoteknik conflicts incl soft-deleted):\n";
foreach ($victims as $v) {
    echo "  - #{$v->id} {$v->nama_pekerjaan}\n";
    \DB::table('termin_pembayaran')->where('pekerjaan_id', $v->id)->delete();
    \DB::table('milestone_pekerjaan')->where('pekerjaan_id', $v->id)->delete();
    \DB::table('pekerjaan_vendor')->where('pekerjaan_id', $v->id)->delete();
    \DB::table('pekerjaan_personil')->where('pekerjaan_id', $v->id)->delete();
    \DB::table('rencana_pengadaan')->where('pekerjaan_id', $v->id)->delete();
    \DB::table('pekerjaan')->where('id', $v->id)->delete(); // raw = hard delete
}
echo "Done.\n";
