<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    protected $signature   = 'pwa:generate-icons';
    protected $description = 'Generate PWA PNG icons using GD library';

    public function handle(): int
    {
        foreach ([192, 512] as $size) {
            $img = imagecreatetruecolor($size, $size);

            // Amber background (#f59e0b)
            $bg = imagecolorallocate($img, 245, 158, 11);
            imagefill($img, 0, 0, $bg);

            // Rounded feel — draw circle overlay
            $white = imagecolorallocate($img, 255, 255, 255);
            $pad   = (int) ($size * 0.12);

            // Draw "D" letter centered
            $fontFile = null;
            $fontSize = (int) ($size * 0.45);
            $cx = (int) ($size / 2);
            $cy = (int) ($size / 2);

            // Simple block letter using rectangles if no font
            $thick = (int) ($size * 0.08);
            $lw    = (int) ($size * 0.25);
            $lh    = (int) ($size * 0.6);
            $lx    = $cx - (int) ($size * 0.18);
            $ly    = $cy - (int) ($lh / 2);

            // Vertical bar of "D"
            imagefilledrectangle($img, $lx, $ly, $lx + $thick, $ly + $lh, $white);
            // Top bar
            imagefilledrectangle($img, $lx, $ly, $lx + (int)($lw * 0.8), $ly + $thick, $white);
            // Bottom bar
            imagefilledrectangle($img, $lx, $ly + $lh - $thick, $lx + (int)($lw * 0.8), $ly + $lh, $white);
            // Curved right — approximate arc with filled ellipse segment
            $arcX = $lx + (int)($lw * 0.8) - (int)($size * 0.04);
            $arcY = $cy;
            imagefilledellipse($img, $arcX, $arcY, (int)($lw * 1.1), $lh - $thick * 2, $white);
            // Re-fill amber inside the D to hollow it
            $innerX = $lx + $thick;
            $innerY = $ly + $thick;
            $innerW = (int)($lw * 0.7);
            $innerH = $lh - $thick * 2;
            imagefilledellipse($img, $arcX - $thick, $arcY, $innerW, $innerH, $bg);
            imagefilledrectangle($img, $innerX, $innerY, $innerX + $thick, $innerY + $innerH, $bg);

            $path = public_path("pwa-icon-{$size}.png");
            imagepng($img, $path, 6);
            imagedestroy($img);

            $this->info("Generated: {$path}");
        }

        return Command::SUCCESS;
    }
}
