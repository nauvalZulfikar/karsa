# 📖 Panduan Pengguna Karta

**Cara Pakai Karta dalam Sehari Kerja**

> Tutorial ini ditulis dalam format cerita — ikuti dari pagi sampai sore, kamu akan paham 90% fitur Karta tanpa perlu hafal menu.

**🔗 Akses:** https://karta.aureonforge.com
**📧 Login default:** `admin@dputr.go.id` / `password` *(ganti segera setelah login pertama)*

---

# 🌅 06:30 — Mulai Hari Sebelum Buka App

## Notifikasi WhatsApp Otomatis

Sebelum bangun tidur, HP kamu bunyi. WhatsApp dari **Karta Bot**:

```
📊 Ringkasan Mingguan
Periode: 5 – 11 Mei 2026

🚦 Status Proyek (total 72)
🟢 Aman: 12 • 🟡 Waspada: 5 • 🔴 Kritis/Terlambat: 27
✅ Selesai minggu ini: 3

⚠️ Perlu Perhatian:
• Rehabilitasi Jalan Soreang — terlambat 5 hari
• Drainase Margahayu — deadline 3 hari lagi, progres 71%

💰 4 termin menunggu approval PPK

Buka dashboard untuk detail.
```

### Cara Setup
- WA digest dikirim **otomatis tiap Senin pagi 07:00**
- Wajib: isi `no_telp` di profile user (Pengaturan → Pengguna → Edit)
- Wajib: super_admin set `WA_GATEWAY_TOKEN` di System Settings

### Buat Apa
- Tahu prioritas hari ini **tanpa perlu buka aplikasi**
- Bisa rencana mental sambil sarapan
- Tidak ada lagi excuse "saya gak tau ada deadline minggu ini"

---

# 🏢 07:45 — Sampai Kantor, Buka Karta

## Login & Auto Status Report

Buka https://karta.aureonforge.com → otomatis redirect ke `/admin/login` → masukkan email + password.

**Dashboard pertama yang muncul:**

```
┌─────────────────────────────────────────────┐
│ [SA] Selamat Datang Super Admin    [Keluar] │
├─────────────────────────────────────────────┤
│ [📑 Import Kontrak] [📤 Export] [🗓️ Kalender]│
├─────────────────────────────────────────────┤
│ 🟧 Apa yang bisa saya bantu hari ini?       │
│                                              │
│ 💬 Halo Super Admin! Saya asisten AI DPUTR │
│ 📊 Status Hari Ini · Senin, 5 Mei 2026     │
│   Total: 72 • Aktif: 59 • Selesai: 13       │
│   🟢 12  🟡 5  🔴 0  ⛔ 27  📋 32          │
│                                              │
│   ⚠️ Perlu Perhatian                        │
│   • Rehabilitasi Jalan Soreang — terlambat 5│
│   • Drainase Margahayu — 3 hari lagi        │
│                                              │
│ [Berapa proyek kritis] [Tampilkan laporan]  │
│ [Ringkasan dashboard] [Termin menunggu]    │
│ [Ketik pertanyaan...]                  [▶]  │
├─────────────────────────────────────────────┤
│ 📋 Papan Pekerjaan 2026                     │
│ [Backlog] [Aman] [Waspada] [Kritis] [Tlmbt]│
└─────────────────────────────────────────────┘
```

### Yang Pertama Kamu Lihat
1. **AccountWidget** — sapaan + tombol **Keluar** (logout di sini!)
2. **3 Action Buttons** — Import Kontrak, Export Data, Kalender
3. **AI Chat Hero** — full width, langsung ada greeting + status report otomatis
4. **Kanban Board** — Trello-style, 6 kolom workflow
5. **Stats di bawah** — overview, traffic light, chart, tabel laporan

---

# 💬 08:00 — Tanya AI Apa Saja

## Cara #1 (Paling Cepat): Pakai AI Chat

Kamu **gak perlu hafal menu**. Tinggal ngomong ke chat.

### Tipe A: Tanya Informasi (Langsung Dijawab, ~5-10 detik)

Ketik di chat hero atau klik tombol cepat:

```
"berapa proyek kritis hari ini?"
"detail proyek nomor 23"
"info pekerjaan jalan soreang"
"laporan harian hari ini"
"siapa personil di proyek drainase margahayu?"
"milestone proyek nomor 18"
"termin yang masih draft"
```

**Contoh respons:**
> 🤖 *"Rehabilitasi Jalan Soreang–Ciwidey (id: 23)*
> *Vendor: PT Karya Maju Jaya*
> *Nilai kontrak: Rp 2.450.000.000*
> *Progres: 62% — terlambat 5 hari kerja*
> *Personil aktif (4):*
> *• Muhammad Syauqi — Team Leader*
> *• Deddy Saputra — Asisten TA*
> *• Ipan Agus — Surveyor"*

### Tipe B: Minta Action (Konfirmasi Dulu)

```
"update progres jalan soreang jadi 75%"
"approve termin 2 drainase margahayu"
"milestone mobilisasi proyek 23 sudah selesai"
```

**Flow:**
1. Kamu ketik perintah
2. AI balas: *"Konfirmasi: ... (ya/tidak)"*
3. Ketik **"ya"** → AI eksekusi
4. AI: *"✅ Berhasil. ..."*

### Tipe C: Floating Chat di Halaman Lain

Pindah halaman manapun (Master Data, Pengaturan) → ikon chat **teal** di pojok kanan-bawah → klik buka drawer.

---

# 🎯 08:30 — Brief ke Kabid (atau Atasan)

## Skenario: Atasan tanya status proyek mendadak

Daripada panik buka file Excel, langsung tanya AI:

> 👨 *"detail termin drainase margahayu"*

> 🤖 *"Drainase Margahayu — 4 termin*
> *• Termin 1 (uang muka 30%): ✅ Dibayar 12 Maret*
> *• Termin 2 (60% progres): ✅ Disetujui PPK, menunggu pencairan*
> *• Termin 3 (PHO): ⏸ Belum diajukan, syarat progres 100%*
> *• Termin 4 (FHO): ⏸ Belum diajukan*
> *Progres saat ini: 71%"*

Jawab atasan dengan confidence: *"Pak, progres 71%, target 100% Jumat ini. Termin 2 sudah disetujui."*

**Tools yang dipakai:** AI Chat dengan tool `get_termin_pekerjaan`.

---

# 📑 09:00 — Vendor Baru Kirim PDF Kontrak

## Skenario: Email masuk dengan PDF kontrak baru

**Old way:** Buka Excel, input manual nama proyek, no SPK, nilai, tanggal, vendor, termin satu-satu. ~25-30 menit.

**Karta way:**

### Step 1: Klik 📑 Import Kontrak
Tombol amber di dashboard, sebelah Export Data.

### Step 2: Upload PDF
Modal popup → upload file PDF (max 10 MB, harus PDF digital, bukan scan).

### Step 3: Klik "Proses Dokumen"
Tunggu ~10-15 detik. AI baca dokumen, ekstrak field.

### Step 4: Notifikasi Sukses
> ✅ *Berhasil dibaca. Ditemukan 4 termin pembayaran & 7 milestone.*

### Step 5: Auto-redirect ke Form Tambah Pekerjaan
Form sudah terisi otomatis:
- ✅ Nama pekerjaan
- ✅ No SPK + tanggal
- ✅ No SPMK + tanggal
- ✅ Nilai pagu + nilai kontrak
- ✅ Tanggal mulai + akhir
- ✅ Hari kerja + satuan waktu
- ✅ Vendor (kalau sudah ada di database) atau notif *"belum ada, mau buat baru?"*

### Step 6: Lengkapi yang Kosong
Yang AI **tidak bisa tebak** dan harus manual:
- **Bidang** (Bangunan Gedung / Jalan / Drainase / dll) — pilih dari dropdown
- **Status** (default: Belum Mulai) — kalau sudah jalan, ganti ke Proses Desain

### Step 7: Klik Simpan
Notifikasi:
> ✅ *Berhasil membuat 4 termin & 7 milestone dari kontrak.*

Total waktu: **~90 detik.**

---

# 📦 09:30 — Bulk Import dari Excel (Setup Awal)

## Skenario: Migration dari Excel lama

Kalau punya Excel berisi puluhan/ratusan proyek (`1_PEKERJAAN 2026.xlsx`), gunakan AI parser untuk Excel, atau gunakan Import Kontrak satu-satu untuk PDF.

### Untuk Master Data Bulk:
1. Sidebar → **Master Data** ▶ → pilih (Bidang/Jenis Pekerjaan/Perusahaan/Tenaga Ahli/Hari Libur)
2. Klik **Tambah** → isi → simpan, atau
3. Untuk import banyak: super_admin bisa pakai script `import_excel.php` (developer-only)

### Untuk Quick-Add saat Buat Pekerjaan:
- Form Tambah Pekerjaan → dropdown **Jenis Pekerjaan** → klik ikon `+` di pojok
- Mini modal muncul → isi nama jenis baru → save → otomatis ter-pilih
- Sama untuk dropdown **Perusahaan** dan **Tenaga Ahli** (di tab Personil)

---

# 📷 10:15 — Vendor di Lapangan: Submit Laporan Harian

## Skenario: Vendor selesai pekerjaan harian, perlu lapor

### Setup Vendor User (Sekali Aja, by Admin)
1. Sidebar → **Pengaturan** ▶ → **Pengguna**
2. Tambah → isi nama, email, password
3. **Role:** vendor
4. **Perusahaan:** pilih dari dropdown (vendor harus link ke 1 perusahaan)
5. Save → kasih credential ke vendor

### Vendor Pakai Portal Sendiri
1. Vendor buka https://karta.aureonforge.com/vendor (auto-redirect ke `/vendor/login`)
2. Login pakai akun vendor mereka
3. UI mobile-friendly, tombol besar
4. Menu **Submit Laporan Harian:**
   - 📷 **Foto** (langsung kamera HP)
   - 📍 **GPS** auto-capture (kalau diizinkan browser)
   - **Pilih jenis:** masuk / pulang / progress
   - **Catatan:** apa yang dikerjain hari ini
   - Submit ✓

**Selesai dalam ~30 detik.** Foto + GPS + timestamp tersimpan, **gak bisa dipalsukan** (audit-grade evidence).

### Admin Lihat Laporan
- Sidebar → **Laporan Harian** → tabel dengan foto thumbnail + lokasi GPS + catatan
- Atau via chat: *"laporan harian hari ini"*

---

# 💰 11:00 — PPK Approve Termin

## Skenario: Vendor ajukan termin pembayaran, PPK harus approve

### Step 1: Vendor (atau Admin) Ajukan Termin
1. Buka detail Pekerjaan → tab **Termin Pembayaran**
2. Klik **Tambah** untuk bikin termin (kalau belum ada)
3. Isi: nomor termin, nama, nilai (Rp), syarat progres (%)
4. Klik tombol **Ajukan** di baris termin status `draft`
5. Sistem cek: progres pekerjaan ≥ syarat?
   - ❌ Kurang → muncul warning
   - ✅ Cukup → status berubah `diajukan`

### Step 2: PPK Terima Notif WA
> 💰 *Termin baru menunggu approval:*
> *Termin 2 Drainase Margahayu — Rp 600.000.000*

### Step 3a: PPK Approve via Chat AI (Cara Cepat)
Login → di chat:
> 👨 *"approve termin 2 drainase margahayu"*

> 🤖 *"Konfirmasi approval:*
> *• Termin 2 Drainase Margahayu*
> *• Nilai: Rp 600.000.000*
> *• Vendor: PT Karya Maju Jaya*
> *• Syarat: progres 60% (terpenuhi: 71%)*
> *Setuju approve? (ya/tidak)"*

> 👨 *"ya"*

> 🤖 *"✅ Termin 2 disetujui pada 5 Mei 2026."*

### Step 3b: Atau Manual via UI
- Detail Pekerjaan → tab Termin → klik **Setujui** di baris termin `diajukan`
- Modal isi catatan PPK → submit
- Status berubah `disetujui` + tanggal otomatis

### Step 4: Bendahara Tandai Sudah Dibayar
- Login Bendahara → klik **Bayar** di baris termin `disetujui`
- Status berubah `dibayar` + tanggal bayar otomatis

---

# 📊 13:00 — Cek Laporan Harian

## Skenario: Admin mau cek siapa yang sudah/belum laporan

### Via Chat (Cepat)
> 👨 *"laporan harian hari ini siapa aja yang udah masuk"*

> 🤖 *"Hari ini ada 5 laporan:*
> *• 09:30 — Drainase Margahayu (Bu Sari)*
> *• 09:45 — Jalan Soreang (Pak Adit)*
> *• 10:17 — Drainase Margahayu (lapis bawah selesai)*
> *• 10:30 — DED Jembatan Ciwidey (Pak Tono)*
> *• 10:50 — Rehabilitasi Trotoar (Bu Lina)*
>
> *3 vendor lain belum laporan hari ini."*

### Via Menu
- Sidebar → **Laporan Harian** → daftar dengan filter tanggal/vendor/jenis

---

# 🔍 14:30 — Investigasi Data Anomali

## Skenario: "Kenapa progres proyek X tiba-tiba turun?"

Audit Trail otomatis catat semua perubahan.

### Step 1: Buka Audit Trail
Sidebar → **Pengaturan** ▶ → **Audit Trail**

### Step 2: Filter
- Aksi: **Diubah**
- Objek: **Pekerjaan**
- Tanggal: kemarin
- ID atau search nama proyek

### Step 3: Lihat Detail Perubahan
Klik baris yang ingin diinvestigasi → modal popup:
> *5 Mei 13:42 — Pak Tono (PPTK) updated `progres_persen` dari 60.00 → 45.00*

Sekarang kamu tahu **siapa yang ubah, kapan, dari berapa ke berapa**.

### Step 4: Konfirmasi & Fix (Bisa via Chat)
> 👨 *"update progres DED Jembatan Ciwidey jadi 65%"*

> 🤖 *"Konfirmasi update progres dari 45% ke 65%? (ya/tidak)"*

> 👨 *"ya"* → ✅ Fixed.

---

# 🗓️ 15:00 — Lihat Kalender Bulan Ini

## Skenario: Plan resource alokasi minggu depan

### Klik 🗓️ Kalender di Dashboard
Modal popup nampilin:
- 🔴 **Hari Libur** (auto from master Hari Libur)
- 🎯 **Milestone proyek** (warna per bidang)
- ⏰ **Deadline pekerjaan** (merah mencolok)
- ⭐ **Hari Ini** highlight kuning

### Navigasi
- `‹` Prev Month
- `›` Next Month
- **Hari Ini** (jump back)

### Manfaat
- Liat deadline padat di minggu mana
- Plan alokasi tambahan personil ke proyek deadline minggu itu
- Avoid scheduling launch saat libur nasional

---

# 📋 16:00 — Filter Kanban per Bidang

## Skenario: Kabid Bidang Jalan mau lihat proyek divisi-nya saja

### Step 1: Scroll ke Papan Pekerjaan
Di dashboard, scroll ke widget **📋 Papan Pekerjaan**.

### Step 2: Klik Filter
Bar pill di atas kanban: 🌐 Semua · Bangunan Gedung · Drainase · Irigasi · Jalan
→ Klik **Jalan** → kanban refresh, cuma 18 cards Bidang Jalan tersisa.

### Step 3: Distribusi 6 Kolom Workflow
- 📋 **Backlog** — proyek belum mulai
- 🟢 **Aman** — progres on-track, deadline aman
- 🟡 **Waspada** — deadline mendekati
- 🔴 **Kritis** — sangat dekat deadline
- ⛔ **Terlambat** — sudah lewat tanggal akhir
- ✅ **Selesai** — proyek tuntas

### Step 4: Click Card → Modal Detail
Click card mana saja → modal popup:
- Nama, vendor, bidang, status badge
- 3 stat cards: Personil · Termin · Milestone
- Progress bar
- Field grid: Nilai, Tanggal, Sisa Hari
- 3 tombol: **Tutup** · **✏️ Edit** · **📂 Buka Detail Lengkap**

### Step 5: Buka Detail Lengkap
Klik **📂 Buka Detail Lengkap** → halaman view dengan **7 tab** horizontal:

| Tab | Isinya |
|---|---|
| **Personil** | Daftar tenaga ahli yang assigned + jabatan + honor |
| **Vendor** | Perusahaan terkait (many-to-many) |
| **Rencana Pengadaan** | List barang yang akan dipakai |
| **Realisasi Pengadaan** | Barang yang sudah terealisasi |
| **Dokumen** | File kontrak, BAST, laporan, dll |
| **Termin Pembayaran** | Termin + status approval |
| **Milestone & Jadwal** | Milestone dengan badge sumber (kontrak / AI generated) |

---

# 📤 17:00 — Export Data Sebelum Pulang

## Skenario: Mau kirim laporan mingguan ke Kabid via email

### Klik 📤 Export Data di Dashboard
Modal popup → pilih:
- **Pekerjaan Excel** (header biru, semua proyek + field lengkap)
- **Pekerjaan PDF** (landscape A4, ringkas)
- **Laporan Harian Excel** (header hijau)
- **Pengadaan Excel** (header kuning)
- **Termin Excel** (header ungu)

Pilih tahun anggaran → klik **Download** → file `.xlsx` atau `.pdf` ter-download.

Email ke Kabid: *"Update mingguan terlampir, Pak."* — selesai.

---

# 🌙 21:00 — Notifikasi Otomatis ke Bendahara

## Cron Job Sistem

Karta jalankan job otomatis di background:

| Jadwal | Notifikasi |
|---|---|
| **Senin 07:00** | Weekly digest ke admin_bidang + super_admin |
| Setiap hari 07:00 | Notif proyek deadline H-14, H-7, H-3 ke personil |
| Weekday 06:30 | Reminder vendor laporan masuk |
| Weekday 15:00 | Reminder vendor laporan pulang |
| Setiap hari 08:00 | Notif termin pending approval ke PPK |

**Syarat berfungsi:**
- `WA_GATEWAY_TOKEN` di System Settings
- User punya `no_telp` di profile

---

# 📌 Sidebar Cheat Sheet

```
📊 Dasbor                  ← landing page (90% kerjaan di sini)
📝 Laporan Harian          ← lihat semua laporan vendor

📚 Master Data ▶           (klik untuk expand)
  🏢 Bidang
  📦 Jenis Pekerjaan
  🏗️  Perusahaan
  🚦 Status Pekerjaan
  👷 Tenaga Ahli
  🗓️  Hari Libur

⚙️  Pengaturan ▶           (klik untuk expand)
  👥 Pengguna
  ⚙️  Pengaturan Sistem
  📜 Audit Trail
```

**Hidden tapi tetap accessible** (via card click di Kanban atau direct URL):
- 💼 Pekerjaan list (akses via card kanban)
- 💰 Termin Pembayaran (akses via tab pekerjaan)
- 📁 Dokumen (akses via tab pekerjaan)
- 📦 Rencana/Realisasi Pengadaan (akses via tab pekerjaan)
- 🎯 Milestone (akses via tab pekerjaan)
- 📥 Import Data (akses via tombol dashboard)
- 📤 Export Laporan (akses via tombol dashboard)

---

# 🎓 Quick Wins untuk User Baru

## Hari Pertama (5 Menit Latihan)

Login → langsung ketik di chat hero:

1. *"berapa total proyek?"*
2. *"daftar proyek aktif"*
3. *"detail proyek nomor 1"*
4. *"milestone proyek nomor 1"*
5. *"laporan harian hari ini"*
6. *"update progres proyek nomor 1 jadi 50%"* → ketik *"ya"*

Kalau 6 ini jalan, kamu sudah pakai 80% kekuatan Karta.

## Hari Pertama untuk Admin

1. ✅ Login → ganti password
2. ✅ Pengaturan → Pengaturan Sistem → set nama instansi, tahun anggaran, jam operasional
3. ✅ Master Data → Bidang → tambah/edit sesuai kebutuhan instansi
4. ✅ Master Data → Perusahaan → tambah vendor utama
5. ✅ Pengguna → tambah staff lain (admin_bidang, pptk, ppk, viewer)
6. ✅ Buat 1 vendor user → kasih credential ke vendor → minta mereka login & coba laporan harian

## Hari Pertama untuk Vendor

1. ✅ Buka https://karta.aureonforge.com/vendor di HP
2. ✅ Login pakai credential dari admin
3. ✅ Submit Laporan Harian pertama (foto + catatan)
4. ✅ Banner "Install di home screen" muncul → klik Install (PWA)
5. ✅ Buka dari home screen, gunakan tiap hari

---

# 🆘 Quick Troubleshooting

| Gejala | Solusi |
|---|---|
| Chat balas *"OPENAI_API_KEY belum dikonfigurasi"* | Admin: isi `OPENAI_API_KEY` di `.env`, restart server |
| Import Kontrak gagal *"tidak dapat dibaca"* | PDF hasil scan → harus PDF digital (Word export) |
| Vendor login error | Cek user role = `vendor` & link ke perusahaan |
| Notif WA gak ngirim | Cek `WA_GATEWAY_TOKEN` & `no_telp` di profile user |
| Halaman 500 error | Admin: cek `storage/logs/laravel-*.log` |
| Lupa cara | **Tanya AI** — itulah gunanya chat hero |
| Logout gak nemu | Avatar **SA** pojok kanan-atas → **Keluar** (atau tombol Keluar di AccountWidget) |

---

# 💡 Filosofi Karta

> **Daripada belajar tool baru, suruh aja tool-nya kerjain.**
> Daripada input data manual, upload aja dokumen yang udah ada.
> Daripada kirim email yang gak dibuka, kirim WA yang langsung dibaca.
> Daripada vendor pakai akun guest yang clunky, kasih portal sendiri.
> Daripada ngeset semua dari nol, pilih template industri yang udah kebayang.
>
> **= Karta.**

---

# 📞 Bantuan Lebih Lanjut

- **In-app:** Tanya chat AI — *"gimana cara X?"*
- **Dokumentasi:** [DEPLOY.md](./DEPLOY.md) untuk setup teknis
- **Roadmap:** [KARSA_DEVELOPMENT_PLAN.md](./KARSA_DEVELOPMENT_PLAN.md) untuk fitur masa depan

---

**Hari kerja yang baik = pulang tepat waktu dengan semua urusan beres.**

Karta bikin itu jadi default, bukan kebetulan.

🎉 Selamat menggunakan Karta!
