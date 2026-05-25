# Laporan Writing Playbook — Karta DPUTR

> Methodology untuk **menulis** (bukan sekadar substitusi string) laporan teknis untuk DPUTR Kabupaten Bandung.
>
> **Source**: 6 referensi nyata di `D:/Downloads/coding project/project_management/laporan/` (Pendahuluan + Akhir untuk 3 jenis: geoteknik, topografi, jalan).
>
> **Tujuan**: setiap laporan baru yang dihasilkan Karta harus terlihat seolah-olah ditulis oleh konsultan profesional (PT. Itergo, PT. Adhi Citrabhumi, PT. Purna Wahana), bukan hasil mail-merge mentah.

---

## 0. Prinsip Inti

| Prinsip | Mengapa |
|---|---|
| **Compose, jangan substitute** | Penggantian string literal saja → semantic drift ("Sekolah Rakyat untuk anak prasejahtera" jadi konteks aneh kalau project-nya jalan). Solusi: LLM tulis ulang konten project-specific per-section. |
| **Reuse generic, regenerate specific** | Bab Teori Dasar (Mohr-Coulomb, formula sondir) sama untuk semua proyek geoteknik → REUSE verbatim. Latar Belakang berbeda per proyek → REGENERATE. |
| **Preserve format & gambar** | Template DOCX = vessel buat layout + 27 gambar + heading style. Konten paragraph-level di-overwrite via python-docx. |
| **Mirror tone register** | Bahasa Indonesia formal pemerintahan, passive voice, kalimat majemuk panjang, citation standar (SNI, UU, Permen). |

---

## 1. Universal Document Architecture (semua jenis)

```
COVER (judul + vendor + tahun + logo pemberi kerja)
KATA PENGANTAR                            ← project-specific, 4 paragraf + signature
DAFTAR ISI                                ← auto-generate TOC
DAFTAR TABEL          (akhir-only)
DAFTAR GAMBAR         (akhir-only)
─────────────────────────────────────────
BAB I  PENDAHULUAN                        ← UNIVERSAL — wajib semua jenis
  Latar Belakang                          ← PROJECT-SPECIFIC (LLM compose)
  Maksud & Tujuan                         ← PROJECT-SPECIFIC (LLM compose)
  Lokasi Pekerjaan                        ← PROJECT-SPECIFIC (LLM compose)
  Ruang Lingkup       (opsional)          ← PROJECT-SPECIFIC (bulleted)
  Dasar Hukum         (opsional)          ← GENERIC + project tweaks
BAB II / III (varies per jenis)
  Gambaran Umum Lokasi  (topografi, jalan) ← PROJECT-SPECIFIC
  Metodologi             (semua)          ← GENERIC per-jenis (Mohr-Coulomb / GNSS / Bina Marga)
  Dasar Perencanaan      (geoteknik)      ← GENERIC per-jenis (teori dasar)
BAB IV  Rencana Kerja                     ← SEMI-GENERIC (jadwal + tim sesuai proyek)
─────────────────────────────────────────
[Pendahuluan stops here]
─────────────────────────────────────────
BAB V+  Hasil Survei & Analisis           ← PROJECT-SPECIFIC (data-driven)
BAB N   Kesimpulan & Saran                ← PROJECT-SPECIFIC (composed dari hasil)
LAMPIRAN (akhir-only: siteplan, cross section, dll)
```

**Tipe = superset rule**: `akhir` = `pendahuluan` BAB I-III/IV + BAB Analisis + Kesimpulan + Lampiran.

---

## 2. Section Composition Patterns (Cara Tulis Tiap Section)

Setiap section di bawah punya:
- **STRUKTUR**: jumlah paragraf + arc narasinya
- **TEMPLATE PROMPT**: skeleton kalimat (placeholder dalam `{kurung}`)
- **TONE MARKER**: ciri register yang harus dipertahankan
- **FACT SLOTS**: data dari pekerjaan yang harus muncul

### 2.1 KATA PENGANTAR

**Struktur**: 4 paragraf + signature block, ~150 kata total.

**Arc**:
| Paragraf | Isi | Pola kalimat pembuka |
|---|---|---|
| P1 | Religious greeting + vendor + nama pekerjaan + lokasi | "Puji [dan] syukur kami panjatkan ke hadirat Tuhan Yang Maha Esa, karena atas rahmat dan karunia-Nya, {vendor} sebagai penyedia jasa dapat melaksanakan tugas dan kewajibannya pada pekerjaan {nama_pekerjaan}, {lokasi_short}, sesuai dengan KAK, maka dari itu {vendor} menyiapkan {tipe_laporan} ini." |
| P2 | Tujuan dokumen + ringkasan isi (apa yang dibahas) | "{Tipe_laporan} ini disusun sebagai {bentuk pertanggung-jawaban / tahap awal pelaksanaan} ... memuat {uraian / ringkasan} mengenai {topik 3-5 hal kunci}." |
| P3 | Harapan / aspirasi | "Diharapkan {tipe_laporan} ini dapat {menjadi dasar / memberikan pemahaman / mendukung kelancaran} ... sehingga {outcome teknis}." |
| P4 | Penutup terima kasih | "Akhir kata, kami mengucapkan terima kasih kepada seluruh pihak yang telah memberikan dukungan, kerja sama, dan kepercayaan dalam pelaksanaan pekerjaan ini." |

**Signature block** (selalu di bawah P4):
```
Tim Penyusun
{vendor}

{NAMA_DIREKTUR}
{Jabatan biasanya: Direktur}
```

**Fact slots**: `:vendor` `:nama_pekerjaan` `:lokasi_short` `:tipe_laporan` `:direktur_nama` `:direktur_jabatan`

**Tone markers**: religious opening (mutlak), passive constructions, "kami" plural author voice.

---

### 2.2 LATAR BELAKANG (BAB I.1)

**Struktur**: 4-6 paragraf, narrative arc: macro → meso → micro → problem → solution.

**Arc**:
| Paragraf | Fungsi | Contoh referensi |
|---|---|---|
| P1 | **Macro context** — pernyataan global tentang subjek/sektor | "Jalan merupakan prasarana transportasi darat yang memiliki peranan penting..." / "Dalam rangka mendukung penyediaan sarana dan prasarana pendidikan..." |
| P2 | **Meso/regional context** — lokasi spesifik + status | "Wilayah Soreang sebagai pusat pemerintahan Kabupaten Bandung..." / "Sekolah Rakyat Ciwidey direncanakan sebagai fasilitas pendidikan terpadu yang dibangun pada kawasan perbukitan..." |
| P3 | **Problem statement** — kendala teknis spesifik | "Seiring meningkatnya beban lalu lintas..." / "kondisi topografi yang berlereng memerlukan kajian teknis berupa..." |
| P4 | **Solution frame** — kenapa kajian/DED ini perlu | "Untuk mengatasi permasalahan tersebut diperlukan suatu perencanaan teknis..." / "Kajian ini dilakukan dalam kaitannya untuk menjawab kekhawatiran tersebut..." |
| P5 (opt) | **Closing umbrella** | "Melalui penyusunan {nama_pekerjaan} diharapkan diperoleh {desain/kajian/perencanaan} yang andal, aman, nyaman, dan berkelanjutan..." |
| P6 (opt) | **Output statement** | "Berdasarkan hal tersebut, maka disusun {nama_pekerjaan} pada lokasi seluas {luas_lahan} sebagai dasar teknis..." |

**Tone**: kalimat panjang multi-klausa, banyak konjungsi (`sehingga`, `serta`, `yang`, `oleh karena itu`). Kalimat 30-60 kata. Hindari pernyataan bombastis personal — gunakan suara teknis netral.

**Fact slots**: `:project_subject` `:lokasi_kawasan` `:luas_lahan` `:kondisi_eksisting` `:jenis_keyword` (geoteknik/topografi/jalan/dll).

**LLM Composition Prompt template**:
```
Tulis 4-6 paragraf LATAR BELAKANG laporan {tipe} {jenis} untuk proyek "{nama_pekerjaan}"
di {lokasi_detail}, luas {luas_lahan}, pemberi kerja {pemberi_kerja}.

Arc wajib (ikuti urutan paragraf):
  P1: Macro context untuk sektor/subjek (~3 kalimat)
  P2: Konteks regional/lokasi spesifik (~3 kalimat)
  P3: Problem statement teknis spesifik (~3 kalimat)
  P4: Solution frame — kenapa kajian ini perlu (~3 kalimat)
  P5 (opt): Closing aspiratif

Style:
  - Bahasa Indonesia formal pemerintahan/engineering
  - Passive voice dominan
  - Kalimat majemuk panjang (30-50 kata)
  - Konjungsi: "sehingga", "serta", "oleh karena itu", "berdasarkan"
  - JANGAN sebut nama proyek lain atau lokasi lain
  - JANGAN gunakan "saya", "anda", first-person
```

---

### 2.3 MAKSUD DAN TUJUAN (BAB I.2)

**Struktur**: 1 paragraf "Maksud" + 1 paragraf intro + bulleted list "Tujuan" (4-8 items).

**Pattern**:
```
Maksud {dari kegiatan / pekerjaan} ini adalah {melaksanakan/memberikan/menyusun}
{aktivitas-utama} pada {lokasi+proyek}.

Adapun tujuan dari kegiatan ini adalah sebagai berikut:
  • Memperoleh data {data-yang-dihasilkan} secara {kualifier akurasi}.
  • Mengetahui kondisi {aspek-yang-dianalisis}.
  • Menyusun rencana {output-perencanaan}.
  • Menghitung {kuantitas-output}.
  • Memberikan rekomendasi teknis {sasaran-rekomendasi}.
```

**Rules**:
- Setiap tujuan = noun phrase yang diawali **verb infinitif** (Memperoleh, Mengetahui, Menyusun, Menghitung, Memberikan).
- Tidak boleh ada "saya/kami" di list tujuan.
- Tujuan tertinggi = "memberikan rekomendasi teknis" (selalu paling akhir).

**Fact slots**: `:aktivitas_utama` `:output_perencanaan` `:kuantitas_target` `:sasaran_rekomendasi` (semua per-jenis).

---

### 2.4 LOKASI PEKERJAAN (BAB I.3)

**Struktur**: 1-3 paragraf + caption gambar.

**Pattern**:
```
Lokasi pekerjaan {nama_pekerjaan_short} berada di {alamat_administratif_lengkap}.
{Aksesibilitas — kalau ada}.
{Koordinat UTM kalau ada: "Secara geografis, lokasi kegiatan berada pada koordinat
sekitar X = {xxx}, Y = {yyy} (sistem koordinat UTM), dengan luas wilayah kajian
kurang lebih ± {luas_lahan}."}

Berikut ditampilkan Gambar kondisi lapangan pada lokasi Pekerjaan:

[Gambar 1.1 Lokasi Pekerjaan {project_subject_short}]
[Gambar 1.2 Kondisi Eksisting {kondisi-relevant}]
```

**Fact slots**: `:lokasi_detail` (Desa+Kec+Kab+Provinsi) `:koordinat_utm` `:luas_lahan` `:aksesibilitas`.

---

### 2.5 RUANG LINGKUP PEKERJAAN (BAB I.x, opsional)

**Struktur**: 1 baris intro + bulleted list (6-10 items, masing-masing noun phrase).

**Pattern**:
```
Ruang lingkup pekerjaan dalam kajian ini meliputi:
  • Pelaksanaan {aktivitas-1} di {lokasi}.
  • Pengumpulan dan pengolahan {data-jenis}.
  • Penyusunan {output-1}.
  • Analisis {analisis-target}.
  • Perencanaan {perencanaan-jenis}.
  • Perhitungan {kuantitas-jenis}.
  • Penyusunan laporan teknis {kajian-name}.
```

---

### 2.6 DASAR HUKUM (BAB I.x, opsional)

**Struktur**: 1 baris intro + bulleted list, hierarki regulasi top-down.

**Hierarchy** (dari tertinggi):
1. UU (Undang-Undang)
2. PP (Peraturan Pemerintah)
3. Perpres (Peraturan Presiden)
4. Permen (Peraturan Menteri — PUPR, ATR/BPN, Sosial, dll)
5. Perda (Peraturan Daerah)
6. SNI (Standar Nasional Indonesia)
7. Pedoman/Standar Bina Marga / Dirjen-specific

**Per-jenis preset library** (yang umum muncul):

**Geoteknik**:
- SNI 8460:2017 (Persyaratan perancangan geoteknik)
- SNI 7749 (Pengukuran muka air tanah)

**Topografi**:
- UU 26/2007 (Penataan Ruang)
- UU 28/2002 (Bangunan Gedung)
- PP 16/2021 (Pelaksanaan UU Bangunan Gedung)
- Permen ATR/BPN 14/2021 (Penyusunan Basis Data Peta RTRW)
- Perda Kab. Bandung tentang RTRW

**Jalan**:
- UU 38/2004 (Jalan)
- UU 2/2017 (Jasa Konstruksi)
- PP 34/2006 (Jalan)
- Perpres 16/2018 (Pengadaan)
- Permen PUPR 19/PRT/M/2011, Permen PUPR 31/PRT/M/2015
- SE Dirjen Bina Marga (05/SE/Db/2017, 04/SE/Db/2017, 02/SE/Db/2018)
- Manual Desain Perkerasan Jalan (MDPJ) Bina Marga Revisi 2017
- Spesifikasi Umum Bina Marga 2018
- PKJI (Pedoman Kapasitas Jalan Indonesia)

---

### 2.7 METODOLOGI (BAB II atau III, per-jenis)

**Mostly GENERIC per-jenis** — dapat di-cache di template library.

**Pattern**:
```
{Aktivitas-utama} pada area {project_subject} dapat dilihat pada Gambar 2.1.
Secara garis besar metode pekerjaan yang dilakukan pada lokasi tinjauan meliputi:
{detail-tahapan-spesifik-jenis} ... serta melakukan analisis terhadap
{output-analisis}.

[Gambar 2.1 Bagan Alir Metodologi Pekerjaan]
```

**Per-jenis**:
- **Geoteknik**: Investigasi pendahuluan → survei detail → uji tanah (hand boring, sondir, lab) → analisis SF dengan PLAXIS 2D Mohr-Coulomb
- **Topografi**: Pengukuran terestris (GNSS + Total Station) → pengolahan koordinat UTM → DEM → peta kontur
- **Jalan**: Pendekatan pelaksanaan → pengumpulan data → survei lapangan → analisis lalu lintas/perkerasan/geometrik/drainase → kuantitas-biaya → dokumen DED

---

### 2.8 TEORI DASAR (Geoteknik only — BAB III.2-III.8)

**100% GENERIC** — verbatim copy dari template Itergo, tidak perlu LLM compose.

**Content**:
- Kekuatan geser tanah (definisi + rumus Mohr-Coulomb `S = c' + σ' tan ϕ'`)
- Jenis longsoran: Baji, Busur, Guling
- Pergerakan longsoran: Translasi, Rotasi, Blok, Runtuhan, Rayapan, Aliran
- Penyebab tanah longsor: Hujan, Lereng terjal, Tanah kurang padat, Getaran, Beban tambahan, Pengikisan
- Muka air tanah (SNI 8460 / SNI 7749)
- Bor dangkal (Hand Boring) — alat UDS, tabung thin-walled 7.6 cm × 76 cm
- Sondir / CPT — qc, Hardiyatmo 1992, Rahardjo 2008

**Strategy**: Simpan di `storage/laporan_templates/sections/geoteknik_teori.md` → INCLUDE saat compose.

---

### 2.9 GAMBARAN UMUM LOKASI (Topografi/Jalan — BAB II)

**Semi-PROJECT-SPECIFIC** — paragraf opening project-aware, body details cookie-cutter.

**Pattern** (per sub-section):

```
Letak Geografis dan Administratif
─────────────────────────────────
Lokasi pekerjaan berada di {lokasi_detail}. Secara administratif, wilayah
ini merupakan bagian dari {kawasan-geografis-context}.

Secara geografis, lokasi kegiatan berada pada koordinat sekitar X = {x},
Y = {y} (sistem koordinat UTM), dengan luas wilayah kajian kurang lebih
± {luas_lahan}. Batas-batas wilayah kajian secara umum meliputi:
  • Utara: {...}    • Selatan: {...}
  • Timur: {...}    • Barat: {...}

Kondisi Fisik dan Topografi Wilayah
────────────────────────────────────
Berdasarkan hasil pengamatan lapangan dan pemetaan {topografi/lapangan},
kondisi fisik wilayah kajian {deskripsi terrain — curam/datar/bergelombang},
dengan variasi elevasi {ringkasan elevasi}. {Penggunaan lahan eksisting:
vegetasi alami/perkebunan/jalan setapak}.

Penggunaan Lahan Existing
─────────────────────────
{1-2 paragraf — lahan terbangun vs non-terbangun, vegetasi, aktivitas}.

Kondisi Hidrologi / Drainase Alami
──────────────────────────────────
{1-2 paragraf — sistem drainase alami, alur aliran air, potensi genangan
saat hujan tinggi}.

Aksesibilitas dan Infrastruktur Sekitar
───────────────────────────────────────
{1 paragraf — akses jalan desa/kabupaten, kondisi jalan, kendaraan yang
bisa lewat. Plus infrastruktur pendukung: listrik, permukiman, fasum}.
```

---

### 2.10 HASIL SURVEI DAN ANALISIS (Akhir only — BAB V+ untuk jalan, BAB IV/V untuk topografi/geoteknik)

**100% PROJECT-SPECIFIC** — perlu data hasil. Kalau data belum ada, generate **placeholder** dengan disclaimer:

```
[ANALYSIS PENDING — data hasil survei lapangan akan diisi setelah pengukuran selesai]
```

**Pattern saat data ada**:
- Tabel hasil (Excel/CSV ingest → tabel DOCX)
- Interpretasi per area/segmen
- Komparasi dengan standar (SF ≥ 1,5 untuk geoteknik; LHR vs kapasitas untuk jalan)

---

### 2.11 KESIMPULAN (Akhir only — BAB N.1)

**Struktur**: 1 paragraf opening + bulleted list (5-10 findings dengan angka numerik).

**Pattern**:
```
Berdasarkan hasil {analisis/kajian/pemetaan} {topik-utama} yang dilakukan
pada {lokasi} dengan luas area {luas_lahan}, dapat disimpulkan sebagai
berikut:

  • {Finding 1 dengan numeric value — e.g. "SF eksisting > 1,5 pada kondisi
     normal dan tetap ≥ 1,3 pada kondisi pembangunan"}
  • {Finding 2 — e.g. "Volume cut and fill sebesar X m³ dan Y m³"}
  • {Finding 3 — e.g. "Alternatif perkuatan terbaik adalah ..."}
  • ...
```

**Rules**:
- **Setiap bullet harus mengandung angka konkret** (SF value, volume, %, dimensi)
- **Reference standar** yang dipenuhi (SNI 8460:2017, MDPJ 2017, dll)
- Hindari opini subjektif — semua claim harus traceable ke section analisis sebelumnya

**Fact slots**: `:hasil_numerik` (dict of metric → value), `:standar_referensi`.

---

### 2.12 SARAN / REKOMENDASI (Akhir only — BAB N.2)

**Struktur**: 1 paragraf opening + bulleted list (4-8 actionable items).

**Pattern**:
```
Berdasarkan kesimpulan tersebut, maka rekomendasi teknis yang dapat
diberikan adalah sebagai berikut:

  • {Tindakan-1 dengan justification teknis — e.g. "Pelaksanaan pematangan
     lahan sebaiknya dilakukan sesuai dengan elevasi rencana dan konsep
     terasering yang telah disusun..."}
  • {Tindakan-2 — e.g. "Pekerjaan galian dan timbunan harus memperhatikan
     keseimbangan volume (cut and fill)..."}
  • {Tindakan-3 dengan referensi standar — e.g. "Pengawasan mutu (QA/QC)
     perlu diterapkan pada seluruh pekerjaan, termasuk material bronjong,
     mutu beton/grout..."}
  • ...
```

**Rules**:
- **Setiap item = imperative recommendation + technical justification**
- Verba: `disarankan`, `wajib`, `perlu`, `sebaiknya`, `harus`, `direkomendasikan`
- Range cakupan: konstruksi → pengawasan → pemeliharaan → kajian lanjutan

---

## 3. Vocab Lexicon (per jenis)

Gunakan istilah yang **konsisten dengan referensi profesional**. Kalau LLM nyebut istilah outside lexicon, flag.

### 3.1 Geoteknik / Stabilitas Lereng
| Kategori | Istilah |
|---|---|
| Software | PLAXIS 2D, finite element method (FEM), Mohr-Coulomb model |
| Parameter | kohesi (c), sudut geser (ϕ), Modulus Young (E), Poisson ratio (ν), sudut dilantasi (ψ), γsat, γunsat |
| Metrik | Safety Factor (SF), faktor keamanan, angka keamanan |
| Tipe longsoran | Baji, Busur, Guling, Translasi, Rotasi, Blok, Runtuhan, Rayapan, Aliran |
| Perkuatan | DPT (Dinding Penahan Tanah), bronjong, cerucuk, strauss pile, bored pile, soil nailing |
| Pengujian | Bor dangkal (Hand Boring), Sondir (Cone Penetration Test/CPT), SPT, UDS (Undisturbed Sample) |
| Standar | SNI 8460:2017, SNI 7749 |

### 3.2 Topografi / Pematangan Lahan
| Kategori | Istilah |
|---|---|
| Alat | GNSS (Global Navigation Satellite System), Total Station, GPS Geodetik |
| Datum | UTM (Universal Transverse Mercator), datum geodetik nasional |
| Output | Peta topografi, peta kontur, DEM (Digital Elevation Model), SIG (Sistem Informasi Geografis) |
| Konsep | Cut and fill, grading plan, terasering, elevasi rencana |
| Kontrol | Bench Mark (BM), titik kontrol, jaring kontrol, pengukuran terestris |

### 3.3 Jalan / DED
| Kategori | Istilah |
|---|---|
| Standar | MDPJ (Manual Desain Perkerasan Jalan) Bina Marga 2017, Spesifikasi Umum Bina Marga 2018, PKJI |
| Komponen | Perkerasan lentur, perkerasan kaku (rigid pavement), geometrik jalan, drainase jalan, perlengkapan jalan |
| Lalu lintas | LHR (Lalu Lintas Harian Rata-rata), volume lalu lintas, kapasitas jalan, tingkat pelayanan |
| Output | DED (Detail Engineering Design), gambar rencana, RAB (Rencana Anggaran Biaya) |
| Personil | Team Leader, Asisten Tenaga Ahli Geodesi, Asisten Tenaga Ahli Teknik Jalan, Surveyor |

---

## 4. Style Register Checklist

Sebelum laporan dirilis, harus pass checklist ini:

| # | Cek | Pass kriteria |
|---|---|---|
| 1 | Indonesian formal register | Tidak ada kata gaul, tidak ada "saya"/"anda" |
| 2 | Passive voice dominan | >60% kalimat passive (dilaksanakan, diharapkan, dilakukan) |
| 3 | Kalimat majemuk panjang | Rata-rata 25-45 kata per kalimat |
| 4 | Konjungsi formal | "sehingga", "serta", "oleh karena itu", "berdasarkan", "yang" |
| 5 | Citation in-text | (Author, Year) atau (SNI-XXXX, YYYY) format |
| 6 | Figure callouts | "dapat dilihat pada Gambar X.Y", "Berikut ditampilkan..." |
| 7 | Numbered/bulleted list | Tujuan, ruang lingkup, dasar hukum, kesimpulan, rekomendasi |
| 8 | Tidak ada nama proyek lain | Cek tidak muncul "Sekolah Rakyat" / "Soreang" kalau bukan proyek itu |
| 9 | Tidak ada placeholder text | Tidak ada `{xxx}` atau "TBD" yang lolos |
| 10 | Signature block correct | Tim Penyusun / vendor / NAMA / Direktur |

---

## 5. Compose vs Reuse Decision Matrix

Per section, putuskan: **COMPOSE** (LLM generate) atau **REUSE** (cached generic) atau **HYBRID**.

| Section | Pendahuluan | Akhir | Strategy |
|---|---|---|---|
| Kata Pengantar | ✏️ Compose | ✏️ Compose | Composes from template prompt + facts |
| Latar Belakang | ✏️ Compose | ✏️ Compose | Compose, full narrative arc |
| Maksud & Tujuan | ✏️ Compose | ✏️ Compose | Compose intro + bulleted tujuan per-jenis |
| Lokasi Pekerjaan | ✏️ Compose | ✏️ Compose | Compose from facts, include img caption |
| Ruang Lingkup | 📋 Hybrid | 📋 Hybrid | Generic bulleted list per-jenis + tweaks |
| Dasar Hukum | 📋 Hybrid | 📋 Hybrid | Per-jenis preset library + project-specific Perda |
| Gambaran Umum Lokasi | ✏️ Compose | ✏️ Compose | (topografi/jalan only) — generate from facts |
| Metodologi | 🔁 Reuse | 🔁 Reuse | Generic per-jenis, cached |
| Teori Dasar | 🔁 Reuse | 🔁 Reuse | (geoteknik) — verbatim copy from cache |
| Rencana Kerja | 📋 Hybrid | — | Jadwal dari pekerjaan.tanggal_*, tim dari personil |
| Hasil Survei & Analisis | — | ✏️ Compose | (akhir-only) compose from data; placeholder if no data |
| Kesimpulan | — | ✏️ Compose | Compose from analisis findings |
| Saran / Rekomendasi | — | ✏️ Compose | Compose from kesimpulan |

Legend: ✏️ Compose | 🔁 Reuse | 📋 Hybrid

---

## 6. Generic Section Library (Cached Reusable Content)

Lokasi: `storage/laporan_templates/sections/{jenis}/{section}.md`

```
sections/
├── geoteknik/
│   ├── teori_dasar.md              ← BAB III.2 verbatim
│   ├── jenis_longsoran.md          ← BAB III.3-III.4
│   ├── penyebab_longsor.md         ← BAB III.5
│   ├── muka_air_tanah.md           ← BAB III.6
│   ├── bor_dangkal.md              ← BAB III.7
│   ├── sondir_cpt.md               ← BAB III.8
│   ├── metode_analisis_plaxis.md   ← BAB III.1
│   └── dasar_hukum_preset.md
├── topografi/
│   ├── metode_survey.md
│   ├── sistem_koordinat.md
│   ├── jaringan_kontrol.md
│   ├── prosedur_pengukuran.md
│   ├── pengolahan_data.md
│   └── dasar_hukum_preset.md
└── jalan/
    ├── pendekatan_pelaksanaan.md
    ├── analisis_lalu_lintas.md
    ├── analisis_perkerasan.md
    ├── analisis_drainase.md
    ├── personil_standard.md        ← Team Leader, Asisten, Surveyor
    └── dasar_hukum_preset.md
```

**Pattern**: setiap `.md` file = single section content yang bisa di-include verbatim. Update jika ada perubahan referensi (revisi standar, dll).

---

## 7. The Pipeline (How It All Comes Together)

```
[1] pekerjaan record (id, nama, jenis, lokasi, vendor, dll)
        ↓
[2] LaporanComposerService::compose(pekerjaan, tipe='pendahuluan')
        ↓
[3] detectJenis(pekerjaan) → 'geoteknik' | 'topografi' | 'jalan' | ...
        ↓
[4] FOR EACH section IN getJenisSectionList(jenis, tipe):
        ├─ IF compose-strategy = REUSE → load sections/{jenis}/{section}.md
        ├─ IF compose-strategy = HYBRID → load + tweak with facts
        └─ IF compose-strategy = COMPOSE → call LLM with:
              - section composition prompt (§2.x)
              - facts dari pekerjaan
              - vocab lexicon untuk jenis (§3.x)
              - style register guide (§4)
              → capture composed text
        ↓
[5] Assemble final DOCX:
        ├─ Load template skeleton (preserve format + images)
        ├─ FOR EACH section, REPLACE template content with composed/reused text
        └─ Save as dokumen/generated/{pekerjaan_id}/laporan_{tipe}_{jenis}_composed_{ts}.docx
        ↓
[6] Validate against §4 style checklist (regex + LLM judge)
        ├─ Pass → insert dokumen DB row
        └─ Fail → log + show flag in UI
```

---

## 8. Quality Gates

Setiap laporan yang dihasilkan harus pass quality gates ini:

| Gate | Auto-check | Method |
|---|---|---|
| G1 | Style checklist §4 ≥ 8/10 | Regex + heuristic |
| G2 | No leak nama proyek lain | Grep "Sekolah Rakyat" / "Soreang" / "Itergo" jika bukan proyek itu |
| G3 | Section count matches expected skeleton | Compare against jenis × tipe skeleton |
| G4 | Citations valid (SNI / UU / Permen exist in dasar_hukum preset) | Cross-ref check |
| G5 | Numeric findings in Kesimpulan (akhir-only) | Regex angka + persentase |
| G6 | LLM judge: "tone matches reference?" (sample-based) | Call LLM with reference paragraph + composed paragraph, judge similarity |

---

## 9. Fact Slots — Complete Schema

Semua data yang diperlukan composer, sumbernya, dan fallback:

| Slot | Sumber Primary | Fallback | Required? |
|---|---|---|---|
| `:vendor` | `perusahaan.nama` | — | ✅ |
| `:vendor_short` | `perusahaan.nama_short` | strip "PT./CV." | ✅ |
| `:direktur_nama` | `perusahaan.direktur_nama` (field baru) | "[NAMA DIREKTUR]" | ⚠️ |
| `:direktur_jabatan` | `perusahaan.direktur_jabatan` | `"Direktur"` | opt |
| `:nama_pekerjaan` | `pekerjaan.nama_pekerjaan` | — | ✅ |
| `:project_subject` | `nama_pekerjaan` strip prefix | `nama_pekerjaan` | ✅ |
| `:jenis_keyword` | `detectJenis()` | "lainnya" | ✅ |
| `:tipe_laporan` | "Laporan Pendahuluan"/"Laporan Akhir" | — | ✅ |
| `:lokasi_detail` | `pekerjaan.lokasi` | `"Kabupaten Bandung, Jawa Barat"` | ✅ |
| `:lokasi_short` | derive from `lokasi` | `"Kabupaten Bandung"` | ✅ |
| `:koordinat_utm` | `pekerjaan.koordinat_utm` (field baru) | "—" | opt |
| `:luas_lahan` | `pekerjaan.luas_lahan` (field baru) | "—" | opt |
| `:pemberi_kerja` | constant | `"Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung"` | ✅ |
| `:pemberi_kerja_short` | constant | `"DPUTR"` | ✅ |
| `:tahun` | `pekerjaan.tahun_anggaran` | current year | ✅ |
| `:no_spk` | `pekerjaan.no_spk` | "—" | opt |
| `:no_spmk` | `pekerjaan.no_spmk` | "—" | opt |
| `:tanggal_mulai` | `pekerjaan.tanggal_mulai` | — | opt |
| `:tanggal_akhir` | `pekerjaan.tanggal_akhir` | — | opt |
| `:personil[]` | `pekerjaan.personil` relation | "[Tim ditentukan]" | opt |
| `:milestones[]` | `pekerjaan.milestones` relation | [] | opt |
| `:hasil_numerik` | `pekerjaan.hasil_kajian` (field baru, json) | `[]` | opt (akhir-only) |

**Action items** untuk extend schema:
- Add fields ke `perusahaan` table: `nama_short`, `direktur_nama`, `direktur_jabatan`
- Add fields ke `pekerjaan` table: `koordinat_utm`, `luas_lahan`, `hasil_kajian` (json)
- Add `no_spmk` ke `pekerjaan` jika belum ada

---

## 10. Anti-patterns (Don'ts)

| ❌ Don't | ✅ Do |
|---|---|
| String replace "Sekolah Rakyat" → nama proyek lain | Compose ulang Latar Belakang dari scratch via LLM |
| Pakai bahasa percakapan ("kita akan..", "yuk..") | Pasif formal ("akan dilakukan", "diperlukan...") |
| Bullet list dengan kalimat lengkap | Bullet list dengan noun phrase / imperative |
| Citation gaya APA (Smith et al., 2020) inline | Citation Indo formal (Hardiyatmo, 1992) atau (SNI-7749, 2004) |
| Skip section Teori Dasar | Always include — itu yang bikin laporan terlihat substantive |
| Compose dari training data tanpa fact slots | Selalu inject pekerjaan facts ke prompt |
| Generate Kesimpulan tanpa angka | Setiap bullet kesimpulan harus ada metric numerik |

---

## 11. References (Source Documents Used to Derive This Playbook)

| File | Jenis | Tipe | Vendor | Insights |
|---|---|---|---|---|
| Laporan Pendahuluan Analisis Stabilitas Lereng SR.docx | geoteknik | pendahuluan | PT. Itergo Buana Utama | Kata Pengantar 4-paragraf formula, Teori Dasar verbatim chunk |
| Laporan Akhir Analisis Stabilitas Lereng SR.docx | geoteknik | akhir | PT. Itergo Buana Utama | Kesimpulan bulleted dgn SF values, Saran imperative |
| Laporan Pendahuluan Kajian Topografi & Pematangan Lahan SR.docx | topografi | pendahuluan | PT. Purna Wahana Lestari | Lokasi dengan koordinat UTM, Dasar Hukum hierarchy |
| Laporan Akhir Kajian Topografi & Pematangan Lahan SR.docx | topografi | akhir | PT. Purna Wahana Lestari | Cut & Fill analysis, Kesimpulan + Rekomendasi format |
| Lap. Pendahuluan.pdf (DED Jalan Soreang) | jalan | pendahuluan | PT. ADHI CITRABHUMI UTAMA | 9-section BAB I deepest hierarchy, Personil detail |
| Lap. Akhir.pdf (DED Jalan Soreang) | jalan | akhir | PT. ADHI CITRABHUMI UTAMA | BAB V hasil survei + analisis, BAB VI kesimpulan |
