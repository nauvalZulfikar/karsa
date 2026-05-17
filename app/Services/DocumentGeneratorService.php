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
