<?php

namespace App\Services;

use App\Models\Pekerjaan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generate dokumen output (Invoice, Laporan, Surat) dari template + data.
 *
 * Output disimpan di storage/app/dokumen/generated/{pekerjaan_id}/{filename}.pdf
 * Bisa di-download via signed URL.
 */
class DocumentGeneratorService
{
    /**
     * Generate Laporan Pendahuluan (DOCX) — uses Geoteknik template + KAK/Kontrak data.
     */
    public function generateLaporanPendahuluan(int $pekerjaanId, array $opts = []): array
    {
        return $this->generateLaporanDocx($pekerjaanId, 'pendahuluan');
    }

    /**
     * Generate Laporan Akhir (DOCX) — like Pendahuluan + Bab 4-6 from daily reports.
     */
    public function generateLaporanAkhir(int $pekerjaanId, array $opts = []): array
    {
        return $this->generateLaporanDocx($pekerjaanId, 'akhir');
    }

    private function generateLaporanDocx(int $pekerjaanId, string $jenis): array
    {
        $pekerjaan = Pekerjaan::with(['perusahaan', 'personil.tenagaAhli', 'jenisPekerjaan'])->find($pekerjaanId);
        if (!$pekerjaan) throw new \RuntimeException("Pekerjaan ID {$pekerjaanId} tidak ditemukan.");

        // === Composer path (PREFERRED) ===
        // LLM-composed content per Playbook, BUKAN string substitute. Quality > simplicity.
        // Disable via config('services.laporan_composer.enabled', true) = false jika butuh fallback ke substitute.
        if (config('services.laporan_composer.enabled', true)) {
            try {
                $composed = app(LaporanComposerService::class)->compose($pekerjaan, $jenis);
                if (!empty($composed['ok'])) {
                    return $this->finalizeFromComposer($pekerjaan, $jenis, $composed);
                }
            } catch (\Throwable $e) {
                logger()->warning("Composer fail, fallback ke template substitute: " . $e->getMessage());
            }
        }

        // === Template-based path (fallback 1) ===
        // Kalau ada template DOCX per jenis_pekerjaan, pakai itu (preserve formatting + images).
        try {
            $tmpl = app(LaporanTemplateService::class)->render($pekerjaan, $jenis);
            if ($tmpl !== null) {
                return $this->finalizeFromTemplate($pekerjaan, $jenis, $tmpl);
            }
        } catch (\Throwable $e) {
            logger()->warning("Template render fail, fallback ke basic generator: " . $e->getMessage());
        }

        // === Fallback: basic generic generator (cover + bab 1-3 hardcoded) ===
        $php = new \PhpOffice\PhpWord\PhpWord();
        $section = $php->addSection();

        // Cover
        $section->addText('LAPORAN ' . strtoupper($jenis), ['size' => 22, 'bold' => true], ['alignment' => 'center']);
        $section->addTextBreak(2);
        $section->addText($pekerjaan->nama_pekerjaan, ['size' => 18, 'bold' => true], ['alignment' => 'center']);
        $section->addTextBreak(5);
        $section->addText('Disusun oleh:', ['size' => 12], ['alignment' => 'center']);
        $section->addText($pekerjaan->perusahaan?->nama ?? '-', ['size' => 14, 'bold' => true], ['alignment' => 'center']);
        $section->addTextBreak(1);
        $section->addText($pekerjaan->tanggal_spk?->format('F Y') ?? date('F Y'), ['size' => 12], ['alignment' => 'center']);
        $section->addPageBreak();

        // Kata Pengantar
        $section->addText('KATA PENGANTAR', ['size' => 16, 'bold' => true]);
        $section->addTextBreak(1);
        $direktur = $pekerjaan->perusahaan?->pic_nama ?? 'Direktur';
        $section->addText(
            "Puji dan syukur kami panjatkan ke hadirat Tuhan Yang Maha Esa, karena atas rahmat dan karunia-Nya, "
            . ($pekerjaan->perusahaan?->nama ?? 'tim penyusun')
            . " dapat menyelesaikan Laporan " . ucfirst($jenis) . " pekerjaan {$pekerjaan->nama_pekerjaan}."
        );
        $section->addTextBreak(1);
        $section->addText("Laporan ini disusun sebagai bentuk pertanggungjawaban atas pelaksanaan pekerjaan yang telah dilakukan sesuai dengan ruang lingkup dalam Kerangka Acuan Kerja (KAK).");
        $section->addTextBreak(3);
        $section->addText('Tim Penyusun', ['italic' => true]);
        $section->addText($pekerjaan->perusahaan?->nama ?? '-', ['bold' => true]);
        $section->addTextBreak(2);
        $section->addText($direktur, ['bold' => true, 'underline' => 'single']);
        $section->addText('Direktur');
        $section->addPageBreak();

        // BAB 1 Pendahuluan
        $section->addText('BAB 1 PENDAHULUAN', ['size' => 16, 'bold' => true]);
        $section->addText('1.1 Latar Belakang', ['size' => 14, 'bold' => true]);
        $section->addText("Pekerjaan {$pekerjaan->nama_pekerjaan} merupakan kegiatan jasa konsultansi yang dilaksanakan dengan dana APBD Kabupaten Bandung Tahun Anggaran " . ($pekerjaan->tahun_anggaran ?? date('Y')) . ". Lokasi pekerjaan berada di Kabupaten Bandung dengan nilai kontrak Rp " . number_format((float) $pekerjaan->nilai_kontrak, 0, ',', '.') . ".");
        $section->addTextBreak(1);
        $section->addText('1.2 Maksud dan Tujuan', ['size' => 14, 'bold' => true]);
        $section->addText("Memberikan hasil analisis teknis sesuai ruang lingkup KAK, menghasilkan dokumen perencanaan yang dapat diacu untuk tahap pelaksanaan konstruksi.");
        $section->addTextBreak(1);
        $section->addText('1.3 Lokasi Pekerjaan', ['size' => 14, 'bold' => true]);
        $section->addText("Lokasi pekerjaan berada di Kabupaten Bandung, Jawa Barat.");
        $section->addPageBreak();

        // BAB 2 Metodologi (boilerplate generic - per jenis pekerjaan bisa di-customize nanti)
        $section->addText('BAB 2 METODOLOGI PEKERJAAN', ['size' => 16, 'bold' => true]);
        $section->addText("Metodologi pelaksanaan mengacu pada tahapan: persiapan, survey lapangan, analisis data, dan penyusunan laporan.");

        // BAB 3 Tim
        $section->addPageBreak();
        $section->addText('BAB 3 ORGANISASI TIM', ['size' => 16, 'bold' => true]);
        if (count($pekerjaan->personil) > 0) {
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);
            $table->addRow();
            $table->addCell(800)->addText('No', ['bold' => true]);
            $table->addCell(3000)->addText('Nama', ['bold' => true]);
            $table->addCell(3500)->addText('Jabatan', ['bold' => true]);
            $table->addCell(2500)->addText('Honor (Rp)', ['bold' => true]);
            foreach ($pekerjaan->personil as $i => $p) {
                $table->addRow();
                $table->addCell(800)->addText((string) ($i + 1));
                $table->addCell(3000)->addText($p->tenagaAhli?->nama ?? '-');
                $table->addCell(3500)->addText($p->jabatan_kontrak);
                $table->addCell(2500)->addText(number_format((float) $p->nilai_honor_kontrak, 0, ',', '.'));
            }
        } else {
            $section->addText('(Belum ada personil terdaftar)');
        }

        // BAB 4-6 untuk Akhir saja
        if ($jenis === 'akhir') {
            $section->addPageBreak();
            $section->addText('BAB 4 HASIL PEKERJAAN LAPANGAN', ['size' => 16, 'bold' => true]);
            $section->addText("Data hasil survey/pengujian akan diinput melalui daily report. Bagian ini akan ter-update otomatis saat data lapangan tersedia.");
            $section->addPageBreak();
            $section->addText('BAB 5 ANALISIS & SIMULASI', ['size' => 16, 'bold' => true]);
            $section->addText("Hasil analisis teknis berdasarkan data yang dikumpulkan.");
            $section->addPageBreak();
            $section->addText('BAB 6 KESIMPULAN & REKOMENDASI', ['size' => 16, 'bold' => true]);
            $section->addText("Rekomendasi teknis akan disusun setelah analisis selesai.");
        }

        $slug = \Illuminate\Support\Str::slug($pekerjaan->nama_pekerjaan);
        $stamp = now()->format('YmdHis');
        $filename = "laporan_{$jenis}_{$slug}_{$stamp}.docx";
        $relativePath = "dokumen/generated/{$pekerjaanId}/{$filename}";

        $absPath = Storage::disk('local')->path($relativePath);
        if (!is_dir(dirname($absPath))) mkdir(dirname($absPath), 0755, true);
        \PhpOffice\PhpWord\IOFactory::createWriter($php, 'Word2007')->save($absPath);

        $size = filesize($absPath) / 1024;

        // Insert/refresh record di tabel dokumen supaya muncul di Pekerjaan detail.
        // NB: enum 'tipe' belum punya 'laporan_pendahuluan', pakai 'lainnya' + tag di nama.
        $tipe = $jenis === 'akhir' ? 'laporan_akhir' : 'lainnya';
        $namaDokumen = "Laporan " . ucfirst($jenis) . " — " . $pekerjaan->nama_pekerjaan;
        $existing = \App\Models\Dokumen::where('pekerjaan_id', $pekerjaanId)
            ->where('nama_dokumen', 'LIKE', 'Laporan ' . ucfirst($jenis) . ' —%')
            ->latest('id')->first();
        $versi = $existing ? (((int) preg_replace('/\D/', '', (string) $existing->versi)) + 1) : 1;
        $dokumen = \App\Models\Dokumen::create([
            'pekerjaan_id'       => $pekerjaanId,
            'tipe'               => $tipe,
            'nama_dokumen'       => $namaDokumen,
            'versi'              => (string) $versi,
            'file_path'          => $relativePath,
            'file_original_name' => $filename,
            'file_size'          => (int) filesize($absPath),
            'keterangan'         => 'Auto-generated by Karta AI (tipe asli: laporan_' . $jenis . ')',
            'created_by'         => auth()->id(),
        ]);

        return [
            'pekerjaan_id' => $pekerjaanId,
            'dokumen_id'   => $dokumen->id,
            'jenis'        => $jenis,
            'filename'     => $filename,
            'path'         => $relativePath,
            'size_kb'      => round($size, 1),
            'download_url' => url(route('dokumen.download', $dokumen)),
            'mode'         => 'generic_fallback',
        ];
    }

    /**
     * Persist + return result dari LaporanComposerService output.
     * Mirip finalizeFromTemplate tapi sumbernya dari composer (LLM-generated content).
     */
    private function finalizeFromComposer(Pekerjaan $pekerjaan, string $jenis, array $composed): array
    {
        $absPath      = $composed['output_path'];
        $filename     = $composed['filename'];
        $relativePath = $composed['relative_path'];
        $sizeBytes    = $composed['size_bytes'];

        $tipe = $jenis === 'akhir' ? 'laporan_akhir' : 'lainnya';
        $namaDokumen = "Laporan " . ucfirst($jenis) . " — " . $pekerjaan->nama_pekerjaan;
        $existing = \App\Models\Dokumen::where('pekerjaan_id', $pekerjaan->id)
            ->where('nama_dokumen', 'LIKE', 'Laporan ' . ucfirst($jenis) . ' —%')
            ->latest('id')->first();
        $versi = $existing ? (((int) preg_replace('/\D/', '', (string) $existing->versi)) + 1) : 1;

        $qSummary = '';
        if (!empty($composed['quality'])) {
            $scores = array_map(fn($q) => $q['score'] ?? '?', $composed['quality']);
            $qSummary = ' | quality scores: ' . json_encode($scores);
        }

        $dokumen = \App\Models\Dokumen::create([
            'pekerjaan_id'       => $pekerjaan->id,
            'tipe'               => $tipe,
            'nama_dokumen'       => $namaDokumen,
            'versi'              => (string) $versi,
            'file_path'          => $relativePath,
            'file_original_name' => $filename,
            'file_size'          => $sizeBytes,
            'keterangan'         => "Auto-composed via LaporanComposer (jenis: {$composed['jenis']}, "
                . count($composed['sections']) . " sections){$qSummary}",
            'created_by'         => auth()->id(),
        ]);

        return [
            'pekerjaan_id' => $pekerjaan->id,
            'dokumen_id'   => $dokumen->id,
            'jenis'        => $jenis,
            'filename'     => $filename,
            'path'         => $relativePath,
            'size_kb'      => round($sizeBytes / 1024, 1),
            'download_url' => url(route('dokumen.download', $dokumen)),
            'mode'         => 'composer',
            'sections'     => $composed['sections'],
            'quality'      => $composed['quality'],
        ];
    }

    /**
     * Persist + return result dari template-based render.
     * Mirip ending dari generateLaporanDocx tapi pakai file yg sudah dirender Python.
     */
    private function finalizeFromTemplate(Pekerjaan $pekerjaan, string $jenis, array $tmpl): array
    {
        $absPath = $tmpl['output_path'];
        $filename = $tmpl['filename'];
        $relativePath = $tmpl['relative_path'];

        $tipe = $jenis === 'akhir' ? 'laporan_akhir' : 'lainnya';
        $namaDokumen = "Laporan " . ucfirst($jenis) . " — " . $pekerjaan->nama_pekerjaan;
        $existing = \App\Models\Dokumen::where('pekerjaan_id', $pekerjaan->id)
            ->where('nama_dokumen', 'LIKE', 'Laporan ' . ucfirst($jenis) . ' —%')
            ->latest('id')->first();
        $versi = $existing ? (((int) preg_replace('/\D/', '', (string) $existing->versi)) + 1) : 1;
        $dokumen = \App\Models\Dokumen::create([
            'pekerjaan_id'       => $pekerjaan->id,
            'tipe'               => $tipe,
            'nama_dokumen'       => $namaDokumen,
            'versi'              => (string) $versi,
            'file_path'          => $relativePath,
            'file_original_name' => $filename,
            'file_size'          => (int) $tmpl['size_bytes'],
            'keterangan'         => "Auto-generated via template '{$tmpl['template']}' (jenis: {$tmpl['jenis']}) by Karta AI",
            'created_by'         => auth()->id(),
        ]);

        return [
            'pekerjaan_id' => $pekerjaan->id,
            'dokumen_id'   => $dokumen->id,
            'jenis'        => $jenis,
            'filename'     => $filename,
            'path'         => $relativePath,
            'size_kb'      => round($tmpl['size_bytes'] / 1024, 1),
            'download_url' => url(route('dokumen.download', $dokumen)),
            'mode'         => 'template',
            'template'     => $tmpl['template'],
        ];
    }

    /** Generate kuitansi gaji — 1 kuitansi per personil dalam 1 PDF. */
    public function generateKuitansiGaji(int $pekerjaanId, array $opts = []): array
    {
        $pekerjaan = Pekerjaan::with(['perusahaan', 'personil.tenagaAhli'])->find($pekerjaanId);
        if (!$pekerjaan) throw new \RuntimeException("Pekerjaan ID {$pekerjaanId} tidak ditemukan.");

        $rows = [];
        foreach ($pekerjaan->personil as $p) {
            $rows[] = [
                'nama' => $p->tenagaAhli?->nama ?? '-',
                'jabatan' => $p->jabatan_kontrak,
                'honor' => (int) $p->nilai_honor_kontrak,
            ];
        }
        $total = array_sum(array_column($rows, 'honor'));

        $html = '<h2>Daftar Gaji Personil</h2>'
            . '<p>Pekerjaan: <strong>' . e($pekerjaan->nama_pekerjaan) . '</strong></p>'
            . '<p>Periode: ' . ($opts['periode'] ?? $pekerjaan->tanggal_mulai?->format('d M Y') . ' – ' . $pekerjaan->tanggal_akhir?->format('d M Y')) . '</p>'
            . '<table border="1" cellpadding="6" cellspacing="0" width="100%">'
            . '<thead><tr><th>No</th><th>Nama</th><th>Jabatan</th><th>Honor (Rp)</th></tr></thead><tbody>';
        foreach ($rows as $i => $r) {
            $html .= '<tr><td>' . ($i + 1) . '</td><td>' . e($r['nama']) . '</td><td>' . e($r['jabatan']) . '</td><td align="right">' . number_format($r['honor'], 0, ',', '.') . '</td></tr>';
        }
        $html .= '<tr><td colspan="3" align="right"><strong>TOTAL</strong></td><td align="right"><strong>Rp ' . number_format($total, 0, ',', '.') . '</strong></td></tr>';
        $html .= '</tbody></table>';

        $pdf = Pdf::loadHTML($html)->setPaper('A4');
        $slug = \Illuminate\Support\Str::slug($pekerjaan->nama_pekerjaan);
        $stamp = now()->format('YmdHis');
        $filename = "kuitansi_gaji_{$slug}_{$stamp}.pdf";
        $relativePath = "dokumen/generated/{$pekerjaanId}/{$filename}";

        Storage::disk('local')->put($relativePath, $pdf->output());
        return [
            'pekerjaan_id' => $pekerjaanId,
            'total_honor' => $total,
            'personil_count' => count($rows),
            'filename' => $filename,
            'path' => $relativePath,
            'download_url' => url("/dokumen/download/" . urlencode(base64_encode($relativePath))),
        ];
    }

    public function generateInvoiceAtk(int $pekerjaanId, array $opts = []): array
    {
        return $this->generateGenericLampiran($pekerjaanId, 'atk', ['ATK', 'Pelaporan', 'Flashdisk']);
    }

    public function generateInvoiceSewaAlat(int $pekerjaanId, array $opts = []): array
    {
        $override = $opts['harga_supplier'] ?? [];
        return $this->generateGenericLampiran($pekerjaanId, 'sewa_alat', ['Sondir', 'Bor Log', 'Sewa'], $override);
    }

    public function generateSuratPermohonanPembayaran(int $pekerjaanId, array $opts = []): array
    {
        $pekerjaan = Pekerjaan::with('perusahaan')->find($pekerjaanId);
        if (!$pekerjaan) throw new \RuntimeException("Pekerjaan ID {$pekerjaanId} tidak ditemukan.");

        $termin = !empty($opts['termin_id'])
            ? \App\Models\TerminPembayaran::find($opts['termin_id'])
            : \App\Models\TerminPembayaran::where('pekerjaan_id', $pekerjaanId)->latest()->first();

        $nilai = $termin?->nilai_termin ?? $pekerjaan->nilai_kontrak ?? 0;

        $html = '<h2 align="center">SURAT PERMOHONAN PEMBAYARAN</h2>'
            . '<p>' . ($pekerjaan->perusahaan?->alamat ?? '') . '</p>'
            . '<hr><p>Kepada Yth.<br>Kuasa Pengguna Anggaran / Pejabat Pembuat Komitmen<br>Dinas Pekerjaan Umum dan Tata Ruang<br>Kabupaten Bandung</p>'
            . '<p>Perihal: <strong>Permohonan Pembayaran ' . e($termin?->nama_termin ?? 'Termin') . '</strong></p>'
            . '<p>Berdasarkan Surat Perjanjian Kontrak Nomor: ' . e($pekerjaan->no_spk ?? '-') . ' tanggal ' . ($pekerjaan->tanggal_spk?->format('d M Y') ?? '-') . ', bersama ini kami mengajukan tagihan sebesar:</p>'
            . '<p><strong>Rp ' . number_format((float) $nilai, 0, ',', '.') . '</strong></p>'
            . '<p>Untuk pekerjaan: <strong>' . e($pekerjaan->nama_pekerjaan) . '</strong></p>'
            . '<br><br><p align="right">Hormat kami,<br>' . e($pekerjaan->perusahaan?->nama ?? '-') . '<br><br><br><strong>' . e($pekerjaan->perusahaan?->pic_nama ?? 'Direktur') . '</strong><br>Direktur</p>';

        $pdf = Pdf::loadHTML($html)->setPaper('A4');
        $slug = \Illuminate\Support\Str::slug($pekerjaan->nama_pekerjaan);
        $stamp = now()->format('YmdHis');
        $filename = "surat_permohonan_{$slug}_{$stamp}.pdf";
        $relativePath = "dokumen/generated/{$pekerjaanId}/{$filename}";

        Storage::disk('local')->put($relativePath, $pdf->output());
        return [
            'pekerjaan_id' => $pekerjaanId,
            'termin' => $termin?->nama_termin,
            'nilai' => $nilai,
            'filename' => $filename,
            'download_url' => url("/dokumen/download/" . urlencode(base64_encode($relativePath))),
        ];
    }

    public function generateBast(int $pekerjaanId, array $opts = []): array
    {
        $pekerjaan = Pekerjaan::with('perusahaan')->find($pekerjaanId);
        if (!$pekerjaan) throw new \RuntimeException("Pekerjaan ID {$pekerjaanId} tidak ditemukan.");

        $html = '<h2 align="center">BERITA ACARA SERAH TERIMA PEKERJAAN</h2>'
            . '<p>No: BAST/' . str_pad((string) $pekerjaanId, 4, '0', STR_PAD_LEFT) . '/' . date('Y') . '</p>'
            . '<p>Pada hari ini, ' . now()->translatedFormat('l d F Y') . ', kami:</p>'
            . '<p><strong>Pihak Pertama (Penyedia)</strong>: ' . e($pekerjaan->perusahaan?->nama ?? '-') . '<br>'
            . 'Direktur: ' . e($pekerjaan->perusahaan?->pic_nama ?? '-') . '</p>'
            . '<p><strong>Pihak Kedua (PPK)</strong>: Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung</p>'
            . '<p>Menyatakan bahwa pekerjaan:</p>'
            . '<ul>'
            . '<li>Nama: ' . e($pekerjaan->nama_pekerjaan) . '</li>'
            . '<li>No. Kontrak: ' . e($pekerjaan->no_spk ?? '-') . '</li>'
            . '<li>Nilai: Rp ' . number_format((float) $pekerjaan->nilai_kontrak, 0, ',', '.') . '</li>'
            . '<li>Periode: ' . ($pekerjaan->tanggal_mulai?->format('d M Y') ?? '-') . ' s/d ' . ($pekerjaan->tanggal_akhir?->format('d M Y') ?? '-') . '</li>'
            . '</ul>'
            . '<p>Telah diselesaikan 100% dan diserahterimakan kepada Pihak Kedua.</p>'
            . '<br><br><table width="100%"><tr><td align="center">Pihak Pertama,<br><br><br><br><strong>' . e($pekerjaan->perusahaan?->pic_nama ?? '(...)') . '</strong><br>Direktur</td>'
            . '<td align="center">Pihak Kedua,<br><br><br><br><strong>(...)</strong><br>PPK</td></tr></table>';

        $pdf = Pdf::loadHTML($html)->setPaper('A4');
        $slug = \Illuminate\Support\Str::slug($pekerjaan->nama_pekerjaan);
        $stamp = now()->format('YmdHis');
        $filename = "bast_{$slug}_{$stamp}.pdf";
        $relativePath = "dokumen/generated/{$pekerjaanId}/{$filename}";

        Storage::disk('local')->put($relativePath, $pdf->output());
        return [
            'pekerjaan_id' => $pekerjaanId,
            'filename' => $filename,
            'download_url' => url("/dokumen/download/" . urlencode(base64_encode($relativePath))),
        ];
    }

    private function generateGenericLampiran(int $pekerjaanId, string $tipe, array $keywords, array $hargaOverride = []): array
    {
        $pekerjaan = Pekerjaan::with('rencanaPengadaan')->find($pekerjaanId);
        if (!$pekerjaan) throw new \RuntimeException("Pekerjaan ID {$pekerjaanId} tidak ditemukan.");

        $items = $pekerjaan->rencanaPengadaan->filter(function ($r) use ($keywords) {
            foreach ($keywords as $kw) {
                if (stripos($r->nama_item, $kw) !== false) return true;
            }
            return false;
        })->values();

        $total = 0;
        $html = '<h3>Invoice Lampiran — ' . ucfirst(str_replace('_', ' ', $tipe)) . '</h3>'
            . '<p>Pekerjaan: <strong>' . e($pekerjaan->nama_pekerjaan) . '</strong></p>'
            . '<table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr><th>No</th><th>Item</th><th>Vol</th><th>Satuan</th><th>Harga</th><th>Jumlah</th></tr></thead><tbody>';
        foreach ($items as $i => $item) {
            $harga = isset($hargaOverride[$item->nama_item]) ? (float) $hargaOverride[$item->nama_item] : (float) $item->harga_satuan_rencana;
            $jumlah = $harga * (float) $item->volume_rencana;
            $total += $jumlah;
            $html .= '<tr><td>' . ($i + 1) . '</td><td>' . e($item->nama_item) . '</td><td align="right">' . $item->volume_rencana . '</td><td>' . e($item->satuan) . '</td><td align="right">' . number_format($harga, 0, ',', '.') . '</td><td align="right">' . number_format($jumlah, 0, ',', '.') . '</td></tr>';
        }
        $html .= '<tr><td colspan="5" align="right"><strong>TOTAL</strong></td><td align="right"><strong>Rp ' . number_format($total, 0, ',', '.') . '</strong></td></tr>';
        $html .= '</tbody></table>';

        $pdf = Pdf::loadHTML($html)->setPaper('A4');
        $slug = \Illuminate\Support\Str::slug($pekerjaan->nama_pekerjaan);
        $stamp = now()->format('YmdHis');
        $filename = "invoice_{$tipe}_{$slug}_{$stamp}.pdf";
        $relativePath = "dokumen/generated/{$pekerjaanId}/{$filename}";

        Storage::disk('local')->put($relativePath, $pdf->output());
        return [
            'pekerjaan_id' => $pekerjaanId,
            'tipe' => $tipe,
            'total' => $total,
            'items_count' => count($items),
            'filename' => $filename,
            'download_url' => url("/dokumen/download/" . urlencode(base64_encode($relativePath))),
        ];
    }

    public function generateInvoice(int $pekerjaanId, ?int $prestasiPersen = null, ?int $noInvoice = null): array
    {
        $pekerjaan = Pekerjaan::with(['perusahaan', 'personil.tenagaAhli'])->find($pekerjaanId);
        if (!$pekerjaan) {
            throw new \RuntimeException("Pekerjaan ID $pekerjaanId tidak ditemukan.");
        }

        // Derive line items dari personil + rencana pengadaan
        $items = $this->deriveLineItems($pekerjaan);

        if (empty($items)) {
            throw new \RuntimeException(
                "Pekerjaan '{$pekerjaan->nama_pekerjaan}' belum punya item RAB. " .
                "Upload Lampiran Negosiasi (RAB) dulu via tool parse_rab_pdf, " .
                "atau tambah personil + rencana pengadaan."
            );
        }

        $subtotal = array_sum(array_column($items, 'jumlah_harga'));
        $ppnPersen = 11;

        $context = [
            'pekerjaan' => [
                'nama'              => $pekerjaan->nama_pekerjaan,
                'vendor'            => $pekerjaan->perusahaan?->nama ?? '(Vendor)',
                'vendor_alamat'     => $pekerjaan->perusahaan?->alamat ?? '',
                'vendor_direktur'   => $pekerjaan->perusahaan?->pic_nama ?? 'Direktur',
                'lokasi'            => $pekerjaan->lokasi ?? 'Kabupaten Bandung',
                'no_spk'            => $pekerjaan->no_spk,
                'tanggal_spk'       => $pekerjaan->tanggal_spk?->format('d F Y'),
                'tanggal_mulai'     => $pekerjaan->tanggal_mulai?->format('d F Y'),
                'tanggal_akhir'     => $pekerjaan->tanggal_akhir?->format('d F Y'),
                'no_invoice'        => $noInvoice ?? str_pad((string) $pekerjaanId, 3, '0', STR_PAD_LEFT),
                'ppk_dinas'         => 'Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung',
                'ppk_nama'          => '(.....)',
                'ppk_nip'           => 'NIP. ...',
            ],
            'items'           => $items,
            'ppn_persen'      => $ppnPersen,
            'prestasi_persen' => $prestasiPersen ?? 100,
            'tanggal_terbit'  => now()->translatedFormat('d F Y'),
            'terbilang'       => $this->terbilang($subtotal * (1 + $ppnPersen / 100)),
        ];

        $html = view('dokumen.invoice', $context)->render();
        $pdf = Pdf::loadHTML($html)->setPaper('A4');

        $slug = Str::slug($pekerjaan->nama_pekerjaan ?? 'invoice');
        $stamp = now()->format('YmdHis');
        $filename = "invoice_{$slug}_{$stamp}.pdf";
        $relativePath = "dokumen/generated/{$pekerjaanId}/{$filename}";

        Storage::disk('local')->put($relativePath, $pdf->output());

        return [
            'pekerjaan_id' => $pekerjaanId,
            'pekerjaan'    => $pekerjaan->nama_pekerjaan,
            'filename'     => $filename,
            'path'         => $relativePath,
            'size_kb'      => round(strlen($pdf->output()) / 1024, 1),
            'subtotal'     => $subtotal,
            'ppn'          => round($subtotal * $ppnPersen / 100),
            'total'        => round($subtotal * (1 + $ppnPersen / 100)),
            'items_count'  => count($items),
            'download_url' => url("/dokumen/download/" . urlencode(base64_encode($relativePath))),
        ];
    }

    /**
     * Derive line items dari personil (pakai RAB rate kalau ada) + rencana pengadaan.
     * Saat ini sederhana: per personil pakai jabatan_kontrak + nilai_honor_kontrak.
     */
    private function deriveLineItems(Pekerjaan $pekerjaan): array
    {
        $items = [];

        // Personil → biaya langsung personil
        foreach ($pekerjaan->personil as $p) {
            if (!$p->nilai_honor_kontrak) continue;
            $items[] = [
                'kategori'      => 'personil',
                'uraian'        => $p->jabatan_kontrak,
                'volume'        => 1,
                'satuan'        => 'Org/Bln',
                'harga_satuan'  => (int) $p->nilai_honor_kontrak,
                'jumlah_harga'  => (int) $p->nilai_honor_kontrak,
            ];
        }

        // Rencana pengadaan → biaya non personil
        if ($pekerjaan->rencanaPengadaan ?? null) {
            foreach ($pekerjaan->rencanaPengadaan as $rp) {
                if (!$rp->harga_satuan || !$rp->volume) continue;
                $items[] = [
                    'kategori'      => 'non_personil',
                    'uraian'        => $rp->nama_item,
                    'volume'        => (float) $rp->volume,
                    'satuan'        => $rp->satuan ?? 'Ls',
                    'harga_satuan'  => (int) $rp->harga_satuan,
                    'jumlah_harga'  => (int) ($rp->harga_satuan * $rp->volume),
                ];
            }
        }

        return $items;
    }

    /**
     * Convert angka ke terbilang Indonesia (sederhana, mendukung sampai triliun).
     */
    private function terbilang(float $angka): string
    {
        $angka = (int) round($angka);
        if ($angka === 0) return 'nol rupiah';

        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        $convert = function (int $n) use (&$convert, $satuan): string {
            if ($n < 12) return $satuan[$n];
            if ($n < 20) return $convert($n - 10) . ' belas';
            if ($n < 100) return $convert(intdiv($n, 10)) . ' puluh' . ($n % 10 ? ' ' . $convert($n % 10) : '');
            if ($n < 200) return 'seratus' . ($n - 100 ? ' ' . $convert($n - 100) : '');
            if ($n < 1000) return $convert(intdiv($n, 100)) . ' ratus' . ($n % 100 ? ' ' . $convert($n % 100) : '');
            if ($n < 2000) return 'seribu' . ($n - 1000 ? ' ' . $convert($n - 1000) : '');
            if ($n < 1_000_000) return $convert(intdiv($n, 1000)) . ' ribu' . ($n % 1000 ? ' ' . $convert($n % 1000) : '');
            if ($n < 1_000_000_000) return $convert(intdiv($n, 1_000_000)) . ' juta' . ($n % 1_000_000 ? ' ' . $convert($n % 1_000_000) : '');
            if ($n < 1_000_000_000_000) return $convert(intdiv($n, 1_000_000_000)) . ' miliar' . ($n % 1_000_000_000 ? ' ' . $convert($n % 1_000_000_000) : '');
            return $convert(intdiv($n, 1_000_000_000_000)) . ' triliun' . ($n % 1_000_000_000_000 ? ' ' . $convert($n % 1_000_000_000_000) : '');
        };

        return ucfirst($convert($angka)) . ' rupiah';
    }
}
