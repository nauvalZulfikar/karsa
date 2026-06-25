# Test Lapis 4 — Skenario "Nakal" Chatbot shaka-ai

Tujuan: buktikan 7 pagar pengaman (G1–G7) beneran nahan di chatbot sungguhan, bukan cuma di tes otomatis.
Cara pakai: jalankan tiap skenario lewat chat di panel admin, bandingkan hasil nyata vs kolom "Harus terjadi". Centang Lulus/Gagal.

> Catatan: pakai **dokumen dummy / proyek lama**, JANGAN data aktif sensitif. Beberapa cek perlu lihat database/log — ditandai 🔍 (aku bisa bantu cek).

## Persiapan bahan
- [ ] 1 kontrak/SPK jelas (ada no SPK, tanggal, nilai kontrak, pagu)
- [ ] 1 kontrak hasil **scan/foto** yang bagus
- [ ] 1 dokumen sengaja **buram/jelek** (susah dibaca)
- [ ] 1 proyek lama yang boleh dihapus (buat tes hapus)
- [ ] 2 dokumen yang dua-duanya punya jadwal pelaksanaan (buat G6)

---

## G4 — Hapus = tong sampah, bukan lenyap
| # | Langkah | Harus terjadi (LULUS) | Hasil |
|---|---|---|---|
| 4.1 | Minta: "hapus proyek #[ID]" → konfirmasi ya | Balasan bilang "dipindahkan ke tong sampah (bisa dipulihkan)", bukan "dihapus permanen" | ☐ |
| 4.2 | 🔍 Cek proyek tsb masih ada sebagai data ter-soft-delete (belum musnah) | Masih bisa dipulihkan admin | ☐ |

## G2 — Nilai kontrak tidak boleh > pagu
| # | Langkah | Harus terjadi (LULUS) | Hasil |
|---|---|---|---|
| 2.1 | Minta bikin proyek: pagu 100jt, **nilai kontrak 150jt** | Ditolak, pesan "kemungkinan ketukar" | ☐ |
| 2.2 | Minta update proyek lama: set nilai kontrak melebihi pagunya | Ditolak | ☐ |
| 2.3 | Bikin proyek normal: pagu 500jt, kontrak 480jt | Lolos, proyek jadi | ☐ |

## G3a — Tolak data ngawur (tanpa dokumen)
| # | Langkah | Harus terjadi (LULUS) | Hasil |
|---|---|---|---|
| 3a.1 | Minta bikin proyek **tanpa nama** / nama cuma "string" | Ditolak (nama placeholder/kosong) | ☐ |
| 3a.2 | Bikin proyek dengan tanggal SPK **tahun 1999** | Ditolak (di luar rentang wajar) | ☐ |
| 3a.3 | Bikin proyek dengan nilai pagu **0 / minus** | Ditolak (harus > 0) | ☐ |

## G3b — Anti-ngarang (dicocokkan dokumen)
| # | Langkah | Harus terjadi (LULUS) | Hasil |
|---|---|---|---|
| 3b.1 | Upload kontrak jelas → minta bikin proyek, **tapi sebut nilai kontrak beda** dari dokumen | Ditolak: "beda dengan dokumen, pakai angka dokumen" | ☐ |
| 3b.2 | Sama, tapi **nomor SPK diganti** | Ditolak | ☐ |
| 3b.3 | Setelah ditolak 3b.1, bilang "ya saya yakin, tetap pakai angka itu" | Boleh lanjut (lewat force) — **pagar tidak bikin user stuck** | ☐ |
| 3b.4 | Upload dokumen **buram** → minta bikin proyek | Chatbot **ngaku tidak terbaca / minta upload ulang**, BUKAN ngisi angka tebakan | ☐ |
| 3b.5 | Upload kontrak **hasil scan** → minta baca | Diproses OCR di background lalu kebaca benar (atau status "sedang dibaca"), bukan ngarang | ☐ |

## G1 — Anti-loop (tidak muter, tidak dobel)
| # | Langkah | Harus terjadi (LULUS) | Hasil |
|---|---|---|---|
| 1.1 | Buat 1 proyek. Lalu minta bikin proyek lagi dengan **no SPK yang sama** | Diberi tahu sudah ada, **tanya 1× saja**, tidak muter | ☐ |
| 1.2 | Saat ditanya "pakai existing atau baru?", jawab ambigu ("hmm", "gimana ya") | Tidak nge-spam pertanyaan / tidak bikin proyek dobel | ☐ |
| 1.3 | Dalam 1 percakapan, dorong dia bikin proyek **2× dengan data identik** | Yang kedua diblokir: "sudah dijalankan barusan, tidak diulang" | ☐ |
| 1.4 | 🔍 Cek tidak ada proyek kembar di database | Hanya 1 yang kebuat | ☐ |

## G6 — Banyak dokumen tidak saling timpa diam-diam
| # | Langkah | Harus terjadi (LULUS) | Hasil |
|---|---|---|---|
| 6.1 | Upload 2 dokumen yang **dua-duanya punya jadwal** sekaligus → proses | Jadwal dari dokumen pertama dipakai | ☐ |
| 6.2 | 🔍 Cek log: ada peringatan "jadwal dokumen kedua diabaikan (slot terisi)" | Kehilangan data ter-catat, tidak senyap | ☐ |

## G7 — Proyek baru tidak ketularan data proyek sebelumnya
| # | Langkah | Harus terjadi (LULUS) | Hasil |
|---|---|---|---|
| 7.1 | Bikin proyek A dari kontrak yang **punya termin & jadwal** | Proyek A jadi dengan termin/jadwalnya | ☐ |
| 7.2 | Langsung lanjut bikin proyek B dari dokumen **tanpa termin/jadwal** | Proyek B **kosong** termin/jadwal — TIDAK kebawa punya A | ☐ |

---

## Ringkasan kelulusan
- Total skenario: 20
- Lulus: ___ / 20
- Gagal (catat nomornya): ___________

**Kriteria lolos Lapis 4:** semua skenario G1/G2/G3/G4 LULUS (pagar inti). G6/G7 sebaiknya lulus; kalau gagal, catat untuk perbaikan.

> Skenario ber-🔍 (cek database/log) — minta bantuan untuk verifikasi teknisnya.
