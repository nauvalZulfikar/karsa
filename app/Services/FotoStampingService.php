<?php

namespace App\Services;

use App\Models\LaporanHarian;

class FotoStampingService
{
    public function stamp(string $sourcePath, LaporanHarian $laporan): string
    {
        $fullSource = storage_path('app/' . $sourcePath);

        if (!file_exists($fullSource)) {
            return $sourcePath;
        }

        $info = getimagesize($fullSource);
        if (!$info) return $sourcePath;

        $image = match($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($fullSource),
            IMAGETYPE_PNG  => imagecreatefrompng($fullSource),
            IMAGETYPE_WEBP => imagecreatefromwebp($fullSource),
            default        => null,
        };

        if (!$image) return $sourcePath;

        $width  = imagesx($image);
        $height = imagesy($image);

        $bannerH = max(120, (int) ($height * 0.15));
        $bannerY = $height - $bannerH;

        $black = imagecolorallocatealpha($image, 0, 0, 0, 50);
        imagefilledrectangle($image, 0, $bannerY, $width, $height, $black);

        $white  = imagecolorallocate($image, 255, 255, 255);
        $yellow = imagecolorallocate($image, 255, 220, 50);

        $jenis = $laporan->jenis === 'masuk' ? 'LAPORAN MASUK' : 'LAPORAN PULANG';
        $lines = [
            $jenis . ' — ' . $laporan->submitted_at?->format('d/m/Y H:i') . ' WIB',
            $laporan->pekerjaan->nama_pekerjaan ?? '-',
            $laporan->perusahaan->nama ?? '-',
            $laporan->user->name ?? '-',
            $laporan->latitude && $laporan->longitude
                ? 'GPS: ' . number_format($laporan->latitude, 5) . ', ' . number_format($laporan->longitude, 5)
                : 'GPS: tidak tersedia',
        ];

        $lineH = (int) ($bannerH / (count($lines) + 1));
        $x = 10;

        foreach ($lines as $i => $line) {
            $y = $bannerY + ($i + 1) * $lineH - 8;
            $color = $i === 0 ? $yellow : $white;
            imagestring($image, 4, $x, $y, $line, $color);
        }

        $destDir     = 'foto_laporan/' . $laporan->tanggal_laporan->format('Y/m') . '/' . $laporan->pekerjaan_id;
        $destDirFull = storage_path('app/' . $destDir);

        if (!is_dir($destDirFull)) {
            mkdir($destDirFull, 0755, true);
        }

        $filename = $laporan->perusahaan_id . '_' . $laporan->user_id . '_' . $laporan->jenis . '_' . time() . '.jpg';
        $destPath = $destDir . '/' . $filename;
        $destFull = storage_path('app/' . $destPath);

        imagejpeg($image, $destFull, 90);
        imagedestroy($image);

        return $destPath;
    }
}
