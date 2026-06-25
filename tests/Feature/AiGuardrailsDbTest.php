<?php

namespace Tests\Feature;

use App\Models\Pekerjaan;
use App\Models\User;
use App\Services\AiChatService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BidangSeeder;
use Database\Seeders\PerusahaanSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fase 1 guardrails yang butuh DB — diuji lewat executeTool() asli (bukan reflection helper).
 * G2 (tolak kontrak>pagu di create), G4 (soft delete), G5 (staff tanpa role).
 */
class AiGuardrailsDbTest extends TestCase
{
    use RefreshDatabase;

    private AiChatService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, AdminSeeder::class, BidangSeeder::class, PerusahaanSeeder::class]);
        $this->actingAs(User::where('email', 'admin@dputr.go.id')->first());
        $this->svc = app(AiChatService::class);
    }

    private function tool(string $name, array $input): array
    {
        $m = new ReflectionMethod($this->svc, 'executeTool');
        $m->setAccessible(true);
        return $m->invoke($this->svc, $name, $input);
    }

    /** G2 */
    public function test_create_menolak_kontrak_lebih_besar_dari_pagu(): void
    {
        $res = $this->tool('create_pekerjaan', [
            'nama_pekerjaan' => 'Uji Ketuker Angka Pagu Kontrak',
            'bidang_kode'    => 'JL',
            'nilai_pagu'     => 100_000_000,
            'nilai_kontrak'  => 200_000_000,
            'force_create'   => true,
        ]);
        $this->assertArrayHasKey('error', $res);
        $this->assertArrayNotHasKey('sukses', $res);
        $this->assertStringContainsStringIgnoringCase('pagu', $res['error']);
    }

    /** G4 */
    public function test_delete_pekerjaan_adalah_soft_delete(): void
    {
        $create = $this->tool('create_pekerjaan', [
            'nama_pekerjaan' => 'Uji Hapus Proyek Soft Delete',
            'bidang_kode'    => 'JL',
            'force_create'   => true,
        ]);
        $this->assertTrue($create['sukses'] ?? false, json_encode($create));
        $id = $create['pekerjaan_id'];

        $del = $this->tool('delete_pekerjaan', ['pekerjaan_id' => $id, 'alasan' => 'uji']);
        $this->assertTrue($del['sukses'] ?? false);
        $this->assertStringContainsStringIgnoringCase('tong sampah', $del['pesan']);

        // Hilang dari query normal, TAPI masih ada (bisa dipulihkan) — bukan musnah.
        $this->assertNull(Pekerjaan::find($id));
        $this->assertNotNull(Pekerjaan::withTrashed()->find($id));
    }

    /** G8 */
    public function test_exact_duplicate_diblok_meski_force_create(): void
    {
        $args = [
            'nama_pekerjaan' => 'Proyek Kembar Uji Geoteknik',
            'bidang_kode'    => 'JL',
            'no_spk'         => 'SPK/UJI/001/2026',
            'force_create'   => true,
        ];

        $first = $this->tool('create_pekerjaan', $args);
        $this->assertTrue($first['sukses'] ?? false, json_encode($first));

        // Bikin lagi: nama+bidang+tahun sama persis, SPK beda → tetap DIBLOK walau force_create.
        $dup = $this->tool('create_pekerjaan', array_merge($args, ['no_spk' => 'SPK/UJI/999/2026']));
        $this->assertSame('exact_duplicate_found', $dup['error'] ?? null, json_encode($dup));
        $this->assertSame($first['pekerjaan_id'], $dup['existing_id'] ?? null);

        // Dengan allow_duplicate=true → baru boleh kembar (jalan keluar eksplisit).
        $forced = $this->tool('create_pekerjaan', array_merge($args, ['no_spk' => 'SPK/UJI/999/2026', 'allow_duplicate' => true]));
        $this->assertTrue($forced['sukses'] ?? false, json_encode($forced));
    }

    /** G5 */
    public function test_invite_staff_tidak_dapat_role_vendor(): void
    {
        $res = $this->tool('invite_staff_user', [
            'email'         => 'staff.uji@dputr.go.id',
            'nama'          => 'Staff Uji',
            'perusahaan_id' => 1,
            'pekerjaan_id'  => 1,
        ]);
        $this->assertTrue($res['sukses'] ?? false, json_encode($res));

        $u = User::where('email', 'staff.uji@dputr.go.id')->first();
        $this->assertNotNull($u);
        $this->assertCount(0, $u->roles, 'Staff TIDAK boleh dapat role apa pun (apalagi vendor).');
        $this->assertFalse($u->hasRole('vendor'));
    }
}
