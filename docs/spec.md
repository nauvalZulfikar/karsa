# DPUTR-PM Feature Specs

---

## Smart Milestone Generation + Vendor-Admin Confirmation Workflow — 2026-05-24

**Problem:** Milestones auto-created from KAK/SPK documents are generic placeholders ("Milestone 1") that carry no project-specific deliverable information, and there is no structured approval flow for vendors to submit completion and admins to confirm or reject it.

**Users:** `vendor` (submits milestone completion), `pptk`/`ppk`/`admin_bidang` (confirms/rejects), `super_admin` (full access)

---

### Part A — Smart Milestone Generation

**MVP scope:**
- Upgrade `KickoffParserService` to extract Section 11 (Keluaran) and Section 12 (Pelaporan) as structured objects: each deliverable has `nama` (deliverable title) and `isi` (array of required sub-contents / bullet items), not a flat string list
- Upgrade `KontrakParserService` to extract Jadwal Pelaksanaan phases (D/C/B/A or equivalent) from the Gantt schedule table: each phase has `nama_fase`, `kegiatan` (activity list), and `minggu_selesai` (week number from contract start)
- Add merge logic in `AiChatService::create_pekerjaan`: for each Jadwal phase, match to KAK deliverables and produce a milestone with:
  - `nama`: from Jadwal phase name
  - `deskripsi`: concatenation of matching KAK Section 11+12 sub-items (exact wording from document)
  - `tanggal_target`: calculated as `tanggal_mulai + (minggu_selesai × 7)` days
  - `progres_target_persen`: derived proportionally from phase position (or taken from contract if explicit)
  - `sumber`: `'kontrak'` when phase is explicit in SPK, `'generated_ai'` when inferred

**Out of scope:**
- Manual editing of the AI merge result before saving (admin can edit via existing EditAction)
- Retroactive re-parsing of already-saved milestones
- Support for non-PDF/XLSX Jadwal formats (image-only scans without OCR fallback)

**Acceptance criteria:**
- [ ] After uploading KAK + SPK with a Jadwal Pelaksanaan table, created milestones have `nama` matching Jadwal phase labels (not "Milestone 1")
- [ ] Each milestone's `deskripsi` contains text sourced from KAK Section 11 or 12 sub-items
- [ ] `tanggal_target` is a real date derived from `tanggal_mulai` + week offset, not null
- [ ] If KAK has no Section 11/12, or SPK has no Jadwal table, system falls back gracefully to the current 3–5 generated_ai milestones (no crash)
- [ ] `sumber` is set to `'kontrak'` when Jadwal phase is explicitly in SPK, `'generated_ai'` otherwise

**Assumptions made:**
- KAK Section 11 (Keluaran) and Section 12 (Pelaporan) use standard Indonesian government numbering — heading detection via regex on section numbers is sufficient
- Jadwal phases are labeled alphabetically (A/B/C/D) or as "Fase I/II/III" — both patterns must be handled
- `minggu_selesai` is always integer weeks from contract start date (`tanggal_mulai` from SPK)
- When KAK deliverable count differs from SPK phases, merge by position order (phase 1 → deliverable 1, etc.)
- `progres_target_persen` distributes evenly if not stated explicitly in the SPK (e.g., 4 phases → 25/50/75/100)

---

### Part B — Vendor-Admin Confirmation Workflow

**MVP scope:**
- Add three new status values to `MilestonePekerjaan`: `diajukan_vendor`, `dikonfirmasi`, `ditolak` (extend `$statusOptions` and `$statusColors` arrays)
- Add migration for three new nullable columns on `milestone_pekerjaan`: `alasan_penolakan` (text), `confirmed_by` (FK → `users.id`), `confirmed_at` (timestamp)
- `MilestoneRelationManager` — role-aware action buttons:
  - `vendor` role sees **"Ajukan Selesai"** button on milestones with status `sedang_berjalan` or `belum_mulai`; clicking sets status → `diajukan_vendor` and triggers notifications
  - `pptk`/`ppk`/`admin_bidang`/`super_admin` see **"Konfirmasi"** and **"Tolak"** buttons on milestones with status `diajukan_vendor`
  - "Konfirmasi" sets status → `dikonfirmasi`, fills `confirmed_by` = auth user, `confirmed_at` = now
  - "Tolak" opens a modal requiring `alasan_penolakan` (required, min 10 chars), then sets status → `ditolak` and stores the reason
- Auto-notification on vendor submission (`diajukan_vendor`):
  - Filament database notification sent to all users with role `pptk`, `ppk`, or `admin_bidang` in the same bidang as the pekerjaan
  - WA notification via existing `WaGatewayService` to the PPK's phone number (taken from `Pekerjaan → ppk` relation)

**Out of scope:**
- Notification to vendor on admin decision (Phase 2 enhancement)
- Re-submission after rejection (vendor must contact admin manually)
- Bulk confirm/reject actions

**Acceptance criteria:**
- [ ] Vendor user sees "Ajukan Selesai" on eligible milestones; after clicking, status shows "Diajukan Vendor"
- [ ] Vendor user does NOT see "Konfirmasi" or "Tolak" buttons
- [ ] Admin/PPK/PPTK user sees "Konfirmasi" + "Tolak" only on milestones with status `diajukan_vendor`
- [ ] "Tolak" without filling `alasan_penolakan` is rejected with a validation error
- [ ] After admin confirms, `confirmed_by` and `confirmed_at` are populated in the database
- [ ] On vendor submission, at least one Filament database notification is created for a relevant admin user
- [ ] On vendor submission, `WaGatewayService` is called with the PPK's phone; if PPK has no phone, WA is skipped without error
- [ ] Activity log records status transitions (covered by existing `LogsActivity` trait on model)
- [ ] `ditolak` status badge renders in `danger` color; `diajukan_vendor` in `warning`; `dikonfirmasi` in `success`

**Assumptions made:**
- `Pekerjaan` model has a `ppk` relation (or `ppk_phone`/`ppk_wa` field) accessible from `MilestonePekerjaan`; if not, WA notification is skipped silently
- Vendor can only submit milestones belonging to pekerjaan they are assigned to (existing vendor scoping from Phase 7 applies)
- "Tolak" resets the milestone to `ditolak` — it does NOT auto-revert to `sedang_berjalan`; admin edits status manually if rework is needed
- WA message template for submission: `"[DPUTR-PM] Vendor mengajukan selesai: {milestone.nama} — {pekerjaan.nama_pekerjaan}. Silakan konfirmasi di panel admin."`
- Role check uses Spatie `hasRole()` — no additional gate/policy is created; visibility is handled in Filament `visible()` closures

---

## Milestone Checklist Items — 2026-05-25

**Problem:** Milestones carry deliverable text in `deskripsi` as a flat string, giving vendors and admins no structured way to tick off individual kegiatan or deliverable sub-items before a milestone is submitted as complete.

**Users:** `vendor` (ticks own checklist items), `pptk`/`ppk`/`admin_bidang`/`super_admin` (ticks admin verification items, views vendor ticks)

**MVP scope:**
- New table `milestone_checklist_items`: `id`, `milestone_pekerjaan_id` (FK cascade delete), `tipe` (enum: `kegiatan`|`deliverable`), `nama` (varchar 500), `is_done_vendor` (bool default false), `vendor_done_at` (timestamp null), `is_done_admin` (bool default false), `admin_done_at` (timestamp null), `admin_done_by` (FK `users.id` null), `timestamps`
- New model `MilestoneChecklistItem` with fillable, `belongsTo MilestonePekerjaan`, `belongsTo User via admin_done_by`; add `hasMany checklistItems` on `MilestonePekerjaan`
- Update `mergeMilestonesFromParsedDocs`: write BOTH `kegiatan[]` (from Jadwal fase) AND `deliverable isi[]` (from KAK keluaran) into `deskripsi`; return `kegiatan[]` and `deliverable_items[]` arrays per milestone entry
- After milestone DB insert in `toolCreatePekerjaan` loop: create checklist items from the milestone's `kegiatan[]` (tipe=`kegiatan`) and `deliverable_items[]` (tipe=`deliverable`); failures are non-fatal (same pattern as milestone insert)
- `MilestoneRelationManager` UI additions:
  - Add `Deskripsi` column (wrap, `toggleable` hidden by default)
  - Add checklist progress indicator column: e.g. "3/5 vendor · 1/5 admin"
  - Add expandable row detail (or modal action) showing all checklist items with two checkbox columns — vendor tick (disabled unless auth is vendor role) and admin tick (disabled unless auth is admin role)
  - Vendor checkbox saves `is_done_vendor=true`, `vendor_done_at=now()`; unchecking resets both to false/null
  - Admin checkbox saves `is_done_admin=true`, `admin_done_at=now()`, `admin_done_by=auth()->id()`; unchecking resets to false/null/null
  - "Ajukan Selesai" button (existing) is disabled — and shows tooltip "Semua checklist vendor harus selesai" — until all `is_done_vendor=true` for the milestone's checklist items (milestones with zero items are unblocked)

**Out of scope:**
- Manual add/delete/reorder checklist items in the UI (admin can use direct DB or future phase)
- Checklist items for milestones created before this feature (no backfill)
- Admin checklist must be complete before "Konfirmasi" (admin ticks at their discretion)
- Per-item comments or file attachments

**Acceptance criteria:**
- [ ] After AI creates a pekerjaan with Jadwal + KAK, `milestone_checklist_items` rows exist for each milestone, with correct `tipe` (kegiatan vs deliverable)
- [ ] `milestone_checklist_items` rows are deleted automatically when parent `milestone_pekerjaan` row is deleted (cascade)
- [ ] Vendor user can tick a checklist item; `is_done_vendor` and `vendor_done_at` are saved; non-vendor cannot tick vendor column
- [ ] Admin role can tick admin column; `is_done_admin`, `admin_done_at`, `admin_done_by` are saved; non-admin cannot tick admin column
- [ ] Progress indicator in the milestone list shows correct fraction (e.g. "2/4 vendor · 0/4 admin")
- [ ] "Ajukan Selesai" button is disabled when any `is_done_vendor=false` exists for that milestone's checklist items
- [ ] "Ajukan Selesai" button is enabled (existing behavior) when milestone has zero checklist items OR all `is_done_vendor=true`
- [ ] `deskripsi` on a merged milestone contains text from both kegiatan and deliverable sources (not deliverable-only as before)
- [ ] Milestone insert failure does not prevent checklist item creation for other milestones; checklist item failure does not crash the whole `toolCreatePekerjaan` flow

**Assumptions made:**
- Filament 3 does not natively support inline expandable row checklists; implementation uses a `Tables\Actions\Action` modal ("Lihat Checklist") with a repeater-style view — architect decides exact Filament component
- Vendor checkbox toggle is an Action button per item (not a native Filament toggle column), to allow server-side role enforcement via `abort_unless`
- Milestones inserted without going through `mergeMilestonesFromParsedDocs` (e.g. manual CreateAction) get zero checklist items — vendor can still "Ajukan Selesai" immediately (zero-item rule above)
- `kegiatan[]` from Jadwal phase and `deliverable_items[]` from KAK keluaran `isi[]` are string arrays; each string becomes one checklist row's `nama`
- `admin_done_by` references `users.id`; if that user is deleted, FK is set null (nullable FK, no cascade on user delete)
- Activity log on checklist item ticks is NOT required in MVP (model can omit `LogsActivity`); milestone-level log already captures submission/confirmation transitions
