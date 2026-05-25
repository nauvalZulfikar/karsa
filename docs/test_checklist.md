# Karta DPUTR-PM — User Test Checklist

> Last updated: 2026-05-24
> Format: `[ ]` = belum · `[x]` = pass · `[!]` = bug
> Semua test dilakukan sebagai user di browser `http://localhost:8010/admin`

---

## 1. Login & Logout

- [ ] Buka `/admin/login` → halaman login muncul
- [ ] Isi email `admin@dputr.go.id`, password `password`, klik Login → masuk dashboard
- [ ] Isi password salah → pesan error muncul, tetap di halaman login
- [ ] Klik avatar/nama di kanan atas → klik Logout → kembali ke halaman login
- [ ] Coba akses `/admin/pekerjaans` tanpa login → otomatis redirect ke login

---

## 2. Dashboard

- [ ] Setelah login, halaman dashboard muncul
- [ ] Sidebar kiri: ada menu Pekerjaan, Master Data, Laporan, Settings
- [ ] Widget/statistik di dashboard (jumlah pekerjaan, status, dll) tampil
- [ ] Klik menu sidebar → pindah halaman sesuai label

---

## 3. Kelola Bidang

- [ ] Sidebar → Master → Bidang → daftar bidang muncul
- [ ] Klik "Buat Bidang" → isi nama & kode → Simpan → muncul di daftar
- [ ] Klik edit di salah satu row → ubah nama → Simpan → nama berubah
- [ ] Klik hapus → konfirmasi → bidang hilang dari daftar
- [ ] Coba buat bidang dengan kode yang sudah ada → error validasi muncul

---

## 4. Kelola Perusahaan (Vendor)

- [ ] Sidebar → Master → Perusahaan → daftar perusahaan muncul
- [ ] Ketik nama di kolom search → daftar terfilter
- [ ] Klik "Buat Perusahaan" → isi nama, NPWP, alamat, direktur → Simpan → muncul di daftar
- [ ] Klik edit → ubah alamat → Simpan → alamat berubah
- [ ] Klik hapus → konfirmasi → perusahaan hilang

---

## 5. Kelola Tenaga Ahli

- [ ] Sidebar → Master → Tenaga Ahli → daftar muncul
- [ ] Klik "Buat" → isi nama, posisi, kualifikasi → Simpan
- [ ] Edit salah satu → ubah posisi → Simpan
- [ ] Hapus → konfirmasi → hilang dari daftar

---

## 6. Kelola Jenis Pekerjaan

- [ ] Sidebar → Master → Jenis Pekerjaan → daftar muncul
- [ ] Buat baru (misal "Konsultansi") → Simpan → muncul
- [ ] Edit & Hapus jalan

---

## 7. Kelola Status Pekerjaan

- [ ] Sidebar → Master → Status Pekerjaan → daftar muncul
- [ ] Buat status baru (misal "Selesai") → Simpan
- [ ] Edit & Hapus jalan

---

## 8. Kelola Hari Libur

- [ ] Sidebar → Master → Hari Libur → daftar muncul
- [ ] Buat hari libur: pilih tanggal, isi keterangan → Simpan
- [ ] Edit & Hapus jalan

---

## 9. Kelola Pekerjaan

### Daftar
- [ ] Sidebar → Pekerjaan → daftar muncul dengan tabel + pagination
- [ ] Ketik "kajian" di search → hanya pekerjaan mengandung "kajian" tampil
- [ ] Filter by bidang → daftar terfilter
- [ ] Filter by status → daftar terfilter
- [ ] Klik header kolom → sorting jalan

### Buat Baru
- [ ] Klik "Buat Pekerjaan" → form muncul
- [ ] Isi semua field wajib (nama, bidang, jenis, status) → Simpan → muncul di daftar
- [ ] Kosongkan field wajib → Simpan → error validasi muncul
- [ ] Isi pagu dengan huruf "abc" → error validasi (harus angka)

### Lihat Detail
- [ ] Klik nama pekerjaan di daftar → halaman detail terbuka
- [ ] Semua info tampil: nama, bidang, pagu, nilai kontrak, lokasi, status
- [ ] Tab/section: Vendor, Personil, Termin, Milestone, Dokumen terlihat

### Edit
- [ ] Di halaman detail, klik Edit → form pre-filled
- [ ] Ubah nama → Simpan → nama berubah di daftar
- [ ] Ubah lokasi → Simpan → lokasi terupdate

### Hapus
- [ ] Di daftar, klik hapus pada satu pekerjaan → muncul dialog konfirmasi
- [ ] Klik Ya → pekerjaan hilang dari daftar

---

## 10. Tab Vendor (di detail Pekerjaan)

- [ ] Buka detail pekerjaan → scroll ke tab Vendor
- [ ] Daftar vendor yang assigned muncul (atau kosong)
- [ ] Klik "Tambah Vendor" → pilih dari daftar Perusahaan → Simpan → muncul
- [ ] Klik lepas/detach vendor → vendor hilang dari daftar

---

## 11. Tab Personil (di detail Pekerjaan)

- [ ] Scroll ke tab Personil
- [ ] Klik "Tambah Personil" → pilih tenaga ahli + isi posisi → Simpan
- [ ] Edit posisi → Simpan → berubah
- [ ] Hapus personil → hilang dari daftar

---

## 12. Tab Termin Pembayaran (di detail Pekerjaan)

- [ ] Scroll ke tab Termin
- [ ] Klik "Tambah Termin" → isi: termin ke-1, bobot 30%, deskripsi → Simpan
- [ ] Nilai termin otomatis terhitung (= pagu × 30%)
- [ ] Tambah termin lagi sampai total >100% → error validasi
- [ ] Edit termin → ubah bobot → Simpan → nilai otomatis update
- [ ] Hapus termin → hilang dari daftar

---

## 13. Tab Milestone (di detail Pekerjaan)

- [ ] Scroll ke tab Milestone
- [ ] Klik "Tambah Milestone" → isi judul, deadline, deliverable → Simpan
- [ ] Deadline dihitung dengan skip hari libur & weekend
- [ ] Edit milestone → Simpan → berubah
- [ ] Hapus milestone → hilang

---

## 14. Tab Dokumen (di detail Pekerjaan)

- [ ] Scroll ke tab Dokumen
- [ ] Klik "Upload Dokumen" → pilih file PDF → upload → muncul di daftar dengan nama file
- [ ] Klik download → file ter-download ke komputer
- [ ] Upload file selain PDF/DOCX/XLSX (misal .exe) → ditolak
- [ ] Upload file >10MB → ditolak (kalau ada limit)
- [ ] Hapus dokumen → hilang dari daftar

---

## 15. AI Chatbot — Buka & Chat Biasa

- [ ] Di halaman manapun, ada tombol bulat hijau di pojok kanan bawah
- [ ] Klik tombol → panel chat muncul dengan animasi slide-up
- [ ] Ada pesan selamat datang dari asisten: "Halo! Saya asisten AI DPUTR..."
- [ ] Ketik "halo" → tekan Enter → pesan user muncul di bubble hijau (kanan)
- [ ] Tunggu → balasan AI muncul di bubble putih (kiri) dengan animasi titik-titik dulu
- [ ] Balasan teks rata kiri, spacing rapi, gak ada spasi aneh
- [ ] Klik tombol X → panel chat tertutup

---

## 16. AI Chatbot — Scroll & UI

- [ ] Kirim 10+ pesan → panel bisa di-scroll ke atas untuk lihat history
- [ ] Scroll ke atas → pesan lama terlihat
- [ ] Kirim pesan baru → otomatis scroll ke bawah (ke pesan terbaru)
- [ ] Pesan panjang (>500 karakter) ter-wrap rapi dalam bubble, gak overflow
- [ ] Bold, italic, bullet list di balasan AI ter-render dengan benar

---

## 17. AI Chatbot — Upload File & Parse Dokumen

- [ ] Klik icon paperclip di input bar → file picker muncul
- [ ] Pilih file PDF KAK → chip "📎 nama_file.pdf" muncul di atas input
- [ ] Klik X di chip → chip hilang (file dibatalkan)
- [ ] Upload 3 file: KAK + SPK + RAB → 3 chips muncul
- [ ] Drag-drop file ke area input → chip muncul (sama seperti klik)
- [ ] Ketik "parse dokumen ini" + Enter → AI proses, reply berisi ringkasan field yang ke-extract

---

## 18. AI Chatbot — Buat Pekerjaan Otomatis

- [ ] Upload 3 dokumen (KAK + Kontrak + RAB)
- [ ] Ketik "buatkan pekerjaan dari file ini" → Enter
- [ ] AI reply: menampilkan list field (nama pekerjaan, pagu, nilai kontrak, vendor, lokasi, termin, milestone)
- [ ] Buka menu Pekerjaan → pekerjaan baru ada di daftar
- [ ] Buka detail pekerjaan baru → cek: nama, pagu, nilai kontrak, lokasi terisi
- [ ] Tab Vendor → vendor dari kontrak sudah ter-assign
- [ ] Tab Termin → termin sudah ter-insert sesuai kontrak
- [ ] Tab Milestone → milestone sudah ada

---

## 19. AI Chatbot — Tanya Data Pekerjaan

- [ ] Buka chat → ketik "list pekerjaan saya" → AI jawab daftar pekerjaan
- [ ] Ketik "berapa total pagu semua pekerjaan?" → AI jawab angka
- [ ] Ketik "pekerjaan kajian topografi statusnya apa?" → AI jawab status yang benar

---

## 20. AI Chatbot — Anti-Loop

- [ ] Upload dokumen yang SPK-nya sudah ada di sistem (duplikat)
- [ ] AI tanya "Sudah ada pekerjaan mirip, mau update atau buat baru?" → **hanya 1x**
- [ ] User jawab "pakai existing aja" → AI lanjut ke langkah berikutnya, **BUKAN** tanya ulang
- [ ] Buat pekerjaan dengan nama mirip yang sudah ada → AI tanya 1x, gak loop

---

## 21. Generate Laporan

- [ ] Buka detail pekerjaan yang sudah lengkap (ada vendor, termin, dll)
- [ ] Klik tombol "Generate Laporan" (atau via menu Laporan Export)
- [ ] Pilih jenis: Laporan Pendahuluan → proses jalan
- [ ] File DOCX ter-download / link download muncul
- [ ] Buka file DOCX → cek:
  - [ ] Ada gambar dari template (gak hilang)
  - [ ] Nama vendor benar (bukan template lama)
  - [ ] Lokasi sesuai data pekerjaan
  - [ ] Tidak ada placeholder `[XXX]` atau `{vendor}` tersisa
  - [ ] Tidak ada nama proyek lain bocor ("Sekolah Rakyat" dll)
  - [ ] Isi section relevan dengan deskripsi pekerjaan

---

## 22. Timeline Pekerjaan

- [ ] Sidebar → Timeline → halaman render
- [ ] Tiap pekerjaan tampil sebagai bar horizontal dengan tanggal mulai-selesai
- [ ] Klik salah satu bar → navigasi ke detail pekerjaan

---

## 23. Kalender Laporan

- [ ] Sidebar → Kalender → calendar view bulan ini muncul
- [ ] Event (deadline laporan) tampil di tanggal yang benar
- [ ] Klik event → ke detail terkait

---

## 24. Import Data

- [ ] Sidebar → Import → halaman muncul
- [ ] Upload file XLSX → preview baris tampil
- [ ] Klik Import → data masuk ke sistem
- [ ] Kalau ada row error → laporan error muncul

---

## 25. Laporan Export

- [ ] Sidebar → Laporan Export → halaman muncul
- [ ] Pilih pekerjaan + jenis laporan → klik Generate
- [ ] File DOCX ter-download

---

## 26. Riwayat Notifikasi

- [ ] Sidebar → Notifikasi → daftar notifikasi muncul
- [ ] Notifikasi yang sudah dibaca vs belum bisa dibedakan
- [ ] Klik notifikasi → mark as read

---

## 27. System Settings

- [ ] Sidebar → Settings → halaman setting muncul
- [ ] Ubah setting (misal API key) → Simpan → toast sukses
- [ ] Refresh halaman → setting tetap tersimpan

---

## 28. Activity Log

- [ ] Sidebar → Activity → daftar log muncul
- [ ] Setelah create/edit/hapus pekerjaan → log baru muncul di daftar
- [ ] Filter by user / tanggal jalan

---

## 29. Dark Mode

- [ ] Klik toggle dark mode (biasanya di sidebar/header)
- [ ] Semua halaman berubah ke tema gelap
- [ ] Chat widget juga ikut gelap (bubble, background, input)
- [ ] Klik lagi → kembali ke light mode

---

## 30. Logout & Session

- [ ] Klik Logout → kembali ke login
- [ ] Tekan Back di browser → TIDAK bisa masuk kembali tanpa login ulang
- [ ] Biarkan idle 30+ menit → session expired → redirect ke login saat klik apapun

---

## Test Run Log

| Tanggal | Tester | Fitur | Hasil | Catatan |
|---------|--------|-------|-------|---------|
| 2026-05-19 | claude | Anti-loop (L1+L2) | PASS | 2 run, no loop |
| 2026-05-19 | claude | Laporan topografi | 85% match | sama dgn asli |
| 2026-05-23 | claude | Chat scroll fix | FIXED | hapus justify-content:flex-end |
| | | | | |
