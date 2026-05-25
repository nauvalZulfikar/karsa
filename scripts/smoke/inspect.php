<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== USERS ===\n";
foreach (\App\Models\User::all(['id','name','email']) as $u) {
    echo "  #{$u->id} | {$u->email} | {$u->name}\n";
}

echo "\n=== TABLES ===\n";
$tables = \DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
foreach ($tables as $t) echo "  {$t->name}\n";

echo "\n=== PEKERJAAN FILLABLE ===\n";
$p = new \App\Models\Pekerjaan();
echo "  " . implode(', ', $p->getFillable()) . "\n";

echo "\n=== ROLES ===\n";
foreach (\Spatie\Permission\Models\Role::all(['id','name']) as $r) {
    echo "  #{$r->id} | {$r->name}\n";
}
