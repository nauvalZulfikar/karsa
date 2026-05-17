# KARTA Autonomous Build Roadmap

> **Maintained by:** Dev agents (cron-triggered daily) · **Reviewed by:** Owner
> **Last updated:** 2026-05-17

## Status Legend

- `[ ]` pending — belum dikerjakan
- `[~]` in_progress — dev agent sedang ngerjain (klaim aktif)
- `[x]` done — selesai (timestamp + commit SHA di catatan)
- `[!]` blocked — gagal/perlu manual intervention (alasan di catatan)
- `[s]` skipped — di-skip karena ada perubahan scope

## Rules for Dev Agents

1. **Pick rule:** First P1 task dengan status `[ ]` dan tanpa unresolved dependency
2. **Claim rule:** Mark `[~]` dengan timestamp + agent ID sebelum mulai
3. **Done rule:** Mark `[x]` setelah `/feature` flow PASS semua step (test green + reviewer ok)
4. **Block rule:** Kalau gagal 5x test loop atau reviewer tolak, mark `[!]` + tulis alasan
5. **Commit rule:** Setiap done task = 1 commit dengan message `feat(karta): <task title>`
6. **Notification rule:** Setelah selesai 1 task, kirim PushNotification ke owner
7. **Budget rule:** Skip security-auditor + deployer per task (terlalu mahal). Run di end of sprint.

---

## P1 — Critical Path (Build First)

### Sprint 1 — Foundation (Sprint Goal: Tools dasar CRUD + workflow)

- [~] **F1.1** · claimed 2026-05-17 08:42 Consolidate roles 6→4 (Superadmin, Admin, Vendor, Staff)
  - Action: rename `admin_bidang` → `admin`, hapus `pptk`/`ppk`/`viewer` atau merge ke roles 4
  - Update: middleware, gates, seeders, blueprint
  - Est: 2-3h · Depends: none
  - Files: `database/seeders/RoleAndPermissionSeeder.php`, all `canViewAny()` gates

- [ ] **F1.2** Tool: `create_pekerjaan` (Project Manager Agent)
  - Input: nama, bidang_id, jenis_pekerjaan_id, pagu, dll
  - Output: pekerjaan_id baru
  - Est: 1-2h · Depends: F1.1
  - Files: `app/Services/AiChatService.php` (tool def + executor)

- [ ] **F1.3** Tool: `update_pekerjaan` (Project Manager Agent)
  - Input: pekerjaan_id + fields to update
  - Est: 1-2h · Depends: F1.2

- [ ] **F1.4** Tool: `assign_vendor` (Project Manager Agent)
  - Input: pekerjaan_id, perusahaan_id
  - Est: 1h · Depends: F1.2

- [ ] **F1.5** Tool: `assign_personil` (Project Manager Agent)
  - Input: pekerjaan_id, tenaga_ahli_id, jabatan_kontrak, nilai_honor
  - Est: 1h · Depends: F1.4

- [ ] **F1.6** Tool: `create_rencana_pengadaan` (bulk from RAB)
  - Input: pekerjaan_id, items[] (kategori, uraian, volume, satuan, harga_satuan)
  - Pattern: dari output `parse_rab_pdf` → bulk insert
  - Est: 2-3h · Depends: F1.2 + parse_rab_pdf (done)

- [ ] **F1.7** Tool: `submit_daily_report` (Workflow Manager Agent)
  - Input: pekerjaan_id, tipe (survey_awal/sondir/bor_log/simulasi/rekomendasi), foto[], data (jsonb), catatan, GPS
  - Output: daily_report_id
  - Pre-cond: user must be Vendor or Staff assigned to pekerjaan
  - Est: 3-4h · Depends: F1.1 (need staff role)

- [ ] **F1.8** Tool: `approve_daily_report` (Workflow Manager Agent)
  - Input: daily_report_id, catatan
  - Pre-cond: user = Admin or Superadmin
  - Est: 1h · Depends: F1.7

- [ ] **F1.9** Tool: `request_termin` (Workflow Manager Agent)
  - Input: pekerjaan_id, nomor_termin, persen_progres, alasan
  - Pre-cond: user = Vendor
  - Est: 1-2h · Depends: F1.1

- [ ] **F1.10** UI: File upload in chat widget
  - Drag-drop PDF/XLSX into chat → auto upload + reference to AI tool
  - Est: 4-5h · Depends: none
  - Files: `resources/views/filament/ai-chat.blade.php`, JS component

### Sprint 2 — Writer Foundation (Sprint Goal: Generate Laporan + Invoice full)

- [ ] **F2.1** Template DOCX: Laporan Pendahuluan (PHPWord)
  - Bab 1 (Pendahuluan): placeholder dari KAK
  - Bab 2-3 (Metodologi + Teori): boilerplate per jenis pekerjaan
  - Cover + Kata Pengantar
  - Est: 4-5h · Depends: F1.1
  - Files: `resources/templates/laporan_pendahuluan_geoteknik.docx`

- [ ] **F2.2** Template DOCX: Laporan Akhir
  - Inherit Pendahuluan + Bab 4-6 (data lapangan, simulasi, rekomendasi)
  - Inject dari daily reports
  - Est: 4-5h · Depends: F2.1, F1.7
  - Files: `resources/templates/laporan_akhir_geoteknik.docx`

- [ ] **F2.3** Tool: `generate_laporan_pendahuluan`
  - Input: pekerjaan_id
  - Output: DOCX download URL
  - Est: 2-3h · Depends: F2.1

- [ ] **F2.4** Tool: `generate_laporan_akhir`
  - Input: pekerjaan_id
  - Pre-check: minimum N daily reports per tipe must exist
  - Est: 2-3h · Depends: F2.2

- [ ] **F2.5** Template XLSX: Invoice 6-sheet (mirror Itergo format)
  - Sheets: RINCIAN MC, REKAP MC, MC, SURAT PERMOHONAN, DATA INDUK, SURAT LHPP
  - Pakai PhpSpreadsheet template processor
  - Est: 6-8h · Depends: parse_rab_pdf
  - Files: `resources/templates/invoice_template.xlsx`

- [ ] **F2.6** Tool: `generate_invoice_xlsx_full`
  - Input: pekerjaan_id, prestasi_persen
  - Output: XLSX download URL
  - Est: 3-4h · Depends: F2.5

### Sprint 3 — Polish (Sprint Goal: Edge case docs + validation)

- [ ] **F3.1** Tool: `parse_penawaran_pdf`
  - Extract: tim vendor (nama, jabatan, CV summary), metodologi, harga penawaran
  - Est: 2-3h · Depends: F1.1

- [ ] **F3.2** Tool: `generate_kuitansi_gaji`
  - Input: pekerjaan_id, periode
  - Output: 1 kuitansi per personil
  - Est: 2h · Depends: F2.5

- [ ] **F3.3** Tool: `generate_invoice_atk`
  - Input: pekerjaan_id
  - Output: XLSX kuitansi ATK
  - Est: 1-2h · Depends: F2.5

- [ ] **F3.4** Tool: `generate_invoice_sewa_alat`
  - Input: pekerjaan_id, harga_supplier (opsional override)
  - Est: 1-2h · Depends: F2.5

- [ ] **F3.5** Tool: `generate_surat_permohonan_pembayaran`
  - Est: 2h · Depends: F2.6

- [ ] **F3.6** Tool: `generate_surat_lhpp`
  - Input: pekerjaan_id, panitia_pho[]
  - Est: 2h · Depends: master panitia data

- [ ] **F3.7** Tool: `generate_bast`
  - Est: 2h · Depends: F2.4 + F2.6 (laporan + invoice done)

- [ ] **F3.8** Validation: `cross_check_rab_vs_kontrak`
  - Cek total RAB = nilai kontrak ± toleransi
  - Cek termin sum = 100%
  - Est: 2h · Depends: F1.6

- [ ] **F3.9** Validation: `validate_photo_authenticity`
  - Detect duplicate, EXIF GPS match lokasi pekerjaan
  - Est: 3-4h · Depends: F1.7

- [ ] **F3.10** Notification: tool wrapper `send_wa_to_vendor`
  - Wrap existing `WaGatewayService`
  - Est: 1h · Depends: none

### Sprint 4 — Hardening (Sprint Goal: Production-ready)

- [ ] **F4.1** E2E test per role (Superadmin/Admin/Vendor/Staff)
  - Est: 6-8h
  - Files: `tests/Feature/AiChat/*`

- [ ] **F4.2** Anti-hallucination guardrails final
  - Implement 8 rules from blueprint
  - Update system prompt
  - Est: 4h

- [ ] **F4.3** Audit log dashboard
  - Show all AI tool calls via Karta UI
  - Est: 4h

- [ ] **F4.4** Documentation update (CHANGELOG, README)
  - Est: 2-3h

- [ ] **F4.5** UI: hide all sidebar menus, fullscreen chat as default
  - Est: 3-4h
  - Files: `app/Providers/Filament/AdminPanelProvider.php`

- [ ] **F4.6** User Manager tools: `invite_vendor_user`, `invite_staff_user`, `grant_admin_access`, `revoke_access`
  - Est: 4-5h · Depends: F1.1

---

## P2 — Important (After P1)

- [ ] Tool: `reject_termin`, `reject_realisasi`, `reject_daily_report`
- [ ] Tool: `submit_realisasi`, `approve_realisasi`
- [ ] Tool: `delete_pekerjaan` (soft delete)
- [ ] Templates for other jenis pekerjaan: Topografi, Drainase, Jalan
- [ ] Bulk operations: bulk invite vendors, bulk import pekerjaan
- [ ] Quality scoring per vendor (track history)

## P3 — Nice to Have

- [ ] Smart automation: AI auto-detect kapan generate laporan
- [ ] AI proactive: notif WA vendor kalau daily report telat
- [ ] AI fluent multi-turn (ingat konteks proyek)
- [ ] Real-time collaboration (multi-user editing same proyek)
- [ ] Mobile-first UI optimization

---

## Notes for Dev Agents

- Refer to **KARTA_BLUEPRINT.md** for architecture & role definitions
- Existing AiChatService pattern: tool def in `getToolDefinitions()` + executor in `executeTool()` match expression
- Use Sonnet for implementer, Haiku for tester, Opus for fixer (per CLAUDE.md)
- Skip security-auditor + deployer per task (run at end of sprint instead)
- Commit message format: `feat(karta): F<X.Y> <task title>`
- After commit: mark task `[x] · {YYYY-MM-DD HH:mm} · {commit SHA}`
