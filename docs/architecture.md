# DPUTR-PM Architecture Decisions

---

## Smart Milestone Generation + Vendor-Admin Confirmation Workflow — 2026-05-24

**Stack additions:** none

---

### Data Model

**Migration:** `2026_05_24_100000_add_confirmation_columns_to_milestone_pekerjaan.php` (NEW)

Changes to `milestone_pekerjaan`:

| Column | Type | Notes |
|---|---|---|
| `status` | enum (MODIFY) | Extend existing enum: add `diajukan_vendor`, `dikonfirmasi`, `ditolak` |
| `alasan_penolakan` | `text nullable` | ADD — filled on Tolak action |
| `confirmed_by` | `unsignedBigInteger nullable` (FK → users.id, nullOnDelete) | ADD |
| `confirmed_at` | `timestamp nullable` | ADD |

Migration strategy: two separate statements — `ALTER TABLE MODIFY status enum(...)` using `->change()` doctrine, then `->addColumn` for the three new nullable columns.

Full enum values after migration: `belum_mulai`, `sedang_berjalan`, `selesai`, `terlambat`, `diajukan_vendor`, `dikonfirmasi`, `ditolak`.

---

### Part A — Smart Milestone Generation

#### Data Flow

```
KAK PDF  → KickoffParserService::parse()  → result['keluaran']  (array of {nama, isi[]})
SPK PDF  → KontrakParserService::parse()  → result['jadwal_pelaksanaan'] (array of {nama_fase, kegiatan[], minggu_selesai})
                                          → result['milestones']  (kept as fallback)

AiChatService::handleCreatePekerjaan()
  → calls mergeMilestonesFromParsedDocs(jadwal, keluaran, tanggal_mulai)
  → returns normalized milestones array (same shape as existing tool schema)
  → existing DB insert loop at lines 1367-1384 (unchanged)
```

#### `KickoffParserService` changes

File: `app/Services/KickoffParserService.php`

- **Prompt change** (line 161): replace `"keluaran": ["array string"]` with:
  ```
  "keluaran": [
    { "nama": "Laporan Pendahuluan", "isi": ["sub-item 1", "sub-item 2"] }
  ],
  "pelaporan": [
    { "nama": "Laporan Akhir", "isi": ["sub-item 1"] }
  ]
  ```
  Instruct LLM: "Ekstrak Section 11 (Keluaran) dan Section 12 (Pelaporan) dari KAK. Setiap deliverable sebagai objek {nama, isi[]}. Kalau section tidak ada, kembalikan array kosong."

- **New private method** `normalizeKeluaran(mixed $list): array` — replaces call to `normalizeStringList()` for the `keluaran` field. Each item normalized to `['nama' => string, 'isi' => string[]]`. Empties filtered. Falls back to empty array.

- **Result shape change**: `$result['keluaran']` is now `array<{nama: string, isi: string[]}>` instead of `string[]`. `$result['pelaporan']` is new with same shape.

#### `KontrakParserService` changes

File: `app/Services/KontrakParserService.php`

- **Prompt change** (after line 148, inside existing JSON structure): add new top-level key:
  ```
  "jadwal_pelaksanaan": [
    {
      "nama_fase": "Fase A / Tahap I / etc",
      "kegiatan": ["activity 1", "activity 2"],
      "minggu_selesai": 4
    }
  ]
  ```
  Instruction: "Ekstrak tabel Jadwal Pelaksanaan dari SPK. Deteksi label fase (A/B/C/D, Fase I/II/III, atau apapun). `minggu_selesai` = nomor minggu akhir dari kolom Gantt (integer dari awal kontrak). Kalau tabel tidak ada, kembalikan array kosong."

- **New private method** `normalizeJadwal(array $list): array` — normalizes each item to `['nama_fase' => string, 'kegiatan' => string[], 'minggu_selesai' => int]`. Filters items with `minggu_selesai <= 0`.

- **`normalize()` method**: add call to `normalizeJadwal($data['jadwal_pelaksanaan'] ?? [])` and include in returned array as `'jadwal_pelaksanaan'`.

- **Existing `milestones` key**: kept unchanged — serves as the fallback path when `jadwal_pelaksanaan` is empty.

#### `AiChatService` changes

File: `app/Services/AiChatService.php`

- **Tool schema** (around line 458): add `jadwal_pelaksanaan` parameter to `create_pekerjaan` tool — same array shape as above. Description: "OTOMATIS pass kalau parse_kontrak_pdf return jadwal_pelaksanaan. Digunakan bersama keluaran_kak untuk generate milestone kaya."

- **Tool schema**: add `keluaran_kak` parameter (array of `{nama, isi[]}`) — "OTOMATIS pass dari parse_kak_pdf result['keluaran'] + result['pelaporan'] digabung."

- **New private method** `mergeMilestonesFromParsedDocs(array $jadwal, array $keluaran, ?string $tanggalMulai): array`:
  - If `$jadwal` is empty: return empty array (caller falls back to `$input['milestones']`).
  - Sort `$jadwal` by `minggu_selesai` ascending.
  - Total phases = `count($jadwal)`. Compute even `progres_target_persen` = `round((i+1) / total * 100, 2)`.
  - For each phase at index `$i`:
    - `urutan` = `$i + 1`
    - `nama` = `$fase['nama_fase']`
    - `deskripsi`: if `$keluaran[$i]` exists, concat `$keluaran[$i]['nama']` + `: ` + `implode('; ', $keluaran[$i]['isi'])`. Else use `implode(', ', $fase['kegiatan'])`.
    - `hari_setelah_mulai` = `$fase['minggu_selesai'] * 7`
    - `progres_target_persen` = if phase contains explicit `%` keyed field from LLM, use it; else even distribution.
    - `sumber` = `'kontrak'`
  - Returns normalized milestones array (same keys as existing `$input['milestones']` items).

- **`handleCreatePekerjaan()`** (method containing lines 1352-1385): before the existing milestone insert loop, insert:
  ```php
  // Attempt smart merge if jadwal_pelaksanaan + keluaran_kak provided
  if (!empty($input['jadwal_pelaksanaan'])) {
      $merged = $this->mergeMilestonesFromParsedDocs(
          $input['jadwal_pelaksanaan'],
          $input['keluaran_kak'] ?? [],
          $input['tanggal_mulai'] ?? null
      );
      if (!empty($merged)) {
          $input['milestones'] = $merged; // override LLM-generated milestones
      }
  }
  ```

- **Fallback guarantee**: if `jadwal_pelaksanaan` absent or `mergeMilestonesFromParsedDocs` returns empty, the existing `$input['milestones']` path (LLM-generated 3-5 fallback from `KontrakParserService`) continues unchanged — no crash.

---

### Part B — Vendor-Admin Confirmation Workflow

#### `MilestonePekerjaan` model changes

File: `app/Models/MilestonePekerjaan.php`

- Extend `$statusOptions`:
  ```php
  'diajukan_vendor' => 'Diajukan Vendor',
  'dikonfirmasi'    => 'Dikonfirmasi',
  'ditolak'         => 'Ditolak',
  ```
- Extend `$statusColors`:
  ```php
  'diajukan_vendor' => 'warning',
  'dikonfirmasi'    => 'success',
  'ditolak'         => 'danger',
  ```
- Add to `$fillable`: `alasan_penolakan`, `confirmed_by`, `confirmed_at`.
- Add to `$casts`: `'confirmed_at' => 'datetime'`.
- Add relation: `confirmedBy(): BelongsTo` → `User::class` via `confirmed_by`.

#### `MilestoneRelationManager` changes

File: `app/Filament/Resources/PekerjaanResource/RelationManagers/MilestoneRelationManager.php`

Replace existing single `selesaikan` action with four role-aware actions:

**Action: `ajukan_selesai`** (vendor only)
- `->label('Ajukan Selesai')`
- `->icon('heroicon-o-paper-airplane')`
- `->color('warning')`
- `->visible(fn ($record) => auth()->user()->hasRole('vendor') && in_array($record->status, ['belum_mulai', 'sedang_berjalan']))`
- `->requiresConfirmation()`
- Action body:
  ```php
  $record->update(['status' => 'diajukan_vendor']);
  // Filament DB notification to pptk/ppk/admin_bidang in same bidang
  $this->kirimNotifikasiAjuanVendor($record);
  // WA to PPK
  $this->kirimWaKePpk($record);
  Notification::make()->title('Milestone diajukan')->success()->send();
  ```

**Action: `konfirmasi`** (admin roles)
- `->label('Konfirmasi')`
- `->icon('heroicon-o-check-badge')`
- `->color('success')`
- `->visible(fn ($record) => auth()->user()->hasAnyRole(['pptk','ppk','admin_bidang','super_admin']) && $record->status === 'diajukan_vendor')`
- `->requiresConfirmation()`
- Action body: `$record->update(['status' => 'dikonfirmasi', 'confirmed_by' => auth()->id(), 'confirmed_at' => now()])`

**Action: `tolak`** (admin roles)
- `->label('Tolak')`
- `->icon('heroicon-o-x-circle')`
- `->color('danger')`
- `->visible(fn ($record) => auth()->user()->hasAnyRole(['pptk','ppk','admin_bidang','super_admin']) && $record->status === 'diajukan_vendor')`
- Form modal: `Textarea::make('alasan_penolakan')->required()->minLength(10)->label('Alasan Penolakan')`
- Action body: `$record->update(['status' => 'ditolak', 'alasan_penolakan' => $data['alasan_penolakan']])`

**Action: `tandai_selesai`** (admin roles only — replaces old `selesaikan`)
- `->visible(fn ($record) => auth()->user()->hasAnyRole(['pptk','ppk','admin_bidang','super_admin']) && !in_array($record->status, ['selesai','dikonfirmasi']))`
- Sets `status = selesai`, `tanggal_selesai_aktual = today()`

**Two private helper methods** added to `MilestoneRelationManager`:

`kirimNotifikasiAjuanVendor(MilestonePekerjaan $milestone): void`
- Load `$milestone->pekerjaan` (with `bidang_id`)
- Query: `User::whereHas('roles', fn($q) => $q->whereIn('name', ['pptk','ppk','admin_bidang']))->where('bidang_id', $pekerjaan->bidang_id)->where('is_active', true)->get()`
- For each user: `\Filament\Notifications\Notification::make()->title('Milestone Diajukan Vendor')->body("{$milestone->nama} — {$pekerjaan->nama_pekerjaan}")->sendToDatabase($user)`

`kirimWaKePpk(MilestonePekerjaan $milestone): void`
- Query PPK users in same bidang: `User::whereHas('roles', fn($q) => $q->where('name', 'ppk'))->where('bidang_id', $pekerjaan->bidang_id)->whereNotNull('no_telp')->get()`
- For each: `app(WaGatewayService::class)->kirim($user->no_telp, "[DPUTR-PM] Vendor mengajukan selesai: {$milestone->nama} — {$pekerjaan->nama_pekerjaan}. Silakan konfirmasi di panel admin.")`
- Wrapped in `try/catch` — WA failure is silent (logged only).

**Table column addition**: add `TextColumn::make('confirmed_at')->label('Dikonfirmasi')->dateTime('d M Y')->placeholder('-')` and `TextColumn::make('alasan_penolakan')->label('Alasan Tolak')->wrap()->placeholder('-')->toggleable(isToggledHiddenByDefault: true)`.

---

### File Touchpoints

| File | Action |
|---|---|
| `database/migrations/2026_05_24_100000_add_confirmation_columns_to_milestone_pekerjaan.php` | NEW |
| `app/Models/MilestonePekerjaan.php` | MODIFY — new statuses, fillable, casts, relation |
| `app/Services/KickoffParserService.php` | MODIFY — prompt upgrade, `normalizeKeluaran()`, new `pelaporan` field |
| `app/Services/KontrakParserService.php` | MODIFY — prompt upgrade, `normalizeJadwal()`, `jadwal_pelaksanaan` in output |
| `app/Services/AiChatService.php` | MODIFY — two new tool params, `mergeMilestonesFromParsedDocs()`, merge insertion before milestone loop |
| `app/Filament/Resources/PekerjaanResource/RelationManagers/MilestoneRelationManager.php` | MODIFY — replace `selesaikan` with 4 role-aware actions, 2 helper methods, 2 new table columns |

---

### Key Decisions

- **`jadwal_pelaksanaan` as additive key, not replacing `milestones`**: KontrakParserService returns both. The AI chat tool passes `jadwal_pelaksanaan` separately; `milestones` stays as the fallback. This means zero risk of breaking existing flows where no Jadwal table is present.

- **Merge by position order, not name matching**: spec explicitly states "phase 1 → deliverable 1". Name-based fuzzy matching would add complexity with no spec requirement. Position order is deterministic and predictable.

- **PPK lookup by role+bidang, not Pekerjaan FK**: the `pekerjaan` table has no `ppk_user_id` column. Rather than adding a FK (scope-creep), WA/DB notifications target all active PPK users in the same `bidang_id`. This matches how the rest of the project scopes by bidang.

- **`WaGatewayService::kirim()` not `send()`**: the service exposes `kirim()`. The existing `AiChatService` calls at lines 1815/1833 use `->send()` which is a pre-existing bug — do not copy that pattern; always call `kirim()`.

- **MySQL enum `->change()` in migration**: `doctrine/dbal` is already a Laravel 12 implicit dependency. The alter migration uses `$table->enum('status', [...])->change()` to extend the enum. The `down()` method shrinks it back. No raw SQL needed.

- **Filament `Notification::sendToDatabase()`**: uses `\Filament\Notifications\Notification` (not Laravel's base `Notification`), which writes directly to the `notifications` table and appears in the bell icon. No new Job/Queue needed for MVP scope.

---

### Open Questions

- **`perusahaan_id` on `pekerjaan`**: the current schema uses `perusahaan_id` for the contractor company, but there is no `ppk_user_id`. If a future spec adds a PPK assignment column, the WA lookup query should be updated to use it for precision. For now, role+bidang scoping is the fallback.
- **`WaGatewayService::send()` calls in AiChatService lines 1815/1833**: these are a pre-existing bug (method is `kirim()`). Out of scope to fix here but should be tracked.
