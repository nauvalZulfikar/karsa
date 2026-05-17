# KARTA — AI-First Project Management Blueprint

> **Versi:** v1.0 · **Tanggal lock:** 2026-05-17 · **Status:** Authoritative reference
> Dokumen ini menjadi rujukan untuk semua dev agent autonomous yang membangun Karta. Jangan ubah tanpa persetujuan owner project.

---

## Vision (Hard Locked)

**Karta = AI Chatbot.** User cukup ngobrol. Sidebar menus & form UI = fallback minimal. Semua aksi signifikan dipicu via chat. User monitor hasil, bukan input manual.

---

## Role Model (Hard Locked — 4 Role)

| Role | Definisi | Scope Akses |
|---|---|---|
| **Superadmin** | Pemilik proyek (PPK/Kabid level di dinas) | Edit + lihat **SEMUA** kerjaan |
| **Admin** | Bawahan Superadmin (PPTK/staf bidang) | Edit + lihat **SEBAGIAN** kerjaan (di-grant per proyek oleh Superadmin) |
| **Vendor** | Eksekutor utama (Direktur perusahaan) | Lihat kerjaan yg di-assign, submit report, **GAK BISA** edit detail kerjaan |
| **Staff** | Bawahan Vendor (Team Leader, Surveyor) | Akses **1 kerjaan** doang, submit report untuk kerjaan itu |

**Aturan absolut:**
- Vendor & Staff TIDAK BISA edit nama proyek, pagu, kontrak, dll
- Vendor & Staff TIDAK BISA approve termin atau pencairan
- Admin gak bisa create user; hanya Superadmin
- Staff gak bisa lihat proyek lain selain yg di-assign

---

## Document Categories (Hard Locked — 6 Tipe)

| Kategori | Asal | Action | Owner |
|---|---|---|---|
| **KAK** | Dinas | UPLOAD | Superadmin / Admin (granted) |
| **Kontrak** (SPK/SPMK/BAST) | Dinas | UPLOAD | Superadmin / Admin (granted) |
| **Penawaran** | Vendor (offline) | UPLOAD via | Superadmin / Admin |
| **RAB Negosiasi** | Negosiasi Dinas-Vendor | UPLOAD | Superadmin / Admin |
| **Laporan** (Pendahuluan + Akhir) | Karta | **GENERATE** dari evidence | Vendor trigger |
| **Invoice** (utama + lampiran) | Karta | **GENERATE** dari RAB + progress | Vendor trigger |

**Aturan absolut:**
- Laporan & Invoice **TIDAK PERNAH DI-UPLOAD**, selalu GENERATED dari evidence
- Evidence = daily reports (foto, data lapangan, progress) dari Vendor & Staff
- Karta = compiler dokumen, bukan lemari arsip

---

## AI Agent Architecture (Lock This)

Karta punya 1 ChatBot interface, di-back oleh 9 specialized agents:

### A. READER Agents (parse input docs)
1. **KAK Reader Agent** — `parse_kak_pdf` — Superadmin/Admin
2. **Kontrak Reader Agent** — `parse_kontrak_pdf` — Superadmin/Admin
3. **Penawaran Reader Agent** — `parse_penawaran_pdf` — Superadmin/Admin
4. **RAB Reader Agent** — `parse_rab_pdf` — Superadmin/Admin

### B. WRITER Agents (generate output docs)
5. **Laporan Generator Agent** — `generate_laporan_pendahuluan`, `generate_laporan_akhir` — Vendor trigger
6. **Invoice Generator Agent** — `generate_invoice`, `generate_invoice_xlsx_full`, `generate_kuitansi_*`, `generate_invoice_*` — Vendor trigger
7. **Surat Generator Agent** — `generate_surat_permohonan_pembayaran`, `generate_surat_lhpp`, `generate_bast` — Vendor trigger, Superadmin/Admin approve

### C. MANAGER Agents (CRUD data)
8. **Project Manager Agent** — `create_pekerjaan`, `update_pekerjaan`, `assign_vendor`, `assign_personil`, `create_rencana_pengadaan` — Superadmin/Admin
9. **Workflow Manager Agent** — `submit_daily_report`, `approve_daily_report`, `request_termin`, `approve_termin`, `reject_*` — Per role

---

## User Journey per Role

### SUPERADMIN — Lifecycle Proyek

| # | Step | Tool yang Dipanggil |
|---|---|---|
| 1 | Login | — |
| 2 | Upload KAK | `parse_kak_pdf` → `create_pekerjaan` |
| 3 | Upload Kontrak | `parse_kontrak_pdf` → `update_pekerjaan` + `create_termin` + `create_milestones` |
| 4 | Upload Penawaran | `parse_penawaran_pdf` → `create_tenaga_ahli` + `assign_personil` |
| 5 | Upload RAB | `parse_rab_pdf` → `create_rencana_pengadaan` (bulk) |
| 6 | Invite vendor user | `invite_vendor_user` |
| 7 | Grant admin access | `grant_admin_access` |
| 8 | Monitor harian | `get_pekerjaan_detail` |
| 9 | Approve realisasi | `approve_realisasi` |
| 10 | Approve termin | `approve_termin` |
| 11 | Final BAST | `generate_bast` |
| 12 | Close project | `update_pekerjaan` (status=closed) |

### ADMIN — Subset Superadmin (sesuai grant)

- Setup proyek hanya yg di-grant
- Monitor + recommend approval; cuma Superadmin yg final approve termin

### VENDOR — Lifecycle Eksekusi (Day 1 → Day 30)

| # | Step | Tool yang Dipanggil |
|---|---|---|
| 1 | Login (panel vendor) | — |
| 2 | Lihat tugas | `get_my_pekerjaan` |
| 3 | Daily report survey | `submit_daily_report(tipe=survey_awal)` |
| 4 | Daily report sondir | `submit_daily_report(tipe=sondir)` |
| 5 | Daily report bor log | `submit_daily_report(tipe=bor_log)` |
| 6 | Day 5: Laporan Pendahuluan | `generate_laporan_pendahuluan` |
| 7 | Day 20: simulasi PLAXIS | `submit_daily_report(tipe=simulasi)` |
| 8 | Day 28: rekomendasi | `submit_daily_report(tipe=rekomendasi)` |
| 9 | Day 30: Laporan Akhir | `generate_laporan_akhir` |
| 10 | Invoice | `generate_invoice(prestasi=30)` |
| 11 | Lampiran (auto) | `generate_kuitansi_gaji` + `generate_invoice_atk` + dll |
| 12 | Surat permohonan | `generate_surat_permohonan_pembayaran` |
| 13 | Submit termin | `request_termin` |
| 14 | TTD final docs | `apply_signature` |

### STAFF — Subset Vendor (1 proyek scope)

- Submit daily report only
- Tidak bisa trigger generate Laporan/Invoice (vendor head only)

---

## AI Guardrails (Anti-Hallucination — Lock This)

1. **Konfirmasi sebelum write action.** Semua tool yang ubah data harus konfirmasi user dulu kecuali user explicit "yes do it"
2. **Cek role permission per tool call.** Vendor gak boleh trigger `create_pekerjaan`. Karta block, gak silent skip.
3. **Cross-reference wajib.** Sebelum generate Invoice, cek RAB ada. Sebelum generate Laporan Akhir, cek daily reports cukup.
4. **No fake data.** Kalau Karta gak tahu jawaban, balas "Saya tidak tahu" — bukan ngarang.
5. **Audit trail wajib.** Setiap tool call log siapa, kapan, input apa, output apa.
6. **File path validation.** Tool yang baca file harus validate path sebelum buka. Tolak path di luar storage.
7. **Rate limit.** Generate tools (laporan/invoice) max 5x per jam per user (anti spam).
8. **Dokumen Laporan & Invoice WAJIB di-generate, NEVER di-upload.** User upload kategori ini = reject.

---

## Implementation Status (per 2026-05-17)

### ✅ Done
- Database schema (pekerjaan, perusahaan, tenaga_ahli, dll)
- Vendor panel terpisah
- AI chat orchestrator + tool calling loop
- 10 read tools (overview, list, detail, harian, personil, milestone, termin)
- 3 write tools (update_progres, tandai_milestone, approve_termin)
- 4 new tools (this session): parse_kak_pdf, parse_kontrak_pdf, parse_rab_pdf, generate_invoice (basic PDF)
- 72 proyek real data imported from Excel
- 16 vendors, 254 tenaga ahli, 287 personil assignments

### ❌ To Build (27 tools — see KARTA_ROADMAP.md)

Refer to **KARTA_ROADMAP.md** for the prioritized task queue with dependencies & estimates.

---

## Locked Promises (gua refer balik ke ini)

- 4 roles definisi locked
- 6 kategori dokumen + ownership locked
- Laporan & Invoice selalu GENERATED, never uploaded
- Sebelum nambahin tool atau ngubah arsitektur, refer dokumen ini
