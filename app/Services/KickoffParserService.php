<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class KickoffParserService
{
    public function parse(string $filePath): array
    {
        $result = [
            'no_spk' => null,
            'tanggal_spk' => null,
            'no_spmk' => null,
            'tanggal_spmk' => null,
            'nama_pekerjaan' => null,
            'nilai_pagu' => null,
            'nilai_kontrak' => null,
            'perusahaan_nama' => null,
            'tanggal_mulai' => null,
            'tanggal_akhir' => null,
            'hari_kerja' => null,
        ];

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
        } catch (\Exception $e) {
            return $result;
        }

        if (preg_match('/(?:No(?:mor)?\.?\s*SPK\s*:?\s*)([^\n\r,;]{5,80})/i', $text, $m)) {
            $result['no_spk'] = trim($m[1]);
        } elseif (preg_match('/(\d+[\/.]\d+[\/.][A-Z]+[\/.][^\n\r,]{3,60})/i', $text, $m)) {
            $result['no_spk'] = trim($m[1]);
        }

        if (preg_match('/(?:No(?:mor)?\.?\s*SPMK\s*:?\s*)([^\n\r,;]{5,80})/i', $text, $m)) {
            $result['no_spmk'] = trim($m[1]);
        }

        $months = [
            'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
            'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
            'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12',
        ];

        $datePattern = '/(\d{1,2})\s+(' . implode('|', array_keys($months)) . ')\s+(\d{4})/i';
        $dates = [];
        if (preg_match_all($datePattern, $text, $dm, PREG_SET_ORDER)) {
            foreach ($dm as $d) {
                $m2 = strtolower($d[2]);
                $dates[] = $d[3] . '-' . $months[$m2] . '-' . str_pad($d[1], 2, '0', STR_PAD_LEFT);
            }
        }
        if (count($dates) >= 1) $result['tanggal_spk'] = $dates[0];
        if (count($dates) >= 2) $result['tanggal_mulai'] = $dates[1];
        if (count($dates) >= 3) $result['tanggal_akhir'] = $dates[2];

        if (preg_match('/(?:nilai\s+(?:pagu|kontrak|hps|pekerjaan)[^0-9]*)([\d.,]+)/i', $text, $m)) {
            $result['nilai_pagu'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }
        if (preg_match('/(?:Rp\.?\s*)([\d.,]+)/i', $text, $m)) {
            $val = (float) str_replace(['.', ','], ['', '.'], $m[1]);
            if ($val > 1000000) {
                $result['nilai_kontrak'] = $val;
                if (!$result['nilai_pagu']) $result['nilai_pagu'] = $val;
            }
        }

        if (preg_match('/\b((?:PT|CV)\.?\s+[A-Z][A-Za-z\s,\.]+?)(?:\n|\r|,|;)/m', $text, $m)) {
            $result['perusahaan_nama'] = trim($m[1]);
        }

        if (preg_match('/(\d+)\s*(?:hari\s+kerja|hari)/i', $text, $m)) {
            $result['hari_kerja'] = (int) $m[1];
        }

        return $result;
    }
}
