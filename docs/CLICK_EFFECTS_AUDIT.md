# 🎯 Audit: Click → Effect di Karta

Inventaris semua interaksi yang punya **after-click effect** (modal, dropdown, drawer, toast). Tracking apakah sudah ke-capture di guidebook PDF.

**Status legend:**
- ✅ Sudah ada di PDF
- ⏳ Belum di-capture (priority)
- ⏸ Optional / lower priority
- 🐛 Capture gagal (technical limit)

---

## 1. Dashboard — Action Buttons

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| 📑 **Import Kontrak** btn | Modal popup upload PDF | 🐛 | `04-import-kontrak-modal.png` (only dashboard, modal didn't render in Playwright headless) |
| 📤 **Export Data** btn | Modal popup pilih jenis export | 🐛 | `05-export-data-modal.png` (same issue) |
| 🗓️ **Kalender** btn | Modal calendar slide in | ✅ | `06-calendar-modal.png` |

## 2. Calendar Modal Interactions

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| `‹` Prev month btn | Calendar geser ke bulan sebelumnya | ⏳ | – |
| `Hari Ini` btn | Loncat ke bulan sekarang | ⏳ | – |
| `›` Next month btn | Calendar geser ke bulan berikutnya | ✅ | `06b-calendar-next-month.png` |
| `×` Close btn | Tutup modal | – | (covered by ESC concept) |
| Klik area gelap di luar modal | Tutup modal | – | – |

## 3. AI Chat (Hero & Floating)

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| Quick suggestion chip (4 tombol) | Auto-fill input box | ⏳ | – |
| Send btn (▶) atau Enter | User bubble muncul instant + typing dots | ✅ | `03b-chat-user-bubble-instant.png` |
| Tunggu AI respond | Bubble assistant muncul | ✅ | `03c-chat-ai-response.png` |
| Floating chat ikon (kanan-bawah) | Drawer slide in dari kanan | ✅ | `17b-floating-chat-open.png` |
| `×` Close drawer | Drawer hilang | – | – |

## 4. Kanban Board

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| Filter button (Bidang) | Kanban refresh dengan filter | ✅ | `07b-kanban-filter-jalan.png` |
| Filter "Semua" | Reset filter | – | (implied) |
| Card click | Modal detail popup | ✅ | `07c-kanban-card-modal.png` |
| Modal **Tutup** | Modal hilang | – | – |
| Modal **✏️ Edit** | Navigate ke `/admin/pekerjaans/{id}/edit` | ⏳ | – |
| Modal **📂 Buka Detail Lengkap** | Navigate ke `/admin/pekerjaans/{id}` | ✅ | `10-pekerjaan-detail.png` |
| ESC | Tutup modal | – | – |

## 5. Sidebar

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| **Master Data** group label | Expand/collapse children | ✅ | `08-sidebar-collapsed.png` & `08b-sidebar-expanded.png` |
| **Pengaturan** group label | Expand/collapse children | ✅ | (included in 08b) |
| Menu item click | Navigate to page | – | (implied per page screenshot) |

## 6. User Menu (Avatar SA)

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| Avatar bulat SA | Dropdown menu open | ✅ | `19-logout-menu.png` |
| **Keluar** | Logout → redirect ke `/admin/login` | – | – |
| Light/Dark/System theme btn | Theme switch | ⏳ | – |

## 7. Pekerjaan Detail — 7 Tabs

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| Tab **Personil** | Show personil table | ⏳ | only header captured |
| Tab **Vendor** | Show vendor list | ⏳ | – |
| Tab **Rencana Pengadaan** | Show rencana table | ⏳ | – |
| Tab **Realisasi Pengadaan** | Show realisasi table | ⏳ | – |
| Tab **Dokumen** | Show dokumen list | ⏳ | – |
| Tab **Termin Pembayaran** | Show termin table | ⏳ | – |
| Tab **Milestone & Jadwal** | Show milestone list | ⏳ | – |

## 8. Tabel Action Buttons

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| `Tambah` btn (header) | Open Create form page | ⏳ | – |
| `Edit` (row action) | Open Edit form page | ⏳ | – |
| `Hapus` (row action) | Confirmation modal | ⏳ | – |
| `Setujui` di Termin Pembayaran | Confirmation modal | ⏳ | – |
| `Tolak` di Termin Pembayaran | Form input alasan | ⏳ | – |
| `Bayar` di Termin Pembayaran | Confirmation + tanggal otomatis | ⏳ | – |
| `Tandai Selesai` di Milestone | Confirmation | ⏳ | – |
| `Verify` di Realisasi Pengadaan | Confirmation modal | ⏳ | – |
| `Reject` di Realisasi | Form alasan | ⏳ | – |
| `Ajukan` di Termin | Validate progres → success/warning | ⏳ | – |

## 9. Form Interactions

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| Dropdown **Bidang** click | Show options | ⏳ | – |
| `+` Quick-add di dropdown Jenis Pekerjaan | Mini modal new entry | ⏳ | – |
| `+` Quick-add di dropdown Perusahaan | Mini modal | ⏳ | – |
| `+` Quick-add di dropdown Tenaga Ahli (di tab Personil) | Mini modal | ⏳ | – |
| Field validation error | Red text + border | ⏳ | – |
| Submit valid form | Toast success + redirect | ⏳ | – |
| Submit invalid form | Validation errors highlighted | ⏳ | – |

## 10. Filters & Tabs in List Page

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| Tab **Semua / Tahun 2026 / Sedang Berjalan / Deadline Dekat / Terlambat / Selesai** | Filter table | ⏳ | – |
| Filter dropdown (sticky right) | Show filter panel | ⏳ | – |
| **Simpan Filter sebagai Preset** | Modal input nama preset | ⏳ | – |
| Apply preset tab (⭐ marked) | Filter ter-apply | ⏳ | – |

## 11. Vendor Portal — Mobile

| Click | Effect | Status | Screenshot |
|---|---|---|---|
| **Submit Laporan Harian** menu | Open form | ⏳ | – |
| Foto button | Camera open (mobile) | ⏳ | – |
| GPS request prompt | Browser permission prompt | ⏳ | – |
| Submit btn | Loading + toast success | ⏳ | – |
| **Input Realisasi** menu | Open realisasi form | ⏳ | – |

## 12. Toast Notifications

| Trigger | Effect | Status |
|---|---|---|
| Save success | Green toast top-right | ⏳ |
| Save error / validation | Red toast | ⏳ |
| AI action confirm | Info toast | ⏳ |
| Bulk delete | Confirmation dialog | ⏳ |

## 13. Logout Flow

| Click | Effect | Status |
|---|---|---|
| Avatar → **Keluar** | POST `/admin/logout` → redirect | ⏳ |
| Tombol **Keluar** di AccountWidget | Same | ⏳ |

---

# 📊 Summary

- **Total interactions identified:** ~50+
- **Already in PDF:** 13 (~26%)
- **Priority untuk capture:** ~25 (50%)
- **Optional/lower priority:** ~12 (24%)

## Yang Paling Penting Untuk Ditambah ke PDF

🔴 **Critical (sales/onboarding impact):**
1. Import Kontrak modal (KEY differentiator) — 🐛 Filament action modals tidak render di headless Playwright
2. Export Data modal — same issue
3. Pekerjaan Detail tab content (Personil, Termin, Milestone) — show domain depth
4. Quick-add `+` modal di dropdown form — show low-friction onboarding

🟡 **High value (workflow demo):**
5. Termin "Setujui" confirmation flow
6. Bulk import (template download) flow
7. Vendor portal "Submit Laporan" flow with foto
8. List page filter tabs in action

🟢 **Nice to have:**
9. Toast notifications
10. Theme switcher
11. Form validation states

## Technical Solutions untuk Filament Modal Capture

**Option A:** Run Playwright in non-headless mode dengan xvfb (Linux only, atau via WSL)

**Option B:** Use Filament's `mountAction()` URL params kalau supported, e.g. `?mountAction=importKontrak`

**Option C:** Patch Karta untuk add `?showModal=importKontrak` query param yang force-mount modal saat page load (debug-only feature)

**Option D:** Capture manually via real browser DevTools, save PNG manual, commit ke repo

**Option E:** Buat custom action yang built-in dispatch `open-modal` event handled by JS yang sama-sama mount.

Saya rekomendasikan **Option D** untuk sekarang (manual screenshot via real browser), karena paling cepat dan reliable. Untuk batch capture future, build **Option B/C** sebagai infrastructure.
