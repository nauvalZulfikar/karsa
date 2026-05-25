<?php
/**
 * Smoke test: simulate user uploading 4 docs (KAK/Kontrak/Penawaran/RAB)
 * and asking "bikin proyek baru" via AI chat — end-to-end.
 *
 * Usage:
 *   php scripts/smoke/create_project_from_docs.php
 *
 * Exits 0 on success, 1 on failure. Writes detailed log to tmp/smoke/<timestamp>.log
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChatUpload;
use App\Models\Pekerjaan;
use App\Models\User;
use App\Services\AiChatService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

// --- Config ---
$docsRoot = 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah';
$docs = [
    'kak'        => $docsRoot . '/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf',
    'kontrak'    => $docsRoot . '/Spk,Spmk,Ba PT Itergo - Kajian Geoteknik Stabilitas Tanah .pdf',
    'rab'        => $docsRoot . '/Lampiran Negosiasi Konsultan Konstruksi.pdf',
    'penawaran'  => $docsRoot . '/PRINT PT. ITERGO BUANA UTAMA GEOTEKNIK/penawaran-PT. ITERGO BUANA UTAMA.xlsx',
];

$logDir = __DIR__ . '/../../tmp/smoke';
@mkdir($logDir, 0777, true);
$logFile = $logDir . '/run-' . date('Ymd-His') . '.log';

function logLine(string $msg, string $logFile): void
{
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

$L = fn (string $m) => logLine($m, $logFile);

$L("=== SMOKE TEST: create project from 4 geoteknik docs ===");
$L("Log file: $logFile");

// --- Step 1: Verify docs exist ---
$L("\n[1] Verifying source files...");
foreach ($docs as $type => $path) {
    if (!file_exists($path)) {
        $L("✗ MISSING: $type → $path");
        exit(1);
    }
    $size = round(filesize($path) / 1024, 1);
    $L("  ✓ $type: " . basename($path) . " ({$size} KB)");
}

// --- Step 2: Auth as superadmin ---
$L("\n[2] Auth as superadmin...");
$user = User::where('email', 'admin@dputr.go.id')->first();
if (!$user) {
    $L("✗ No superadmin user found");
    exit(1);
}
Auth::login($user);
$L("  ✓ Logged in as: {$user->email} (id={$user->id})");

// --- Step 3: Copy + register as ChatUpload (simulating widget) ---
$L("\n[3] Registering uploads (with PDF pre-extract)...");
ChatUpload::where('user_id', $user->id)->delete(); // clean slate

$attachments = [];
foreach ($docs as $type => $srcPath) {
    $name = basename($srcPath);
    $contents = file_get_contents($srcPath);
    $relPath = 'ai_chat_uploads/' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    Storage::disk('local')->put($relPath, $contents);
    $abs = Storage::disk('local')->path($relPath);

    $mime = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'pdf'  => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        default => 'application/octet-stream',
    };

    $upload = ChatUpload::create([
        'user_id'       => $user->id,
        'original_name' => $name,
        'abs_path'      => $abs,
        'mime'          => $mime,
        'size_bytes'    => filesize($abs),
    ]);

    // Pre-extract PDF text (same as updatedUploadedFile)
    if (str_ends_with(strtolower($name), '.pdf')) {
        try {
            $t0 = microtime(true);
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($abs);
            $text = trim($pdf->getText());
            $upload->update([
                'ocr_text'       => mb_substr($text, 0, 15000),
                'is_scanned_pdf' => mb_strlen($text) < 100,
            ]);
            $dur = round((microtime(true) - $t0) * 1000);
            $L("  ✓ $type ($name): pre-extracted " . mb_strlen($text) . " chars in {$dur}ms");
        } catch (\Throwable $e) {
            $L("  ! $type ($name): pre-extract failed: " . $e->getMessage());
        }
    } else {
        $L("  ✓ $type ($name): registered (no pre-extract for non-PDF)");
    }

    $attachments[] = ['name' => $name, 'path' => $abs];
}

// --- Step 4: Build messages array (like the widget would) ---
$L("\n[4] Building chat messages...");
$pekerjaanBefore = Pekerjaan::count();
$L("  Pekerjaan count BEFORE: $pekerjaanBefore");

$body = "bikin proyek baru. Source of truth = data dari KONTRAK. Kalau dokumen lain (RAB/Penawaran) mismatch atau ternyata beda proyek, abaikan tanpa nanya — proceed buat proyek dengan data kontrak. Auto-eksekusi semua step tanpa konfirmasi lagi, kasih ringkasan di akhir.\n\n[File terlampir]\n";
foreach ($attachments as $att) {
    $body .= "- {$att['name']} → {$att['path']}\n";
}
$messages = [
    ['role' => 'user', 'content' => $body],
];

// --- Step 5: Call AiChatService::chat() (multi-turn with auto-confirm) ---
$L("\n[5] Calling AiChatService::chat() — multi-turn auto-confirm...");
@set_time_limit(0);
@ini_set('max_execution_time', '0');

$service = app(AiChatService::class);
$pekerjaanBaseline = $pekerjaanBefore;
$maxTurns = 3;

for ($turn = 1; $turn <= $maxTurns; $turn++) {
    $t0 = microtime(true);
    try {
        $reply = $service->chat($messages);
    } catch (\Throwable $e) {
        $dur = round(microtime(true) - $t0, 1);
        $L("  ✗ TURN $turn EXCEPTION after {$dur}s: " . $e->getMessage());
        $L("  Trace: " . substr($e->getTraceAsString(), 0, 1000));
        exit(1);
    }
    $dur = round(microtime(true) - $t0, 1);
    $L("  ✓ turn $turn returned after {$dur}s");
    $L("--- AI REPLY (turn $turn) ---");
    $L(mb_substr($reply, 0, 1500));
    $L("--- end ---");

    $messages[] = ['role' => 'assistant', 'content' => $reply];

    // Check if a project was created during this turn
    if (Pekerjaan::count() > $pekerjaanBaseline) {
        $L("  → Pekerjaan record detected after turn $turn — stopping multi-turn loop");
        break;
    }

    // Detect if AI is asking for confirmation; if so, auto-confirm
    $lower = mb_strtolower($reply);
    $needsConfirm = str_contains($lower, 'force_create')
        || str_contains($lower, 'lanjutkan')
        || str_contains($lower, 'apakah anda')
        || str_contains($lower, 'mau lanjut')
        || str_contains($lower, 'mau bikin baru')
        || str_contains($lower, 'similar')
        || str_contains($lower, 'mirip');

    if (!$needsConfirm) {
        $L("  → AI didn't ask for confirmation but also no pekerjaan created. Stopping.");
        break;
    }

    $confirmMsg = "Ya, lanjutkan. Anggap ini proyek BARU (force_create=true). "
        . "Untuk RAB yang ternyata beda proyek, skip step rencana_pengadaan, "
        . "tapi tetap lakukan create_pekerjaan + assign_vendor. "
        . "Auto-eksekusi, gak perlu nanya lagi.";
    $L("  → auto-confirm turn $turn: $confirmMsg");
    $messages[] = ['role' => 'user', 'content' => $confirmMsg];
}

// --- Step 6: Verify DB side effects ---
$L("\n[6] Verifying DB side effects...");
$pekerjaanAfter = Pekerjaan::count();
$L("  Pekerjaan count AFTER: $pekerjaanAfter (delta: " . ($pekerjaanAfter - $pekerjaanBefore) . ")");

if ($pekerjaanAfter > $pekerjaanBefore) {
    $latest = Pekerjaan::latest('id')->first();
    $L("  ✓ Latest pekerjaan: id={$latest->id} | nama={$latest->nama_pekerjaan}");
    $L("    no_spk={$latest->no_spk} | nilai_kontrak={$latest->nilai_kontrak}");
    $L("    tanggal_mulai={$latest->tanggal_mulai} | tanggal_akhir={$latest->tanggal_akhir}");

    // Count related records
    $L("    termin_pembayaran: " . $latest->terminPembayaran()->count());
    $L("    milestones: " . $latest->milestones()->count());

    $L("\n✅ SUCCESS — project created end-to-end");
    file_put_contents($logDir . '/last-success-pekerjaan-id.txt', $latest->id);
    exit(0);
} else {
    $L("\n❌ FAIL — no new pekerjaan created. AI reply above tells why.");
    exit(1);
}
