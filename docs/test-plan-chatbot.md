# Test Plan — AI Chatbot shaka-ai (DPUTR PM)

Dokumen induk pengetesan asisten AI (`AiChatService`). Tujuan: yakin chatbot **benar, aman, dan tidak ngawur** — sebelum dipercaya di prod, dan sebagai gerbang sebelum mengganti otak OpenAI → Qwen lokal.

- Versi: 1.0 — 2026-06-25
- Lingkup: `app/Services/AiChatService.php` (46 tool), `app/Livewire/AiChatWidget.php` (upload), OCR queue (`OcrChatUpload`), parser (`KickoffParserService`/`KontrakParserService`/`RabParserService`).
- Status pagar Fase 1 (G1–G7): terpasang di lokal, **belum deploy prod**.

## Peta lapis pengetesan
| Lapis | Nama | Otomatis? | Status | Gunanya |
|---|---|---|---|---|
| 1 | Pagar pengaman (unit) | ✅ otomatis | **SELESAI 17/17** | cegah regresi guardrail |
| 2 | Kemampuan per-tool | ½ manual | belum | tiap jurus benar sendiri-sendiri |
| 3 | Alur penuh (E2E) | manual | belum | skenario nyata end-to-end |
| 4 | Skenario "nakal" | manual + 🔍 | checklist siap | pagar nahan di chatbot sungguhan |
| 5 | Banding model + non-fungsional | semi | belum | gerbang pindah ke Qwen |

---

## Lapis 1 — Pagar pengaman (unit test) ✅ SELESAI
Otomatis, jalan tiap perubahan kode. File: `tests/Unit/AiGuardrailsTest.php` (+ smoke reflection).
Cakupan: G1 klasifikasi write-tool, G2 nilai ketuker, G3a sanity, G3b cross-check (no_spk/nilai/tanggal + normalisasi).
**Hasil: 17/17 lolos.** `php -l` bersih.

Cara jalan (saat dev-deps terpasang):
```bash
composer install            # sekali, ambil phpunit
vendor/bin/phpunit --filter=AiGuardrailsTest
```

**Yang masih perlu ditambah ke Lapis 1 (TODO):** unit test untuk G1 dedup di dalam loop `chat()`, G6 first-non-empty, G7 clearAggregated — perlu mock model/cache, dikerjakan saat dev-deps siap.

---

## Lapis 2 — Kemampuan per-tool (½ manual)
Uji tiap kemampuan terpisah pakai **dokumen dummy**. Cek hasil cocok dokumen; kalau tak kebaca harus **ngaku**, bukan nebak.

| # | Uji | Langkah | Lulus kalau | Hasil |
|---|---|---|---|---|
| 2.1 | Baca KAK | upload 1 KAK, "baca dokumen ini" | nama proyek, pagu, lokasi benar | ☐ |
| 2.2 | Baca kontrak | upload 1 SPK | no SPK, tanggal, nilai kontrak, vendor benar | ☐ |
| 2.3 | Baca RAB | upload 1 RAB/xlsx | item + harga + total benar | ☐ |
| 2.4 | Baca scan | upload kontrak hasil scan | masuk OCR queue → kebaca benar | ☐ |
| 2.5 | Tanya data | "ada berapa proyek?" / "list proyek kritis" | angka cocok dashboard | ☐ |
| 2.6 | Cari file lama | "file yg tadi gue upload mana?" | ketemu via list/find_uploaded_file | ☐ |
| 2.7 | Generate dokumen | "bikin invoice proyek #X" | file ke-generate, link unduh muncul | ☐ |

---

## Lapis 3 — Alur penuh end-to-end (manual)
Skenario nyata dari nol sampai jadi.

| # | Skenario | Langkah | Lulus kalau | Hasil |
|---|---|---|---|---|
| 3.1 | Bikin proyek 1 dokumen | upload KAK → "bikin proyek" | proyek jadi, data dari KAK benar | ☐ |
| 3.2 | Bikin proyek 4 dokumen | drag KAK+kontrak+penawaran+RAB → "bikin proyek tanpa konfirmasi" | proyek jadi **sekali jalan**: vendor ke-assign, personil ke-assign, RAB masuk, jadwal/termin terisi, cross-check jalan | ☐ |
| 3.3 | Lanjutan alur | minta "bikin laporan pendahuluan" | draft ke-generate + link unduh | ☐ |
| 3.4 | Workflow approval | submit laporan harian → approve | status pindah benar | ☐ |
| 3.5 | Termin | ajukan termin → approve | nilai & status benar | ☐ |

**Lulus Lapis 3:** alur 3.2 selesai tanpa nanya berulang, semua data akurat.

---

## Lapis 4 — Skenario "nakal" (adversarial)
Detail lengkap (20 skenario) ada di **`docs/test-lapis4-chatbot.md`**. Ringkas:
- G4 hapus → tong sampah · G2 kontrak>pagu ditolak · G3a data ngawur ditolak
- G3b beda-dokumen ditolak + jalan keluar "saya yakin" + dokumen buram diakui tak terbaca
- G1 SPK dobel tidak muter / tidak kembar · G6 jadwal tidak timpa senyap · G7 proyek B tidak ketularan A

**Gerbang utama kepercayaan:** semua G1–G4 harus hijau.

---

## Lapis 5 — Banding model + non-fungsional (gerbang pindah Qwen)
Dijalankan **sebelum** mengganti OpenAI → Qwen lokal.

### 5a. Akurasi banding (paling penting)
Siapkan **set emas**: 15–20 dokumen + jawaban benar (kunci jawaban) per field kritis (nama, pagu, nilai kontrak, tanggal, no SPK).
1. Jalankan ekstraksi dengan OpenAI → catat % benar per field.
2. Jalankan dengan Qwen → catat % benar.
3. Bandingkan.

| Field | Target OpenAI (baseline) | Qwen | Selisih |
|---|---|---|---|
| nama_pekerjaan | __% | __% | |
| no_spk | __% | __% | |
| nilai_kontrak | __% | __% | |
| nilai_pagu | __% | __% | |
| tanggal_* | __% | __% | |

**Lolos kalau:** Qwen ≥ 85% per field kritis (atau ≥ baseline OpenAI − 5%). Kalau gagal → Fase 6 (few-shot/fine-tune), JANGAN pindah.

### 5b. Tool-calling Qwen
- Uji alur Lapis 3.2 (4 dokumen) dengan Qwen → cek dia bisa memanggil tool berantai tanpa kacau.
- **Lulus kalau:** proyek jadi benar, tidak ngarang tool / argumen.

### 5c. OCR mata (vision) Qwen
- Upload scan → cek model vision lokal (qwen-vl/llava) menghasilkan teks benar.

### 5d. Non-fungsional
| # | Uji | Lulus kalau | Hasil |
|---|---|---|---|
| 5d.1 | Kecepatan | jawaban biasa < ~10 dtk; bikin proyek 4-dok < ~2 mnt | ☐ |
| 5d.2 | Mac Mini mati (failover) | matikan Ollama → chatbot otomatis fallback (OpenAI) / pesan "offline", **tidak hang** | ☐ |
| 5d.3 | Beban | 3-5 chat bersamaan tidak bikin web lain ngadat | ☐ |
| 5d.4 | Jawaban kepotong | minta ringkasan panjang → cek tidak terputus (atur max output) | ☐ |

---

## Kapan dijalankan
- **Lapis 1:** tiap commit (otomatis/CI).
- **Lapis 2–4:** sebelum deploy perubahan chatbot ke prod.
- **Lapis 5:** sekali, sebagai gerbang sebelum ganti otak ke Qwen.

## Bahan yang dibutuhkan (dari user)
- 2-3 KAK, 2-3 kontrak/SPK, 1-2 RAB (dummy / proyek lama, **bukan data sensitif aktif**)
- 1 dokumen scan bagus + 1 dokumen sengaja buram
- 1 proyek lama yang boleh dihapus
- 2 dokumen yang dua-duanya punya jadwal
- Untuk Lapis 5: kunci jawaban 15–20 dokumen (set emas)

## Definisi "SIAP PINDAH KE QWEN"
Semua terpenuhi: Lapis 1 hijau · Lapis 4 G1–G4 hijau · Lapis 5a Qwen ≥ ambang · 5b tool-calling lolos · 5c OCR lolos · 5d.2 failover lolos.
