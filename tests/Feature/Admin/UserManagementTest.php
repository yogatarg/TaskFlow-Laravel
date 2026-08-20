<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_biasa_tidak_bisa_membuka_halaman_kelola_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_approver_juga_tidak_bisa_membuka_halaman_kelola_user(): void
    {
        $approver = User::factory()->approver()->create();

        $this->actingAs($approver)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_admin_bisa_mengubah_role_dan_approver_user(): void
    {
        $admin = User::factory()->admin()->create();
        $approver = User::factory()->approver()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'role' => Role::User->value,
                'approver_id' => $approver->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame(Role::User, $user->role);
        $this->assertSame($approver->id, $user->approver_id);
    }

    public function test_approver_harus_berrole_approver_atau_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $userLain = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'role' => Role::User->value,
                'approver_id' => $userLain->id,
            ])
            ->assertSessionHasErrors('approver_id');

        $this->assertNull($user->refresh()->approver_id);
    }

    public function test_user_tidak_boleh_menjadi_approver_dirinya_sendiri(): void
    {
        $admin = User::factory()->admin()->create();
        $approver = User::factory()->approver()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $approver), [
                'role' => Role::Approver->value,
                'approver_id' => $approver->id,
            ])
            ->assertSessionHasErrors('approver_id');
    }

    public function test_lingkaran_approval_langsung_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $a = User::factory()->approver()->create();
        $b = User::factory()->approver()->create(['approver_id' => $a->id]);

        // A tidak boleh mengambil B sebagai approver, karena B sudah dipegang A.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $a), [
                'role' => Role::Approver->value,
                'approver_id' => $b->id,
            ])
            ->assertSessionHasErrors('approver_id');
    }

    public function test_admin_tidak_bisa_menurunkan_role_akunnya_sendiri(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'role' => Role::User->value,
                'approver_id' => null,
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::Admin, $admin->refresh()->role);
    }

    public function test_approver_yang_masih_punya_bawahan_tidak_bisa_diturunkan(): void
    {
        $admin = User::factory()->admin()->create();
        $approver = User::factory()->approver()->create();
        User::factory()->bawahanDari($approver)->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $approver), [
                'role' => Role::User->value,
                'approver_id' => null,
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_role_tidak_bisa_diubah_lewat_form_profil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'role' => Role::Admin->value,
        ]);

        $this->assertSame(Role::User, $user->refresh()->role);
    }

    public function test_pendaftar_baru_selalu_menjadi_user_biasa(): void
    {
        $this->post(route('register'), [
            'name' => 'Calon Admin',
            'email' => 'calon@taskflow.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::Admin->value,
        ]);

        $baru = User::where('email', 'calon@taskflow.test')->firstOrFail();

        $this->assertSame(Role::User, $baru->role);
        $this->assertNull($baru->approver_id);
    }

    public function test_halaman_kelola_user_dan_form_ubah_bisa_dirender(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Utama']);
        $approver = User::factory()->approver()->create(['name' => 'Budi Penyetuju']);
        $user = User::factory()->create(['name' => 'Sari Biasa']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Sari Biasa')
            ->assertSee('Budi Penyetuju');

        // Dropdown approver hanya berisi Approver/Admin, dan tidak memuat user biasa.
        $this->actingAs($admin)
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('Budi Penyetuju')
            ->assertSee('Admin Utama')
            ->assertDontSee('<option value="'.$user->id.'"', escape: false);
    }
}
