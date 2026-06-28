<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Rekomendasi grup: deteksi file upload yang sinyal namanya menunjuk ke
 * SATU paket pekerjaan yang sama (mis. KAK + SPK + RAB satu proyek), supaya
 * bisa diorganise sekaligus. Murni heuristik nama file (deterministik).
 *
 * Signature paket = JENIS PEKERJAAN + LOKASI/IDENTITAS.
 * Dua file dengan signature sama dianggap satu proyek.
 */
class DocumentGroupingService
{
    /** Jenis pekerjaan → kata kunci. Urutan = prioritas (spesifik dulu). */
    private const WORK_TYPES = [
        'SPALD'                => ['spald'],
        'Drainase Perkotaan'   => ['drainase perkotaan', 'saluran drainase perkotaan'],
        'Drainase Lingkungan'  => ['drainase lingkungan'],
        'Outline Plan'         => ['outline plan', 'outplan'],
        'Jembatan'             => ['jembatan'],
        'Trotoar'              => ['trotoar'],
        'Tanggul Sungai'       => ['tanggul'],
        'Normalisasi Sungai'   => ['normalisasi', 'restorasi'],
        'Rehab Bendung'        => ['bendung'],
        'Rehab Irigasi'        => ['irigasi'],
        'Air Bersih'           => ['air bersih'],
        'Kajian Geoteknik'     => ['geoteknik'],
        'Kajian Topografi'     => ['topografi'],
        'Pengawasan'           => ['pengawasan'],
        'Interior'             => ['interior', 'int '],
        'Pagar'                => ['pagar', 'pager'],
        'Renovasi'             => ['renov'],
        'Kantor Kecamatan'     => ['kantor kec', 'kecamatan'],
        'Gedung'               => ['gedung', 'gd '],
        'Jalan'                => ['ded jalan', 'jalan kabupaten', 'teknik jalan', 'pertek', 'p fajr ded', 'ded '],
    ];

    /** Token lokasi / identitas paket (kota, sub-DAS, gedung). */
    private const PLACES = [
        'cileunyi', 'ciparay', 'ciwidey', 'banjaran', 'baleendah', 'margahayu', 'soreang',
        'dayeuhkolot', 'rancaekek', 'cisangkuy', 'citarik', 'cirasea',
        'arjasari', 'pacira', 'knpi', 'diskominfo', 'dispora', 'bapperida', 'intelkam',
        'ipari', 'pgmi', 'graha', 'slrt', 'masjid', 'bupati', 'upakarti', 'pendopo', 'polresta',
    ];

    /** Jenis yang pakai "wilayah N" sebagai identitas (bukan token kota). */
    private const WILAYAH_BASED = ['SPALD', 'Drainase Perkotaan', 'Drainase Lingkungan'];

    /**
     * @param  Collection  $uploads  koleksi ChatUpload
     * @return array<int,array{key:string,label:string,files:Collection}>  grup (≥2 file), terbesar dulu
     */
    public function cluster(Collection $uploads): array
    {
        $groups = [];

        foreach ($uploads as $upload) {
            $sig = $this->signature((string) $upload->original_name);
            if ($sig === null) {
                continue; // tak ada sinyal jelas → biarkan diorganise satuan
            }
            $groups[$sig['key']]['label'] = $sig['label'];
            $groups[$sig['key']]['files'][] = $upload;
        }

        $out = [];
        foreach ($groups as $key => $g) {
            if (count($g['files']) < 2) {
                continue; // rekomendasi hanya untuk yg punya >1 file
            }
            $out[] = [
                'key'   => $key,
                'label' => $g['label'],
                'files' => collect($g['files']),
            ];
        }

        usort($out, fn ($a, $b) => $b['files']->count() <=> $a['files']->count());

        return $out;
    }

    /** Signature 1 file, atau null bila tak terdeteksi. */
    public function signature(string $name): ?array
    {
        $n = ' ' . Str::lower(preg_replace('/[^a-z0-9]+/i', ' ', $name)) . ' ';
        $n = preg_replace('/\s+/', ' ', $n);

        $work = $this->matchWorkType($n);
        if ($work === null) {
            return null;
        }

        $place = $this->matchPlace($n, $work);
        if ($place === null) {
            return null;
        }

        $key = Str::slug($work . '-' . $place['key']);

        return [
            'key'   => $key,
            'label' => $work . ' — ' . $place['label'],
        ];
    }

    private function matchWorkType(string $n): ?string
    {
        foreach (self::WORK_TYPES as $canonical => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($n, ' ' . $kw) || str_contains($n, $kw . ' ')) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    private function matchPlace(string $n, string $work): ?array
    {
        if (in_array($work, self::WILAYAH_BASED, true)
            && preg_match('/wilayah\s*(\d+)/', $n, $m)) {
            return ['key' => 'wil' . $m[1], 'label' => 'Wilayah ' . $m[1]];
        }

        foreach (self::PLACES as $place) {
            if (str_contains($n, ' ' . $place . ' ')) {
                return ['key' => $place, 'label' => Str::title($place)];
            }
        }

        // fallback wilayah N untuk jenis non-wilayah yg tetap pakai nomor
        if (preg_match('/wilayah\s*(\d+)/', $n, $m)) {
            return ['key' => 'wil' . $m[1], 'label' => 'Wilayah ' . $m[1]];
        }

        return null;
    }
}
