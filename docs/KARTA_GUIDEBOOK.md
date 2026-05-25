# 🌅 06:30 — Bangun, Cek WA Digest (WhatsApp Digest Mingguan)

Alarm bunyi jam 6 pagi. Pak Dimas masih di kasur. Belum sempat duduk, HP sudah berdering — bukan telepon, tapi notifikasi WhatsApp dari **Karta Bot**:

```
📊 Ringkasan Mingguan — Periode 5–11 Mei 2026

🚦 Status Proyek (total 72)
🟢 Aman: 12 • 🟡 Waspada: 5 • 🔴 Kritis/Terlambat: 27
✅ Selesai minggu lalu: 3

⚠️ Perlu perhatian:
• Rehabilitasi Jalan Soreang — terlambat 5 hari
• Drainase Margahayu — deadline 3 hari lagi, progres 71%
• DED Jembatan Banjaran — terlambat 8 hari

💰 4 termin menunggu approval PPK
```

Pak Dimas baca sambil masih tiduran. Sudah tau prioritas hari ini sebelum mandi.

### 🛠️ Cara Setup
- Admin: isi `WA_GATEWAY_TOKEN` di **Pengaturan → Pengaturan Sistem**
- User: pastikan `no_telp` di profile sudah benar
- Cron otomatis kirim **Senin pagi 07:00**

### 💡 Manfaat
- Tau prioritas tanpa perlu buka aplikasi
- Bisa rencana mental sambil sarapan
- Tidak ada lagi excuse "saya gak tau ada deadline"

---

# 🏢 07:45 — Sampai Kantor, Login (Login & Dashboard)

## Login Page

Pak Dimas buka https://karta.aureonforge.com → otomatis redirect ke `/admin/login`. Card login putih dengan judul **"DPUTR Project Management"** (formal name) + sub-judul **"Masuk ke akun Anda"**. Isinya: field **"Alamat email *"** (wajib, border oranye saat fokus), field **"Kata sandi *"** (wajib) dengan ikon **"Tampilkan kata sandi"** (toggle show/hide), checkbox **"Ingat saya"**, dan tombol oranye full-width **"Masuk"**. Ikon chat teal melayang di pojok kanan-bawah (Floating Chat — dibahas di Bab 4).

![Login DPUTR PM — card putih dengan judul "DPUTR Project Management" + "Masuk ke akun Anda", field "Alamat email *" (border oranye saat fokus) & "Kata sandi *" (dengan tombol "Tampilkan kata sandi" / toggle eye-icon), checkbox "Ingat saya" off, tombol oranye full-width "Masuk", plus ikon chat-bubble teal di pojok kanan-bawah viewport](./screenshots/01-login-page.png)

Isi credential → klik **Masuk** → langsung redirect ke dashboard.

> ⚠️ **Wajib ganti password setelah login pertama** — production live di internet, jangan biarkan default. Hubungi sysadmin untuk credential awal.

## Dashboard — Landing Page

Setelah login, dashboard ("Dasbor") langsung tampil. Bagian atas terdiri dari **5 area** yang ke-stack vertikal:

![Dashboard Top viewport — topbar gelap dengan field "Pencarian global" + avatar bulat hitam "SA" pojok-kanan. Body: kotak putih AccountWidget (avatar SA + "Selamat Datang / Super Admin" + tombol outline "Keluar"). Di bawahnya row tombol action: "📑 Import Kontrak" (oranye solid, fokus border) · "📤 Export Data" (outline) · "🗓️ Kalender" (outline). Lalu banner Chat Hero full-width oranye: "Apa yang bisa saya bantu hari ini?" + sub "Tanya, atau minta saya update data — saya akan konfirmasi dulu". Bubble assistant pertama: "Halo Super Admin! Saya asisten AI DPUTR. Coba: 'berapa proyek kritis hari ini?' atau 'update progres jalan soreang jadi 75%'." Diikuti bubble status report (📊 Status Hari Ini · Rabu, 20 Mei 2026 / 📋 Ringkasan Proyek 2026 / Status Waktu / Perlu Perhatian / Hari Ini). 4 chip quick-suggestion abu-abu di atas input box ("Ketik pertanyaan, atau drag PDF/Excel ke sini…") + tombol Send bulat oranye dengan icon AI](./screenshots/02b-dashboard-top.png)

**Dari atas ke bawah:**

1. **AccountWidget** — sapaan "Selamat Datang" + nama user + tombol **Keluar** (logout shortcut)
2. **3 Action Buttons** — `📑 Import Kontrak` · `📤 Export Data` · `🗓️ Kalender` (dibahas di Bab 5, 12, 10)
3. **AI Chat Hero** — full width, banner oranye "Apa yang bisa saya bantu hari ini?" + bubble status report otomatis
4. **Quick Suggestion Buttons** — 4 pertanyaan cepat (chip abu-abu di atas input)
5. **Input Box Chat** — placeholder "Ketik pertanyaan atau perintah…" + tombol Send oranye

### Status Report Otomatis (di Chat Hero)

Di dalam chat hero, ada **bubble assistant pertama** yang otomatis muncul tiap kali buka dashboard. Isinya snapshot real-time dari proyek hari ini:

```
📊 Status Hari Ini · Senin, 5 Mei 2026
──────────────────────────────

📋 Ringkasan Proyek 2026
Total: 72 • Aktif: 59 • Selesai: 13

🚦 Status Waktu
🟢 Aman: 0   🟡 Waspada: 0   🔴 Kritis: 0
⛔ Terlambat: 27   ⏸ Backlog: 32

⚠️ Perlu Perhatian
• Perencanaan Teknik Pembangunan Drainase — terlambat 41 hari
• DED Jalan Kabupaten Wilayah Ciwidey — terlambat 25 hari

📝 Hari Ini
• 0 laporan harian masuk
```

> 💡 Angka di atas adalah contoh; widget refresh otomatis tiap kali dashboard di-load. Detailnya ada di `app/Livewire/AiChatHero.php`.

### Full Dashboard (scroll ke bawah)

Setelah area atas, scroll terus untuk lihat 5 widget tambahan:

![Dashboard Full — full-page scroll urut dari atas: AccountWidget + 3 tombol Action + Chat Hero. Lalu widget "📋 Papan Pekerjaan 2026" (sub "Klik card untuk lihat detail"), 4 kolom terlihat di viewport (Backlog 0 / Aman 0 / Waspada 0 / Kritis 0, semua "— Kosong —") — Terlambat (4 card) & Selesai (3 card) di-scroll horizontal. Kemudian 4 Stats Cards row: Proyek Aktif=2 / Total Nilai Kontrak Aktif=Rp 0 / Laporan Hari Ini (Pending)=0 / Termin Menunggu Persetujuan=0 — masing-masing dengan subtitle keterangan. Lalu 6 Traffic Light Cards 3x2 grid: Aman 0 / Waspada 0 / Kritis 0 (atas), Terlambat 4 / Selesai 3 / Belum Mulai 0 (bawah) — semua punya subtitle (Progres sesuai jadwal / Mendekati batas waktu / Sangat mendekati deadline / Melewati tanggal kontrak / Pekerjaan telah selesai / Tanggal mulai belum tercapai). Bar Chart "Distribusi Progres Pekerjaan" per range 0-25/26-50/51-75/76-100. Terakhir tabel "Laporan Vendor Hari Ini" — empty state "Tidak ada data yang ditemukan"](./screenshots/02-dashboard-fullpage.png)

- **Papan Pekerjaan Kanban** — 6 kolom (📋 Backlog · 🟢 Aman · 🟡 Waspada · 🔴 Kritis · ⛔ Terlambat · ✅ Selesai), card pekerjaan bisa diklik (lihat Bab 11). Di viewport 1440px sidebar-terbuka hanya 4 kolom pertama yang fit — scroll horizontal untuk Terlambat & Selesai
- **4 Stats Cards** — Proyek Aktif, Total Nilai Kontrak, Laporan Pending, Termin Menunggu Persetujuan
- **6 Traffic Light Cards** — Aman, Waspada, Kritis, Terlambat, Selesai, Belum Mulai (distribusi status semua proyek)
- **Bar Chart** — distribusi progres pekerjaan per range (0-25%, 26-50%, 51-75%, 76-100%)
- **Tabel Laporan Vendor Hari Ini** — daftar laporan harian yang masuk hari itu

### After-Click: Avatar Dropdown (Pojok Kanan Atas)

Klik bulat hitam **SA** (avatar Super Admin) di topbar → dropdown muncul di bawahnya:

![Avatar SA Dropdown — popover putih di bawah avatar pojok-kanan-atas, isi: "Super Admin" (label nama user, abu-abu), row 3 ikon theme (☀️ Light · 🌙 Dark · 🖥️ System — System aktif berwarna oranye), dan menu "Keluar" dengan ikon door-arrow](./screenshots/19-logout-menu.png)

3 area di dropdown:
- **Nama user** (Super Admin)
- **Theme switcher** — 3 ikon: ☀️ Light · 🌙 Dark · 🖥️ System (auto-follow OS)
- **🚪 Keluar** — logout (dibahas tuntas di Bab 13)

> ✨ Theme switcher reactive — klik ikon, seluruh UI langsung ganti warna tanpa reload.

---

# 💬 08:00 — Tanya AI Apa Saja (AI Chat Hero)

Pak Dimas sambil sruput kopi, ketik di chat hero:

> 👨 *"berapa proyek aktif?"*

![Chat Typing — input box di dashboard, border oranye saat fokus, isi text "berapa proyek aktif?" yang lagi diketik. Tombol Send oranye di kanan-bawah input](./screenshots/03a-chat-typing-question.png)

Tekan Enter — pesan kamu **langsung muncul instant** sebagai bubble amber di kanan, plus typing dots animasi:

![Chat — submit state: tombol Send berubah jadi loading spinner oranye, pesan user di-dispatch ke Livewire. Bubble user belum muncul instan di area chat (kerendering bareng response, ~1-2 detik kemudian)](./screenshots/03b-chat-user-bubble-instant.png)

~4-8 detik kemudian, AI balas:

![Chat AI Response — bubble user oranye "berapa proyek aktif?" di kanan, di bawahnya bubble AI putih outline "Saat ini terdapat **7 proyek aktif**." Quick suggestion chips dan input box kembali enabled](./screenshots/03c-chat-ai-response.png)

> 🤖 *"Saat ini terdapat 6 proyek aktif. Jika ada yang ingin Anda ketahui lebih lanjut, silakan beri tahu saya!"*

## 3 Tipe Perintah AI

### Tipe A: Tanya Informasi (Langsung Dijawab)

```
"berapa proyek kritis hari ini?"
"detail proyek nomor 23"
"info pekerjaan jalan soreang"
"laporan harian hari ini"
"siapa personil di proyek drainase margahayu?"
"milestone proyek nomor 18"
"termin yang masih draft"
```

### Tipe B: Minta Action (AI Konfirmasi Dulu)

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

Ada juga ikon chat **teal bulat** di pojok kanan-bawah halaman manapun:

![Floating Chat Button — di halaman Pekerjaan list, ikon chat-bubble di lingkaran teal solid (44x44 px) di pojok kanan-bawah viewport, posisi fixed, z-index tinggi](./screenshots/17-floating-chat-button.png)

Klik → drawer chat terbuka:

![Floating Chat Open — klik ikon teal → drawer chat 360x500 px terbuka di atas tombol (anchor bottom-right). Header teal + body putih dengan area pesan kosong + input "Ketik pertanyaan…" di bawah. Sama persis fungsinya dengan Chat Hero di Dashboard](./screenshots/17b-floating-chat-open.png)

Fungsinya sama persis dengan chat hero di dashboard — bisa pakai dari halaman manapun.

---

# 🎯 08:30 — Brief Mendadak ke Kabid (AI Quick Lookup)

Pak Dimas baru duduk, langsung ditembak Kabid:

> 👔 *"Pak Dimas, gimana proyek Drainase Margahayu? Mau di-PHO kapan?"*

Tanpa panik, sambil tetap natap muka Kabid, tangannya ngetik di chat Karta:

> 👨 *"detail termin drainase margahayu"*

5 detik kemudian:

> 🤖 *"4 termin. Termin 1 (uang muka 30%) sudah dibayar 12 Maret. Termin 2 sudah disetujui PPK, menunggu pencairan. Termin 3 (PHO) belum diajukan—syarat progres 100%. Progres saat ini 71%."*

Pak Dimas jawab Kabid dengan tenang: *"Pak, target 100% Jumat ini, jadi PHO bisa minggu depan. Termin 2 sudah disetujui Bu Ratna, tinggal nunggu Bendahara."*

Kabid manggut puas, lanjut topik lain.

> 💡 **Dulu** rapat dadakan kayak gini bikin grogi karena harus buka 3 file Excel dulu. Sekarang chat 1 baris sudah cukup.

---

# 📑 09:00 — Vendor Kirim PDF Kontrak (Import Kontrak PDF)

Email masuk dari kontraktor baru — *PT Sinar Konstruksi* — kirim PDF kontrak proyek "Pembangunan Jembatan Pasirjambu", 8 halaman penuh angka.

**Dulu** Pak Dimas akan luangkan 25 menit input manual ke Excel.

## Cara Pakai Import Kontrak

### Step 1: Klik Tombol "📑 Import Kontrak" di Dashboard

Tombol amber paling atas, di sebelah Export Data dan Kalender.

### Step 2: Modal Upload Muncul

Modal popup minta upload file PDF:
- Maksimal 10 MB
- **Wajib PDF digital** (bukan hasil scan/foto)
- Helper text: *"AI akan baca dokumen, ekstrak data, dan buka form Tambah Pekerjaan dengan field sudah terisi. Tinggal review & simpan."*

### Step 3: Klik "Proses Dokumen"

Tunggu **10-15 detik**. AI baca dokumen via OpenAI GPT-4o-mini, ekstrak field.

### Step 4: Notifikasi Sukses

> ✅ *"Dokumen berhasil dibaca. Ditemukan 4 termin pembayaran & 7 milestone — akan dibuat otomatis setelah disimpan."*

### Step 5: Auto-redirect ke Form Tambah Pekerjaan

Form sudah terisi otomatis:
- ✅ Nama pekerjaan
- ✅ No SPK + tanggal
- ✅ No SPMK + tanggal
- ✅ Nilai pagu + nilai kontrak
- ✅ Tanggal mulai + akhir
- ✅ Hari kerja + satuan waktu
- ✅ Vendor (kalau sudah ada di database, atau notif "belum ada, mau buat baru?")

### Step 6: Lengkapi yang Kosong

Yang AI **tidak bisa tebak** dan harus manual:
- **Bidang** (Bangunan Gedung / Jalan / Drainase / dll) — pilih dari dropdown
- **Status** (default: Belum Mulai) — kalau sudah jalan, ganti ke Proses Desain

> 💡 **Quick-add inline:** kalau jenis pekerjaan / perusahaan / tenaga ahli **belum ada di dropdown**, klik ikon `+` di pojok dropdown → mini modal muncul → isi nama baru → save → otomatis ter-pilih. Tidak perlu keluar form.

### Step 7: Klik Simpan

Notifikasi terakhir:
> ✅ *"Berhasil membuat 4 termin & 7 milestone dari kontrak."*

**Total waktu: ~90 detik.** Versus 25-30 menit input manual.

---

# 📷 10:15 — Vendor di Lapangan (Vendor Portal & PWA)

Bu Sari (vendor PT Karya Maju) lagi ngawasin tukang cor lapis bawah di lokasi proyek Drainase Margahayu. Pekerjaan kelar jam 10 pagi, perlu lapor.

## Vendor Portal — URL Terpisah

![Vendor Login — viewport mobile 414x896 (iPhone-class), card login putih dengan judul **"Portal Vendor — DPUTR"** + sub "Masuk ke akun Anda", field "Alamat email *" + "Kata sandi *" (toggle "Tampilkan kata sandi"), checkbox "Ingat saya", tombol oranye full-width "Masuk". Branding "Portal Vendor" sengaja beda dari admin panel biar vendor tahu mereka di area khusus. Layout sederhana, tombol 44px+ touch-friendly](./screenshots/18-vendor-login.png)

Vendor pakai **URL berbeda** dari admin:
- 🔗 https://karta.aureonforge.com/vendor

UI lebih sederhana, mobile-first, tombol besar (44px+ untuk touch screen).

### Step 1: Setup Vendor User (Sekali, oleh Admin)

1. Sidebar → **Pengaturan** ▶ → **Pengguna**
2. Tambah user baru
3. **Role:** vendor
4. **Perusahaan:** pilih dari dropdown (vendor harus link ke 1 perusahaan)
5. Save → kasih credential ke vendor

### Step 2: Vendor Login dari HP

1. Vendor buka URL `/vendor` di Chrome HP
2. Banner muncul: *"📱 Install Karta di HP kamu"* → klik **Install**
3. App ter-install di home screen, buka tanpa browser bar (PWA)
4. Login pakai credential dari admin

### Step 3: Submit Laporan Harian

Klik menu **Submit Laporan Harian:**
- 📷 **Foto** — kamera HP otomatis aktif
- 📍 **GPS** — auto-capture lokasi (kalau diizinkan browser)
- ⏰ **Waktu** — tertangkap otomatis (server time)
- **Pilih jenis:** masuk / pulang / progress
- **Catatan:** apa yang dikerjain hari ini
- Submit ✓

**Selesai dalam ~30 detik.**

> 💡 **Foto + GPS + timestamp tersimpan = audit-grade evidence.** Tidak bisa dipalsukan. Audit BPK seneng.

---

# 📊 11:00 — Cek Laporan Harian (Laporan Harian Vendor)

Pak Dimas balik ke laptop. Tanya AI:

> 👨 *"laporan harian hari ini siapa aja yang udah masuk"*

> 🤖 *"Hari ini ada 5 laporan: 09:30 Drainase Margahayu (Bu Sari, foto+GPS ✓), 09:45 Jalan Soreang (Pak Adit), 10:17 Drainase Margahayu lapis bawah selesai, 10:30 DED Jembatan Ciwidey (Pak Tono), 10:50 Rehabilitasi Trotoar (Bu Lina). 3 vendor lain belum laporan: Survey Topografi, Pengadaan Aspal, Pengawasan Banjaran."*

## Lihat Laporan via Menu

Sidebar → **Laporan Harian**:

![Laporan Harian — breadcrumb "Laporan Harian / Daftar" + judul. Filter aktif chip "Hari Ini" di atas tabel (default filter saat landing). Body kosong dengan ikon "x" abu-abu + label "Tidak ada data yang ditemukan" (state kosong saat tidak ada laporan masuk hari itu)](./screenshots/11-laporan-harian-list.png)

Tabel lengkap dengan: foto thumbnail, lokasi GPS, jenis (masuk/pulang/progress), vendor, catatan.

### Kirim Reminder ke Vendor yang Belum Lapor

> 👨 *"kirim reminder ke vendor yang belum laporan"*

> 🤖 *"Konfirmasi: kirim reminder WA ke 3 vendor? (ya/tidak)"*

> 👨 *"ya"*

> 🤖 *"✅ 3 reminder terkirim via WA."*

---

# 💰 13:00 — PPK Approve Termin (Approval Termin)

Bu Ratna (PPK) lagi lunch di restoran. HP-nya bunyi—WA dari Karta:

> 💰 *"Termin baru menunggu approval: Termin 2 Drainase Margahayu, Rp 600.000.000, vendor PT Karya Maju. Syarat progres minimal 60% ✅ (sekarang 71%). Login untuk review."*

## 2 Cara Approve

### Cara A: Via Chat AI (Paling Cepat)

Login dari HP, di chat:

> 👨 *"approve termin 2 drainase margahayu"*

> 🤖 *"Konfirmasi approval:*
> *• Termin 2 Drainase Margahayu*
> *• Nilai: Rp 600.000.000*
> *• Vendor: PT Karya Maju Jaya*
> *• Syarat: progres 60% (terpenuhi: 71%)*
> *Setuju? (ya/tidak)"*

> 👨 *"ya"*

> 🤖 *"✅ Termin 2 disetujui pada 5 Mei 2026. Notif terkirim ke Bendahara untuk pencairan."*

**Total: 30 detik.**

### Cara B: Via UI Manual

1. Buka detail Pekerjaan → tab **Termin Pembayaran**
2. Klik **Setujui** di baris termin status `diajukan`
3. Modal isi catatan PPK → submit
4. Status berubah `disetujui` + tanggal otomatis

## Workflow Termin Lengkap

```
Vendor (atau Admin) bikin termin
  ↓
status: draft
  ↓
Klik "Ajukan" → cek syarat progres
  ↓
status: diajukan  ← notif WA ke PPK
  ↓
PPK Setujui (chat AI atau UI)
  ↓
status: disetujui  ← notif WA ke Bendahara
  ↓
Bendahara klik "Bayar"
  ↓
status: dibayar
```

---

# 🔍 14:30 — Investigasi Anomali (Audit Trail)

Kabid kirim WA Pak Dimas: *"Pak, kenapa progres DED Jembatan Ciwidey jadi 45%? Kemarin masih 60%."*

## Audit Trail — Riwayat Semua Perubahan

Sidebar → **Pengaturan** ▶ → **Audit Trail**:

![Audit Trail — halaman `/admin/activities`, breadcrumb "Audit Trail / Daftar". Tabel 7 kolom: Waktu (cth. "20 Mei 2026 10:11:08") / Aksi (badge "Diubah" oranye / "Dibuat" hijau / "Dihapus" merah) / Objek (Pekerjaan / RencanaPengadaan / Termin / Personil / Milestone / dll — nama model) / ID (record id) / Dilakukan Oleh (nama user, cth. Super Admin) / Deskripsi (string log dari Spatie ActivityLog: created/updated/deleted) / Perubahan (link "Lihat" untuk modal diff). Panel filter di atas dengan Cari + Filter button](./screenshots/12-audit-trail.png)

**Filter:**
- Aksi: created / updated / deleted
- User: siapa yang melakukan
- Objek: Pekerjaan / Termin / Personil / dll
- Tanggal: range filter

### Step-by-Step Investigasi

1. Filter: Objek = Pekerjaan, ID = 18, tanggal kemarin
2. Hasil muncul: *"5 Mei 13:42 — Pak Tono (PPTK) updated `progres_persen` dari 60.00 → 45.00. Catatan kosong."*

Pak Dimas WA Pak Tono → ternyata salah ketik (mau 65, ke-input 45).

### Fix via Chat

> 👨 *"update progres DED Jembatan Ciwidey jadi 65%"*

> 🤖 *"Konfirmasi update dari 45% ke 65%? (ya/tidak)"*

> 👨 *"ya"*

> 🤖 *"✅ Updated. Tercatat di Audit Trail: Pak Dimas ubah dari 45 → 65 pada 5 Mei 14:35."*

Drama selesai dalam 3 menit, **dengan jejak audit lengkap**.

> 💡 **Excel tidak punya audit trail** → kalau ada anomali, susah investigasi, sering jadi saling tuduh "kamu yang ubah!"

---

# 🗓️ 15:00 — Plan Resource via Kalender (Kalender Resource)

Pak Dimas mau plan alokasi personil untuk minggu depan. Klik tombol **🗓️ Kalender** di dashboard.

![Calendar Modal — modal full-width "Mei 2026" dengan header navigasi (‹ Hari Ini › ×), grid 7-kolom MIN/SEN/SEL/RAB/KAM/JUM/SAB. Tanggal libur nasional ditandai dengan chip merah berisi tanggal + nama event (Hari Buruh 1 Mei, Kenaikan Isa Almasih 12 Mei, Cuti Bersama 13 Mei, Hari Raya Waisak 29 Mei). Sunday-column tanggal merah. Hari ini (20 Mei) highlighted kuning. Legend di bawah: 🔴 Hari Libur · 🔵 Milestone Bangunan · 🟢 Milestone Jalan · 🟣 Milestone Drainase · 🟠 Milestone Irigasi · 🔴 Deadline Pekerjaan](./screenshots/06-calendar-modal.png)

**Visualisasi:**
- 🔴 **Hari Libur** — tanggal di-circle merah dengan event chip (Hari Buruh, Cuti Bersama, Waisak, dll)
- 🎯 **Milestone Pekerjaan** — color per bidang (BG=biru, Jalan=hijau, Drainase=ungu, Irigasi=amber)
- ⏰ **Deadline Pekerjaan** — marker merah mencolok
- ⭐ **Hari Ini** — highlight kuning
- **Sunday** otomatis warna merah

## Navigasi Bulan

Tombol di pojok kanan atas:
- `‹` Bulan Sebelumnya
- `Hari Ini` → loncat ke bulan ini
- `›` Bulan Berikutnya
- `×` Tutup

![Calendar Next Month — modal kalender setelah klik tombol "›" di header, menampilkan bulan Juni 2026 dengan event Hari Lahir Pancasila (1 Jun) dan Hari Raya Idul Adha (6 Jun) yang ter-circle merah](./screenshots/06b-calendar-next-month.png)

## Manfaat

- Lihat deadline padat di minggu mana
- Plan alokasi tambahan personil ke proyek deadline ketat
- Avoid scheduling launch saat libur nasional / cuti bersama

---

# 📋 16:00 — Filter Kanban per Bidang (Kanban Board & Filter)

Mau zoom ke proyek Bidang Jalan saja. Scroll ke widget **Papan Pekerjaan**:

![Kanban Full — pill filter bar di atas (🌐 Semua aktif oranye, lalu Bangunan Gedung / Drainase / Jalan), di bawahnya 6 kolom kanban: Backlog · Aman · Waspada · Kritis · Terlambat · Selesai (di viewport sempit hanya 4 pertama yang terlihat, scroll horizontal untuk lihat Terlambat & Selesai)](./screenshots/07-kanban-full.png)

## 6 Kolom Workflow (Trello-Style)

```
📋 Backlog → 🟢 Aman → 🟡 Waspada → 🔴 Kritis → ⛔ Terlambat → ✅ Selesai
```

- **📋 Backlog** — proyek belum mulai (tanggal mulai belum tercapai)
- **🟢 Aman** — progres on-track, deadline aman
- **🟡 Waspada** — deadline mendekati (perlu perhatian)
- **🔴 Kritis** — sangat dekat deadline
- **⛔ Terlambat** — sudah lewat tanggal akhir
- **✅ Selesai** — proyek tuntas

> 💡 Pada layar 1440px dengan sidebar terbuka, hanya 4 kolom pertama (Backlog / Aman / Waspada / Kritis) yang terlihat penuh. **Scroll horizontal di area kanban** untuk lihat Terlambat & Selesai.

## Filter per Bidang

Bar pill di atas kanban — jumlah pill **dinamis** berdasarkan bidang yang punya pekerjaan pada tahun aktif. Di environment contoh ini tampil 4 pill:

```
🌐 Semua | Bangunan Gedung | Drainase | Jalan
```

Klik **"Jalan"** → pill Jalan jadi oranye-solid, kanban refresh, hanya nampilin proyek Bidang Jalan:

![Kanban Filter Jalan — pill "Jalan" highlighted oranye, kanban menampilkan hanya pekerjaan dari Bidang Jalan (kolom Kritis/Terlambat/Selesai keisi, sisanya kosong)](./screenshots/07b-kanban-filter-jalan.png)

> 💡 Pill "Irigasi" akan otomatis muncul kalau ada pekerjaan Bidang Irigasi tahun ini (dynamic filter). Bidang yang tidak punya pekerjaan disembunyikan biar bar tidak ramai.

## Click Card → Modal Detail

Click card mana saja → modal popup detail:

![Kanban Card Modal — title pekerjaan (cth. "DED Jalan Kabupaten Wilayah Ciwidey") + status badge ("Proses Desain" merah), 3 stat cards (Personil/Termin/Milestone), progress bar 60%, field grid 2 kolom dengan Bidang/Vendor/No SPK/No SPMK/Nilai Pagu/Nilai Kontrak/Tanggal Mulai/Tanggal Akhir/Hari Kerja/Sisa Hari, dan 3 tombol footer (Tutup / Edit / Buka Detail Lengkap oranye)](./screenshots/07c-kanban-card-modal.png)

**Isi modal:**
- **Title** + status badge (warna sesuai status pekerjaan, BUKAN warna kolom kanban)
- **3 stat cards:** Personil · Termin · Milestone — angka total relasi
- **Progress bar** dengan persentase (warna oranye-merah)
- **Field grid** 2 kolom (kiri-kanan): Bidang · Vendor · No SPK · No SPMK · Nilai Pagu · Nilai Kontrak · Tanggal Mulai · Tanggal Akhir · Hari Kerja · Sisa Hari
- **3 tombol footer:** `Tutup` (outline) · `✏️ Edit` (outline) · `📂 Buka Detail Lengkap` (oranye solid)

## Buka Detail Lengkap → Form View + 7 Relation Tabs

Klik **Buka Detail Lengkap** → halaman detail Pekerjaan `/admin/pekerjaans/{id}` (mode View, bukan Edit). Bagian atas adalah **form read-only** dengan 5 section terstruktur, dan **scroll ke bawah** untuk akses 7 tab relation manager.

![Pekerjaan Detail (top viewport) — breadcrumb "Pekerjaan / Lihat", judul "Lihat Pekerjaan", tombol "Ubah" oranye di kanan-atas (toggle ke mode Edit). Section pertama "Informasi Umum" dengan field read-only: Bidang ("Bangunan Gedung"), Jenis Pekerjaan, Nama Pekerjaan ("Kajian Pemetaan Topografi"), Perusahaan ("PT. PURNA WAHANA LESTARI KONSULTAN"), Status ("Selesai"), Tahun Anggaran, Progress (%). Section kedua "Nilai Anggaran" dengan Nilai Pagu (Rp) dan Nilai Kontrak (Rp) — helper "Tidak boleh melebihi nilai pagu"](./screenshots/10-pekerjaan-detail.png)

**Section di form view (urut dari atas):**
1. **Informasi Umum** — Bidang / Jenis Pekerjaan / Nama Pekerjaan / Perusahaan / Status / Tahun Anggaran / Progress (%)
2. **Nilai Anggaran** — Nilai Pagu, Nilai Kontrak (validasi: tidak boleh > Nilai Pagu)
3. **SPK & SPMK** — Nomor SPK + Tanggal SPK, Nomor SPMK + Tanggal SPMK
4. **Waktu Pelaksanaan** — Tanggal Mulai, Tanggal Akhir, **Durasi (hari)**, **Satuan Waktu** (radio: Hari Kerja / Hari Kalender)
5. **Catatan** — notes opsional

Setelah 5 section ini, **scroll terus** ke bawah — ketemu **tab navigator horizontal** dengan 7 tab relation manager:

![Pekerjaan Detail Full Page — full-length screenshot menampilkan 5 section form di atas + tab horizontal "Personil / Vendor / Rencana Pengadaan / Realisasi Pengadaan / Dokumen / Termin Pembayaran / Milestone" di bawah, dengan tab pertama (Personil) aktif menampilkan tabel relation manager](./screenshots/20-pekerjaan-tabs-overview.png)

| Label Tab di App | Isinya | Filament Class |
|---|---|---|
| **Personil** | Daftar tenaga ahli yang assigned + jabatan + honor | PersonilRelationManager |
| **Vendor** | Perusahaan terkait (many-to-many) | VendorRelationManager |
| **Rencana Pengadaan Barang** | List barang yang akan dipakai | RencanaPengadaanRelationManager |
| **Realisasi Pengadaan** | Barang yang sudah terealisasi | RealisasiPengadaanRelationManager |
| **Dokumen Proyek** | File kontrak, BAST, laporan | DokumenRelationManager |
| **Termin Pembayaran** | Termin + status approval | TerminPembayaranRelationManager |
| **Milestone & Jadwal** | Milestone dengan badge sumber (kontrak / AI generated) | MilestoneRelationManager |

> 💡 Tab aktif ditandai dengan **border-bottom oranye + teks oranye**. Klik tab lain → konten berganti instan (Livewire). URL tidak berubah (state cuma di-track di Filament JS).

### Pekerjaan List (Akses Direct via URL)

Kalau perlu daftar semua proyek:

![Pekerjaan List — breadcrumb "Pekerjaan / Daftar" + judul "Pekerjaan", tombol "📥 Import dari Kontrak" (outline) & "Buat Pekerjaan" (oranye solid) di kanan-atas, filter tab row 6-tab (Semua / Tahun 2026 / Sedang Berjalan / Deadline Dekat / Terlambat / Selesai), tombol "💾 Simpan Filter sebagai Preset" di atas tabel. Tabel kolom 9-col: Nama Pekerjaan / Bidang / Perusahaan / Nilai Pagu / Traffic Light (badge warna sesuai status_waktu) / Sisa Hari Kerja (negatif kalau Terlambat) / Status / Deadline / Progress (%). Setiap row punya aksi Lihat / Ubah / Hapus](./screenshots/09-pekerjaan-list.png)

URL: `/admin/pekerjaans` (atau klik card di Kanban). Tombol header kanan-atas:
- **📥 Import dari Kontrak** — sama dengan tombol Import Kontrak di Dashboard
- **Buat Pekerjaan** (oranye) — buka form Tambah Pekerjaan kosongan

### Filter Tab di Pekerjaan List

Di atas tabel ada **6 filter tab cepat** — klik untuk filter tanpa buka panel filter:

```
[ Semua ] [ Tahun 2026 ] [ Sedang Berjalan ] [ Deadline Dekat ] [ Terlambat ] [ Selesai ]
```

Tab yang aktif ditandai dengan teks oranye + underline. Berikut screenshot per tab:

| Filter | Tampilan |
|---|---|
| **Tahun 2026** — semua proyek tahun anggaran aktif | ![Filter Tahun 2026 — tab "Tahun 2026" aktif (teks oranye), tabel ke-filter cuma menampilkan pekerjaan dengan `tahun_anggaran = 2026`](./screenshots/22a-filter-tahun-2026.png) |
| **Sedang Berjalan** — proyek aktif (belum Selesai, belum dihapus) | ![Filter Sedang Berjalan — tab aktif, daftar pekerjaan yang masih on-going](./screenshots/22b-filter-sedang-berjalan.png) |
| **Terlambat** — yang sudah lewat `tanggal_akhir` | ![Filter Terlambat — tab aktif, daftar pekerjaan dengan Sisa Hari Kerja negatif & badge Traffic Light "Terlambat"](./screenshots/22c-filter-terlambat.png) |
| **Selesai** — yang sudah closeout (status_pekerjaan = Selesai) | ![Filter Selesai — tab aktif, daftar pekerjaan dengan badge "Selesai" hijau](./screenshots/22d-filter-selesai.png) |

> 💡 Tab **"Deadline Dekat"** filter ke pekerjaan dengan `tanggal_akhir` dalam ≤ 14 hari. **"Semua"** = no filter (default landing). Reset filter dengan klik **Semua**.

### Form Tambah Pekerjaan Manual

Kalau tidak pakai Import Kontrak, klik tombol **Buat Pekerjaan** (oranye, di header kanan-atas) untuk input manual:

![Form Tambah Pekerjaan — breadcrumb "Pekerjaan / Buat", judul "Buat Pekerjaan", 7 section terurut dari atas: "Upload Dokumen Kick-Off" (collapsible, opsi upload PDF untuk auto-extract), "Informasi Umum" (Bidang/Jenis Pekerjaan/Nama Pekerjaan/Perusahaan/Status/Tahun Anggaran/Progress%), "Nilai Anggaran" (Nilai Pagu, Nilai Kontrak), "SPK & SPMK", "Waktu Pelaksanaan" (Tanggal Mulai/Akhir + Durasi + Satuan Waktu), "Catatan", "Asisten AI DPUTR" (chat-style helper di bagian bawah form)](./screenshots/21-form-tambah-pekerjaan.png)

Form di-split jadi **7 section** (Filament Forms\Section), urut dari atas:
1. **Upload Dokumen Kick-Off** — opsional, upload PDF KAK/Kontrak → AI auto-fill section di bawah (alternatif tombol Import Kontrak)
2. **Informasi Umum** — Bidang, Jenis Pekerjaan, Nama Pekerjaan, Perusahaan, Status, Tahun Anggaran, Progress (%)
3. **Nilai Anggaran** — Nilai Pagu (Rp), Nilai Kontrak (Rp) — *Nilai Kontrak tidak boleh melebihi nilai pagu*
4. **SPK & SPMK** — Nomor SPK + Tanggal SPK, Nomor SPMK + Tanggal SPMK
5. **Waktu Pelaksanaan** — Tanggal Mulai, Tanggal Akhir, **Durasi (hari)**, **Satuan Waktu** (radio: Hari Kerja / Hari Kalender)
6. **Catatan** — notes opsional
7. **Asisten AI DPUTR** — chat helper inline (kontekstual saat ngisi form, beda dengan chat hero dashboard)

---

# 📤 17:00 — Export Sebelum Pulang (Export Excel/PDF)

Pak Dimas mau kirim laporan mingguan ke Kabid via email.

## Klik Tombol "📤 Export Data" di Dashboard

Modal popup muncul dengan:

**Pilih Jenis Export:**
- 📊 Pekerjaan (Excel) — header biru, semua field lengkap
- 📄 Pekerjaan (PDF Landscape A4) — ringkas, siap print
- 📝 Laporan Harian (Excel) — header hijau
- 📦 Pengadaan (Excel) — header kuning
- 💰 Termin Pembayaran (Excel) — header ungu

**Pilih Tahun Anggaran** → klik **Download** → file ter-download.

Email ke Kabid: *"Pak, update mingguan terlampir."* → kirim dari mobile email.

**Selesai. Pulang jam 5:15.**

---

# 🚪 17:15 — Logout (Sign Out)

## Cara #1: Tombol "Keluar" di AccountWidget (Paling Mudah)

Di dashboard, kotak Selamat Datang paling atas → klik tombol **Keluar**.

## Cara #2: Avatar Pojok Kanan-Atas

Klik avatar bulat **SA** di pojok kanan-atas → dropdown muncul:

![Logout Menu (sama dengan Avatar SA Dropdown) — popover dengan "Super Admin" + 3 ikon theme + tombol "Keluar" di bawah](./screenshots/19-logout-menu.png)

Klik **Keluar** (bahasa Indonesia, bukan "Sign out").

> 💡 **Bahasa Indonesia:** Karta full-pakai locale `id`, jadi semua label tombol/menu pakai Bahasa Indonesia.

---

# 🌙 21:00 — Karta Tetap Kerja Sendiri (Cron & Background Jobs)

Pak Dimas udah di rumah, lagi nonton bola sama anak. Karta tetap jalan otomatis di server:

```
21:00 → cron jalan
       ↓
       Kirim WA ke Bendahara:
       "1 termin baru disetujui PPK hari ini, siap pencairan:
        Termin 2 Drainase Margahayu Rp 600.000.000"
```

## Schedule Job Otomatis

| Jadwal | Notifikasi |
|---|---|
| **Senin 07:00** | Weekly digest ke admin_bidang + super_admin |
| Setiap hari 07:00 | Notif proyek deadline H-14, H-7, H-3 ke personil |
| Weekday 06:30 | Reminder vendor laporan masuk |
| Weekday 15:00 | Reminder vendor laporan pulang |
| Setiap hari 08:00 | Notif termin pending approval ke PPK |

Esok pagi, Bendahara siap kerja sebelum sampai kantor.

---

# 📊 Recap Hari Pak Dimas (Ringkasan Workflow)

| Aktivitas | Old Way (Excel) | Karta |
|---|---|---|
| Cek kondisi pagi | ❌ Tunggu rapat 9 AM | ✅ WA digest 06:30 |
| Brief ke Kabid | 15 menit nyari file | 30 detik via chat |
| Input kontrak baru | 25 menit input manual | 90 detik upload PDF |
| Cek laporan harian | Telepon 8 vendor | 1 chat command |
| Reminder vendor | 8 WA manual | 1 chat command |
| Approve termin | 3 menit per termin | 30 detik (via chat) |
| Investigasi data | Tanya tim, cek email lama | Audit Trail (10 detik) |
| Fix data salah | Edit Excel, save, kirim ulang | 1 chat command |
| Export laporan | Bikin pivot manual | 1 klik download |
| **Total waktu hemat** | — | **~3-4 jam/hari** |

---

# 📌 Lampiran: Sidebar Cheat Sheet (Navigasi Menu)

![Sidebar Collapsed — 2 menu top-level (Dasbor aktif berwarna oranye dengan ikon rumah, Laporan Harian dengan ikon kamera) + 2 group "Master Data" dan "Pengaturan" dengan chevron-down untuk expand](./screenshots/08-sidebar-collapsed.png)

## Default View (Collapsed)

```
🏠 Dasbor                  ← landing page (90% kerjaan di sini) — aktif oranye
📷 Laporan Harian          ← lihat semua laporan vendor
Master Data         v      ← group (collapsed default, klik chevron untuk expand)
Pengaturan          v      ← group (collapsed default)
```

> 💡 Item "Dasbor" yang lagi aktif ditandai dengan **background oranye lembut + teks oranye**. Hover item lain → background abu-abu.

## Setelah Expand

![Sidebar Expanded — Master Data & Pengaturan dibuka. Master Data berisi: Bidang, Hari Libur, Jenis Pekerjaan, Perusahaan, Status Pekerjaan, Tenaga Ahli (urutan alfabet). Pengaturan berisi: Pengaturan Sistem, Pengguna, Audit Trail](./screenshots/08b-sidebar-expanded.png)

```
🏠 Dasbor
📷 Laporan Harian
Master Data         ^      (expanded)
   Bidang
   Hari Libur
   Jenis Pekerjaan
   Perusahaan
   Status Pekerjaan
   Tenaga Ahli
Pengaturan          ^      (expanded)
   Pengaturan Sistem
   Pengguna
   Audit Trail
```

> 💡 **Urutan item di group otomatis alfabet** — bukan custom-ordered. Jadi "Bidang" duluan dari "Hari Libur" karena B < H.

## Master Data Pages

### 🏢 Bidang

![Master Bidang — halaman `/admin/master/bidangs`, breadcrumb "Bidang / Daftar" + judul "Bidang", tombol "Buat Bidang" oranye di kanan-atas. Tabel 5 kolom: Kode (BG/JL/DR/IR/UM/TR/JK) / Nama (Bangunan Gedung / Jalan / Drainase / Irigasi / UMPEG / TARU / JAKON) / Kepala Bidang / Status (Aktif) / Diperbarui (timestamp). 7 row seeded. Setiap row punya tombol "Ubah" (tidak ada delete karena bidang masih dipakai pekerjaan)](./screenshots/23-master-bidang.png)

Daftar bidang/divisi (Jalan, Drainase, Jembatan, dst). Tiap pekerjaan harus dipasangkan ke 1 bidang.

### 🏢 Perusahaan

![Master Perusahaan — halaman `/admin/master/perusahaans`, breadcrumb "Perusahaan / Daftar" + tombol "Buat Perusahaan" oranye di kanan-atas. Tabel 6 kolom: Nama (cth. PT. ITERGO BUANA UTAMA, PT. PURNA WAHANA LESTARI KONSULTAN, CV. TACIBA SHIGOTO NUSANTARA) / Singkatan (PT/CV) / Jenis (PT/CV/Perorangan/Lainnya — badge) / PIC (nama Person In Charge) / Telp PIC / Status (Aktif/Nonaktif). Setiap row punya aksi Ubah/Hapus](./screenshots/15-master-perusahaan.png)

CRUD daftar vendor/kontraktor (PT, CV, Perorangan, Lainnya).

### 👷 Tenaga Ahli

![Master Tenaga Ahli — halaman `/admin/master/tenaga-ahlis`, breadcrumb "Tenaga Ahli / Daftar" + tombol "Buat Tenaga Ahli" oranye. Tabel 7 kolom: Nama (cth. "Tenaga Ahli 1", "Agus Santoso") / Jabatan-Keahlian / Sertifikasi / Perusahaan (cth. PT. ITERGO BUANA UTAMA) / No. Telepon / Status (Aktif) / Proyek Aktif (jumlah pekerjaan saat ini di-assign). Tombol Ubah di setiap row](./screenshots/16-master-tenaga-ahli.png)

Pool personil yang bisa di-assign ke pekerjaan. Profile berisi nama, NIK, jabatan keahlian, kontak.

## Pengguna (User Management)

![User Management — halaman `/admin/users` (Pengguna), breadcrumb "Pengguna / Daftar" + tombol "Buat Pengguna" oranye di kanan-atas. Tabel 5 kolom: Nama / Email / Role (string seperti "super_admin" / "admin_bidang" / "pptk" / "ppk" / "viewer" / "vendor") / Status (Aktif/Nonaktif) / Dibuat (tanggal). Pada env demo: 1 row "Super Admin / admin@dputr.go.id / super_admin / Aktif / Mei 18, 2026" + aksi Ubah/Hapus. Footer "Menampilkan 1 hasil"](./screenshots/14-pengguna-list.png)

CRUD user, assign role:
- `super_admin` — akses semua
- `admin_bidang` — admin per bidang
- `pptk` — input progres, kelola personil
- `ppk` — approve termin pembayaran
- `viewer` — read-only
- `vendor` — login portal vendor

## System Settings

![System Settings — halaman `/admin/system-settings` (super_admin only), full-page screenshot. 5 section terurut dari atas: **"Informasi Instansi"** (Nama Instansi *, Nama Singkat *, Alamat Kantor, Nomor Telepon Kantor) → **"Tahun Anggaran Aktif"** (input numerik dengan helper "Tahun yang muncul sebagai default di semua filter") → **"Notifikasi WhatsApp"** (Alert Deadline hari ke-* dengan helper "14,7,3 artinya WA dikirim saat sisa H-14/H-7/H-3", lalu 3 toggle: Notifikasi Deadline / Notifikasi Laporan Harian / Notifikasi Termin) → **"Jam Laporan Vendor"** (4 field HH:MM: Jam Buka/Tutup Laporan Masuk + Jam Buka/Tutup Laporan Pulang) → **"Sistem"** (toggle Mode Maintenance). Footer: tombol "Simpan Pengaturan" oranye + tombol "Reset ke Tersimpan" outline](./screenshots/24-system-settings-full.png)

Setting global aplikasi (super_admin only):
- **Informasi Instansi** — nama, alamat, telepon
- **Tahun Anggaran Aktif**
- **Notifikasi WA** — toggle aktif/non untuk: deadline, weekly digest, reminder, termin pending
- **Jam Operasional Vendor** — buka/tutup masuk & pulang

## Halaman yang TIDAK Ada di Sidebar (Akses Lain)

Sengaja disembunyikan biar sidebar gak ramai:

| Halaman | Cara Akses |
|---|---|
| 💼 Daftar Pekerjaan | Klik card di Kanban → modal → "Buka Detail" |
| 💰 Termin Pembayaran | Detail Pekerjaan → tab "Termin Pembayaran" |
| 📁 Dokumen | Detail Pekerjaan → tab "Dokumen" |
| 📦 Rencana Pengadaan | Detail Pekerjaan → tab "Rencana Pengadaan" |
| ✅ Realisasi Pengadaan | Detail Pekerjaan → tab "Realisasi Pengadaan" |
| 🎯 Milestone | Detail Pekerjaan → tab "Milestone & Jadwal" |
| 📥 Import Data | Tombol "📑 Import Kontrak" di Dashboard |
| 📤 Export Data | Tombol "📤 Export Data" di Dashboard |

---

# 🆘 Troubleshooting (Diagnostik & Solusi)

| Gejala | Solusi |
|---|---|
| Chat AI balas *"OPENAI_API_KEY belum dikonfigurasi"* | Admin: isi `OPENAI_API_KEY` di `.env`, restart server |
| Import Kontrak gagal *"tidak dapat dibaca"* | PDF hasil scan → harus PDF digital (Word export) |
| Vendor login error | Cek user role = `vendor` & link ke perusahaan |
| Notif WA gak ngirim | Cek `WA_GATEWAY_TOKEN` & `no_telp` di profile user |
| Halaman 500 error | Admin: cek `storage/logs/laravel-*.log` |
| Lupa cara | **Tanya AI** — itulah gunanya chat hero |
| Logout gak nemu | Avatar **SA** pojok kanan-atas → **Keluar** |
| Modal kalender stuck open | Tekan **ESC** atau klik `×` |
| Filter kanban gak refresh | Hard reload browser (Ctrl+Shift+R) |

---

# 💡 Filosofi Karta (Design Principles)

> **Daripada belajar tool baru, suruh aja tool-nya kerjain.**
> Daripada input data manual, upload aja dokumen yang udah ada.
> Daripada kirim email yang gak dibuka, kirim WA yang langsung dibaca.
> Daripada vendor pakai akun guest yang clunky, kasih portal sendiri.
> Daripada ngeset semua dari nol, pilih template industri yang udah kebayang.
>
> **= Karta.**

---

# 🎓 Quick Wins untuk User Baru (Onboarding 3 Hari)

## Latihan 5 Menit Pertama

Login → langsung ketik di chat hero:

1. *"berapa total proyek?"* — lihat angka real
2. *"daftar proyek aktif"* — list muncul
3. *"detail proyek nomor 1"* — detail real
4. *"milestone proyek nomor 1"* — milestone list
5. *"laporan harian hari ini"*
6. *"update progres proyek nomor 1 jadi 50%"* → ketik *"ya"*

Kalau 6 ini jalan, kamu sudah pakai 80% kekuatan Karta.

## Onboarding Admin Baru (10 Menit)

1. ✅ Login → ganti password admin (avatar → Edit Profile)
2. ✅ **Pengaturan → Pengaturan Sistem** — set nama instansi, tahun anggaran, jam operasional
3. ✅ **Master Data → Bidang** — tambah/edit sesuai kebutuhan
4. ✅ **Master Data → Perusahaan** — tambah vendor utama
5. ✅ **Pengguna** — tambah staff lain (admin_bidang, pptk, ppk, viewer)
6. ✅ Buat 1 vendor user → kasih credential ke vendor → minta mereka coba laporan harian

## Onboarding Vendor (3 Menit)

1. ✅ Buka https://karta.aureonforge.com/vendor di HP
2. ✅ Login pakai credential dari admin
3. ✅ Submit Laporan Harian pertama (foto + catatan + GPS)
4. ✅ Banner "Install di home screen" → klik **Install** (PWA)
5. ✅ Buka dari home screen tiap hari

---

**Hari kerja yang baik = pulang tepat waktu dengan semua urusan beres.**

Karta bikin itu jadi default, bukan kebetulan.

🎉 **Selamat menggunakan Karta!**

---

📞 **Bantuan lebih lanjut:**
- In-app: tanya AI Chat — *"gimana cara X?"*
- Dokumentasi: [USER_GUIDE.md](./USER_GUIDE.md)
- Setup teknis: [DEPLOY.md](../DEPLOY.md)
- Roadmap masa depan: [KARSA_DEVELOPMENT_PLAN.md](./KARSA_DEVELOPMENT_PLAN.md)
