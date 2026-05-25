<?php
$signatureBlock = 'an peraturan yang berlaku. E. PERSYARATAN TEKNIS LAINNYA Selain kriteria umum di atas, untuk pekerjaan Perencanaan berlaku pula ketentuan- ketentuan seperti standar, pedoman, dan peraturan yang berlaku, antara lain: 1. Peraturan Pemerintah Nomor 29 Tahun 2000 tentang Penyelenggaraan Jasa Konstruksi, Ketentuan yang diberlakukan untuk pekerjaan satuan kerjayang bersangkutan, yaitu Surat Perjanjian Pekerjaan Pelaksanaan beserta kelengkapannya, dan ketentuan- ketentuan sebagai dasar perjanjiannya. 2. Yang termuat dalam Peraturan Menteri Pekerjaan Umum Nomor : 14/PRT/M/2013 Tentang Perubahan Peraturan Menteri Pekerjaan Umum Nomor 07/PRT/M/2011 tentang Pedoman Teknis Pembangunan Bangunan Gedung Negara. 3. Peraturan Pembangunan Pemerintah Daerah Setempat. 4. Standar dan Pedoman Teknis yang berlaku di bidang penyelenggaraan bangunan gedung. Kerangka Acuan Kerja (KAK)  8 12. PELAPORAN Laporan Konsultan Perencana diminta: 1) Laporan Kajian Pendahuluan 2) Laporan Kajian Akhir, meliputi : • Laporan Akhir • Gambar teknis • Laporan Hasil Pengujian Bor Log • Laporan Hasil Pengujian Sondir 13. PENUTUP A. Setelah Kerangka Acuan Kerja ini diterima, konsultan hendaknya memeriksa semua bahan masukan yang diterima dan mencari bahan masukan lain yang dibutuhkan. B. Berdasarkan bahan-bahan tersebut, maka selanjutnya konsultan agar segera menyusun program kerja untuk dibahas dengan Pemberi Tugas Soreang, 19 Desember 2025 Pejabat Pembuat Komitmen Widya Astuti, S.T., MPSDA. NIP. 19790405 201101 2 002';

$months = ['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
$pattern = '/(\d{1,2})\s+(' . implode('|', $months) . ')\s+(\d{4})/i';
echo "Pattern: $pattern\n";
if (preg_match($pattern, $signatureBlock, $m)) {
    echo "DATE MATCH: day=" . $m[1] . " month=" . $m[2] . " year=" . $m[3] . "\n";
} else {
    echo "DATE NO MATCH\n";
}

if (preg_match('/NIP\.?\s*:?\s*([\d\s]{16,30})/i', $signatureBlock, $m)) {
    echo "NIP MATCH: '" . trim($m[1]) . "'\n";
} else {
    echo "NIP NO MATCH\n";
}

$namePattern = '/([A-Z][A-Za-z\s,.\']{5,60}(?:S\.?T\.?|S\.?E\.?|M\.?T\.?|M\.?M\.?|M\.?Sc\.?|MPSDA|M\.?PSDA|S\.?Sos\.?|S\.?Pd\.?|M\.?Pd\.?|S\.?H\.?|M\.?H\.?|M\.?Si\.?|S\.?I\.?P\.?|Ph\.?D\.?|Drs\.?|Dra\.?|Ir\.?|Dr\.?)\.?)\s*(?:NIP|$)/i';
if (preg_match($namePattern, $signatureBlock, $m)) {
    echo "NAME MATCH: '" . trim(rtrim($m[1], ',')) . "'\n";
} else {
    echo "NAME NO MATCH\n";
}
