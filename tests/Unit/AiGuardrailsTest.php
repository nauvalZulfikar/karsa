<?php

namespace Tests\Unit;

use App\Services\AiChatService;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fase 1 guardrails (G1–G7) — uji logika murni lewat reflection.
 * Tidak butuh DB: hanya nyentuh cache (array) + auth (null di test).
 */
class AiGuardrailsTest extends TestCase
{
    private function invokePriv(string $method, array $args)
    {
        $svc = app(AiChatService::class);
        $m = new ReflectionMethod($svc, $method);
        $m->setAccessible(true);
        return $m->invoke($svc, ...$args);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** G1 */
    public function test_write_tool_classification(): void
    {
        $this->assertTrue($this->invokePriv('isWriteTool', ['create_pekerjaan']));
        $this->assertTrue($this->invokePriv('isWriteTool', ['delete_pekerjaan']));
        $this->assertTrue($this->invokePriv('isWriteTool', ['generate_invoice']));
        $this->assertFalse($this->invokePriv('isWriteTool', ['get_pekerjaan_list']));
        $this->assertFalse($this->invokePriv('isWriteTool', ['parse_kak_pdf']));
        $this->assertFalse($this->invokePriv('isWriteTool', ['cross_check_rab_vs_kontrak']));
    }

    /** G2 */
    public function test_nilai_kontrak_tidak_boleh_lebih_dari_pagu(): void
    {
        $this->assertNotNull($this->invokePriv('assertNilaiSane', [['nilai_pagu' => 100, 'nilai_kontrak' => 150]]));
        $this->assertNull($this->invokePriv('assertNilaiSane', [['nilai_pagu' => 150, 'nilai_kontrak' => 100]]));
        $this->assertNull($this->invokePriv('assertNilaiSane', [['nilai_pagu' => 100]])); // satu kosong → lolos
        $this->assertNull($this->invokePriv('assertNilaiSane', [[]]));
    }

    /** G3a */
    public function test_sanity_field_kritis(): void
    {
        $base = ['nama_pekerjaan' => 'Pengawasan Jalan Ciwidey', 'tanggal_spk' => '2026-03-01', 'nilai_pagu' => 500000000];
        $this->assertNull($this->invokePriv('assertFieldKritisValid', [$base]));

        $this->assertNotNull($this->invokePriv('assertFieldKritisValid', [['nama_pekerjaan' => 'string']]));      // placeholder
        $this->assertNotNull($this->invokePriv('assertFieldKritisValid', [['nama_pekerjaan' => '']]));            // kosong
        $this->assertNotNull($this->invokePriv('assertFieldKritisValid', [$base + ['tanggal_mulai' => '1999-01-01']])); // tahun ngawur
        $this->assertNotNull($this->invokePriv('assertFieldKritisValid', [$base + ['nilai_kontrak' => 0]]));       // nilai <= 0
    }

    /** G3b */
    public function test_cross_check_vs_dokumen(): void
    {
        // Rekam "kebenaran" dari hasil parse kontrak.
        $this->invokePriv('captureParseTruth', ['kontrak', ['pekerjaan' => [
            'no_spk'        => '027/123/SPK/2026',
            'nilai_kontrak' => 480000000,
            'nilai_pagu'    => 500000000,
            'tanggal_spk'   => '2026-03-01',
        ]]]);

        $good = [
            'nama_pekerjaan' => 'Pengawasan Jalan Ciwidey',
            'no_spk'         => '027/123/SPK/2026',
            'nilai_kontrak'  => 480000000,
            'nilai_pagu'     => 500000000,
            'tanggal_spk'    => '2026-03-01',
        ];
        $this->assertNull($this->invokePriv('assertFieldKritisValid', [$good]), 'data cocok dokumen harus lolos');

        // no_spk beda → ditolak
        $this->assertNotNull($this->invokePriv('assertFieldKritisValid', [array_merge($good, ['no_spk' => '027/999/SPK/2026'])]));
        // nilai_kontrak ngarang → ditolak
        $this->assertNotNull($this->invokePriv('assertFieldKritisValid', [array_merge($good, ['nilai_kontrak' => 999000000, 'nilai_pagu' => 999000000])]));
        // tanggal beda → ditolak
        $this->assertNotNull($this->invokePriv('assertFieldKritisValid', [array_merge($good, ['tanggal_spk' => '2026-05-01'])]));
        // no_spk beda spasi/case doang → tetap lolos (normSpk)
        $this->assertNull($this->invokePriv('assertFieldKritisValid', [array_merge($good, ['no_spk' => '027/123/spk/2026 '])]));
    }
}
