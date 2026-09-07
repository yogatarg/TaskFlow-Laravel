<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rantai approver boleh sedalam apa pun -- staf, supervisor, manajer, direktur --
 * tapi tidak boleh melingkar.
 *
 * Lingkaran tidak menimbulkan galat apa pun saat berjalan: task tetap bisa
 * diajukan dan diputuskan. Yang hilang adalah maknanya -- tidak ada lagi puncak
 * rantai, dan semua saling menyetujui secara melingkar tanpa ada yang benar-benar
 * bertanggung jawab. Karena itu harus ditolak saat penyimpanan.
 */
class RantaiApproverTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function tetapkanApprover(User $target, ?User $approver)
    {
        return $this->actingAs($this->admin)->put(route('admin.users.update', $target), [
            'role' => $target->role->value,
            'approver_id' => $approver?->id,
        ]);
    }

    public function test_rantai_bertingkat_yang_sah_tetap_diterima(): void
    {
        // staf -> supervisor -> manajer -> direktur
        $direktur = User::factory()->approver()->create();
        $manajer = User::factory()->approver()->create(['approver_id' => $direktur->id]);
        $supervisor = User::factory()->approver()->create(['approver_id' => $manajer->id]);
        $staf = User::factory()->create();

        $this->tetapkanApprover($staf, $supervisor)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($supervisor->id, $staf->refresh()->approver_id);
    }

    public function test_lingkaran_tiga_tingkat_ditolak(): void
    {
        // A -> B -> C sudah terbentuk; menjadikan A sebagai approver C akan menutup
        // lingkarannya. Inilah kasus yang sebelumnya lolos.
        $c = User::factory()->approver()->create(['name' => 'Citra']);
        $b = User::factory()->approver()->create(['name' => 'Budi', 'approver_id' => $c->id]);
        $a = User::factory()->approver()->create(['name' => 'Anisa', 'approver_id' => $b->id]);

        $this->tetapkanApprover($c, $a)->assertSessionHasErrors('approver_id');

        $this->assertNull($c->refresh()->approver_id);
    }

    public function test_lingkaran_empat_tingkat_juga_ditolak(): void
    {
        $d = User::factory()->approver()->create();
        $c = User::factory()->approver()->create(['approver_id' => $d->id]);
        $b = User::factory()->approver()->create(['approver_id' => $c->id]);
        $a = User::factory()->approver()->create(['approver_id' => $b->id]);

        $this->tetapkanApprover($d, $a)->assertSessionHasErrors('approver_id');
    }

    public function test_lingkaran_langsung_dua_tingkat_masih_ditolak(): void
    {
        $b = User::factory()->approver()->create();
        $a = User::factory()->approver()->create(['approver_id' => $b->id]);

        $this->tetapkanApprover($b, $a)->assertSessionHasErrors('approver_id');
    }

    public function test_menjadi_approver_bagi_diri_sendiri_masih_ditolak(): void
    {
        $a = User::factory()->approver()->create();

        $this->tetapkanApprover($a, $a)->assertSessionHasErrors('approver_id');
    }

    public function test_pesan_galat_menyebut_siapa_yang_menyebabkan_lingkaran(): void
    {
        $c = User::factory()->approver()->create(['name' => 'Citra Penyetuju']);
        $b = User::factory()->approver()->create(['approver_id' => $c->id]);
        $a = User::factory()->approver()->create(['name' => 'Anisa Penyetuju', 'approver_id' => $b->id]);

        $this->tetapkanApprover($c, $a);

        $this->assertStringContainsString(
            'Anisa Penyetuju',
            session('errors')->get('approver_id')[0],
        );
    }

    public function test_mengosongkan_approver_selalu_boleh(): void
    {
        $b = User::factory()->approver()->create();
        $a = User::factory()->create(['approver_id' => $b->id]);

        $this->tetapkanApprover($a, null)->assertSessionHasNoErrors();

        $this->assertNull($a->refresh()->approver_id);
    }

    public function test_data_yang_sudah_terlanjur_melingkar_tidak_membuat_penelusuran_menggantung(): void
    {
        // Lingkaran bisa saja sudah ada di database dari sebelum aturan ini dibuat.
        // Tanpa penjaga simpul-terlewati, penelusuran akan berputar selamanya.
        $b = User::factory()->approver()->create();
        $a = User::factory()->approver()->create(['approver_id' => $b->id]);
        $b->forceFill(['approver_id' => $a->id])->save();

        $baru = User::factory()->create();

        // Yang penting: permintaan ini selesai, bukan menggantung.
        $this->tetapkanApprover($baru, $a)->assertSessionHasNoErrors();

        $this->assertSame($a->id, $baru->refresh()->approver_id);
    }

    public function test_admin_tetap_bisa_menetapkan_approver_untuk_dirinya_sendiri(): void
    {
        $approver = User::factory()->approver()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'role' => Role::Admin->value,
                'approver_id' => $approver->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($approver->id, $this->admin->refresh()->approver_id);
    }
}
