<?php

namespace App\Services;

use App\Models\Pekerjaan;

/**
 * Validate composed laporan content against Playbook §4 Style Checklist
 * + §10 Anti-patterns.
 *
 * Returns: {passed: bool, score: 0-10, issues: array}
 *
 * Dipakai oleh LaporanComposerService sebelum return composed content.
 */
class QualityGateService
{
    /** Forbidden project names yg sering bocor dari training data referensi. */
    private const FORBIDDEN_LEAK_NAMES = [
        'Sekolah Rakyat Ciwidey', 'Sekolah Rakyat (SR) Ciwidey', 'SR Ciwidey',
        'Soreang', 'DED Jalan Kabupaten Wilayah Soreang',
        'Itergo', 'PT. Itergo', 'PT. Adhi Citrabhumi', 'PT. Purna Wahana',
        'Lebakmuncang', 'Kemensos',
    ];

    /** First-person markers yg gak boleh muncul (harus passive). */
    private const FIRST_PERSON_FORBIDDEN = ['saya', 'anda', 'kalian', 'gua', 'lu', 'lo'];

    /**
     * Run all gates. Return summary.
     *
     * @param string $content        Composed section content
     * @param string $sectionName    e.g. 'kata_pengantar', 'latar_belakang'
     * @param Pekerjaan $pekerjaan   For project-context checks (allow vendor name etc)
     */
    public function check(string $content, string $sectionName, Pekerjaan $pekerjaan): array
    {
        $issues = [];
        $passed = 0;
        $total  = 0;

        // Gate 1: No first-person
        $total++;
        $found = $this->findAny($content, self::FIRST_PERSON_FORBIDDEN, caseInsensitive: true);
        if ($found) {
            $issues[] = "G1: first-person markers found: " . implode(',', $found);
        } else {
            $passed++;
        }

        // Gate 2: No leak nama proyek lain
        $total++;
        // Allow project's own vendor + nama_pekerjaan (case-insensitive contain check)
        $vendor = strtolower($pekerjaan->perusahaan?->nama ?? '');
        $nama   = strtolower($pekerjaan->nama_pekerjaan ?? '');
        $forbid = array_filter(self::FORBIDDEN_LEAK_NAMES, function ($n) use ($vendor, $nama) {
            $lower = strtolower($n);
            return !str_contains($vendor, $lower) && !str_contains($nama, $lower);
        });
        $leaks = $this->findAny($content, $forbid, caseInsensitive: true);
        if ($leaks) {
            $issues[] = "G2: leak nama proyek lain: " . implode(',', $leaks);
        } else {
            $passed++;
        }

        // Gate 3: No placeholder text
        $total++;
        if (preg_match('/\{[a-z_]+\}|\[TBD\]|\[PENDING\]|\[\.\.\.\]/i', $content)) {
            $issues[] = "G3: placeholder text masih ada (look for {xxx} or [TBD])";
        } else {
            $passed++;
        }

        // Gate 4 & 5 only apply to PROSE sections (not bulleted-list sections like
        // ruang_lingkup, maksud_tujuan, kesimpulan, rekomendasi where structure is list).
        $isList = $this->isMostlyList($content);

        // Gate 4: Passive voice ratio (skip for list-heavy sections)
        $total++;
        if ($isList) {
            $passed++;
        } else {
            $passiveRatio = $this->passiveRatio($content);
            if ($passiveRatio < 0.25) {
                $issues[] = sprintf("G4: passive voice ratio %.0f%% < 25%% (target >60%% formal Indo)", $passiveRatio * 100);
            } else {
                $passed++;
            }
        }

        // Gate 5: Sentence length avg (skip for list)
        $total++;
        if ($isList) {
            $passed++;
        } else {
            $avgLen = $this->avgSentenceLength($content);
            if ($avgLen < 12 || $avgLen > 70) {
                $issues[] = sprintf("G5: avg sentence length %.0f words (target 20-50)", $avgLen);
            } else {
                $passed++;
            }
        }

        // Gate 6: Per-section specific checks
        $total++;
        $sectionIssues = $this->sectionSpecificChecks($content, $sectionName);
        if (!empty($sectionIssues)) {
            $issues = array_merge($issues, $sectionIssues);
        } else {
            $passed++;
        }

        $score = $total > 0 ? round(($passed / $total) * 10, 1) : 0;
        return [
            'passed'  => count($issues) === 0,
            'score'   => $score,
            'gates_passed' => $passed,
            'gates_total'  => $total,
            'issues'  => $issues,
            'metrics' => [
                'passive_ratio'      => round($passiveRatio ?? $this->passiveRatio($content), 2),
                'avg_sentence_len'   => round($avgLen ?? $this->avgSentenceLength($content), 1),
                'word_count'         => str_word_count(strip_tags($content)),
                'is_list'            => $isList,
            ],
        ];
    }

    private function sectionSpecificChecks(string $content, string $sectionName): array
    {
        $issues = [];
        switch ($sectionName) {
            case 'kata_pengantar':
                if (!preg_match('/Puji.*syukur.*Tuhan Yang Maha Esa/i', $content)) {
                    $issues[] = "KP: opening religious greeting missing";
                }
                if (!preg_match('/Tim Penyusun/i', $content)) {
                    $issues[] = "KP: signature block 'Tim Penyusun' missing";
                }
                if (!preg_match('/Direktur/i', $content)) {
                    $issues[] = "KP: signature 'Direktur' missing";
                }
                break;

            case 'kesimpulan':
                // Should have numbers (SF values, percentages, volumes)
                if (!preg_match('/\d+([,.]\d+)?\s*(%|m³|m3|kg|persen|hektar|SF|sf)/i', $content)
                    && !preg_match('/\d+([,.]\d+)?\s*≥|\d+([,.]\d+)?\s*>/i', $content)) {
                    $issues[] = "KS: tidak ada angka konkret (SF/volume/%) — wajib di kesimpulan";
                }
                break;

            case 'latar_belakang':
                $paraCount = count(array_filter(explode("\n", $content), fn($p) => strlen(trim($p)) > 50));
                if ($paraCount < 3) {
                    $issues[] = "LB: hanya {$paraCount} paragraf — target 4-6";
                }
                break;

            case 'maksud_tujuan':
                // Should contain bulleted list of tujuan
                if (substr_count($content, "\n-") < 3 && substr_count($content, "\n•") < 3 && substr_count($content, "\n1.") < 3) {
                    $issues[] = "MT: tujuan list pendek (< 3 items)";
                }
                break;
        }
        return $issues;
    }

    /** Find any of $needles in $haystack. Returns matched needles. */
    private function findAny(string $haystack, array $needles, bool $caseInsensitive = false): array
    {
        $found = [];
        $hs = $caseInsensitive ? strtolower($haystack) : $haystack;
        foreach ($needles as $n) {
            $needle = $caseInsensitive ? strtolower($n) : $n;
            // word boundary match (rough)
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $hs)) {
                $found[] = $n;
            }
        }
        return $found;
    }

    /**
     * Compute passive-voice ratio. Heuristic: count clauses with passive markers
     * (di- prefix verbs, dilakukan, dilaksanakan, dihasilkan, diharapkan, etc.)
     * divided by sentence count.
     */
    private function passiveRatio(string $content): float
    {
        $sentences = $this->splitSentences($content);
        if (empty($sentences)) return 0.0;
        $passiveCount = 0;
        $passivePattern = '/\b(di[a-z]{3,})\b/u'; // di- prefix verbs
        $aspirePattern  = '/\b(diharapkan|dilakukan|dilaksanakan|dihasilkan|dilakukan|dilakukan|disusun|diberikan|direncanakan|dianalisis|diolah|ditetapkan|digunakan|diperlukan|dikoordinasikan|dimulai|dilanjutkan|dihitung|dievaluasi|dipertimbangkan|disampaikan|dibahas|ditampilkan)\b/iu';
        foreach ($sentences as $s) {
            if (preg_match($aspirePattern, $s) || preg_match($passivePattern, $s)) {
                $passiveCount++;
            }
        }
        return $passiveCount / count($sentences);
    }

    /** Avg sentence length in words. */
    private function avgSentenceLength(string $content): float
    {
        $sentences = $this->splitSentences($content);
        if (empty($sentences)) return 0;
        $totalWords = 0;
        foreach ($sentences as $s) {
            $totalWords += str_word_count(strip_tags($s));
        }
        return $totalWords / count($sentences);
    }

    /** Heuristic: section is mostly list when > 50% lines are bulleted. */
    private function isMostlyList(string $content): bool
    {
        $lines = array_filter(array_map('trim', explode("\n", $content)), fn($l) => $l !== '');
        if (count($lines) < 3) return false;
        $bulleted = 0;
        foreach ($lines as $l) {
            if (preg_match('/^(?:[-•*]|\d+\.)\s+/u', $l)) $bulleted++;
        }
        return ($bulleted / count($lines)) > 0.4;
    }

    private function splitSentences(string $content): array
    {
        // Split on . ! ? but not on decimals like "1.5"
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z])/u', strip_tags($content));
        $parts = array_filter($parts, fn($p) => strlen(trim($p)) > 5);
        return array_values($parts);
    }
}
