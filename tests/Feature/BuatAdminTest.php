<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Perintah taskflow:buat-admin adalah satu-satunya cara membuat Admin dari luar
 * aplikasi. Karena itu ia harus bisa diandalkan justru dalam keadaan terburuk:
 * database kosong, tidak ada seorang pun yang bisa login.
 */
class BuatAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_admin_pertama_pada_database_kosong(): void
    {
        $this->assertSame(0, User::count());

        $this->artisan('taskflow:buat-admin', ['email' => 'bos@taskflow.test'])
            ->expectsQuestion('Nama lengkap', 'Yogata Rama')
            ->expectsQuestion('Password', 'rahasia-sekali')
            ->expectsQuestion('Ulangi password', 'rahasia-sekali')
            ->assertSuccessful();

        $admin = User::sole();

        $this->assertSame('Yogata Rama', $admin->name);
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertNull($admin->approver_id);
        $this->assertTrue(Hash::check('rahasia-sekali', $admin->password));
    }

    public function test_password_disimpan_sebagai_hash_bukan_teks_biasa(): void
    {
        $this->artisan('taskflow:buat-admin', ['email' => 'bos@taskflow.test'])
            ->expectsQuestion('Nama lengkap', 'Yogata Rama')
            ->expectsQuestion('Password', 'rahasia-sekali')
            ->expectsQuestion('Ulangi password', 'rahasia-sekali')
            ->assertSuccessful();

        $this->assertNotSame('rahasia-sekali', User::sole()->password);
    }

    public function test_email_ditanyakan_kalau_tidak_diberikan_sebagai_argumen(): void
    {
        $this->artisan('taskflow:buat-admin')
            ->expectsQuestion('Email admin', 'bos@taskflow.test')
            ->expectsQuestion('Nama lengkap', 'Yogata Rama')
            ->expectsQuestion('Password', 'rahasia-sekali')
            ->expectsQuestion('Ulangi password', 'rahasia-sekali')
            ->assertSuccessful();

        $this->assertSame('bos@taskflow.test', User::sole()->email);
    }

    public function test_opsi_nama_menggantikan_pertanyaan_namanya(): void
    {
        $this->artisan('taskflow:buat-admin', [
            'email' => 'bos@taskflow.test',
            '--nama' => 'Yogata Rama',
        ])
            ->expectsQuestion('Password', 'rahasia-sekali')
            ->expectsQuestion('Ulangi password', 'rahasia-sekali')
            ->assertSuccessful();

        $this->assertSame('Yogata Rama', User::sole()->name);
    }

    public function test_user_yang_sudah_ada_dinaikkan_tanpa_mengubah_passwordnya(): void
    {
        $user = User::factory()->create([
            'email' => 'sari@taskflow.test',
            'password' => Hash::make('password-lama'),
        ]);

        $this->artisan('taskflow:buat-admin', [
            'email' => 'sari@taskflow.test',
            '--paksa' => true,
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue(Hash::check('password-lama', $user->password));
        $this->assertSame(1, User::count());
    }

    public function test_user_yang_memang_sudah_admin_tidak_diubah(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'bos@taskflow.test']);
        $sebelum = $admin->updated_at;

        $this->artisan('taskflow:buat-admin', [
            'email' => 'bos@taskflow.test',
            '--paksa' => true,
        ])
            ->expectsOutputToContain('memang sudah Admin')
            ->assertSuccessful();

        $this->assertEquals($sebelum, $admin->refresh()->updated_at);
    }

    public function test_memperingatkan_dan_batal_kalau_sudah_ada_admin(): void
    {
        User::factory()->admin()->create();

        $this->artisan('taskflow:buat-admin', ['email' => 'baru@taskflow.test'])
            ->expectsConfirmation('Tetap lanjutkan?', 'no')
            ->assertSuccessful();

        $this->assertSame(1, User::count());
    }

    public function test_konfirmasi_yang_diterima_meneruskan_pembuatan(): void
    {
        User::factory()->admin()->create();

        $this->artisan('taskflow:buat-admin', ['email' => 'baru@taskflow.test'])
            ->expectsConfirmation('Tetap lanjutkan?', 'yes')
            ->expectsQuestion('Nama lengkap', 'Admin Kedua')
            ->expectsQuestion('Password', 'rahasia-sekali')
            ->expectsQuestion('Ulangi password', 'rahasia-sekali')
            ->assertSuccessful();

        $this->assertSame(2, User::where('role', Role::Admin)->count());
    }

    public function test_paksa_melewati_konfirmasi(): void
    {
        User::factory()->admin()->create();

        // Tidak ada expectsConfirmation di sini: kalau perintahnya tetap bertanya,
        // test ini gagal. Itulah yang sedang diuji.
        $this->artisan('taskflow:buat-admin', [
            'email' => 'baru@taskflow.test',
            '--paksa' => true,
        ])
            ->expectsQuestion('Nama lengkap', 'Admin Kedua')
            ->expectsQuestion('Password', 'rahasia-sekali')
            ->expectsQuestion('Ulangi password', 'rahasia-sekali')
            ->assertSuccessful();

        $this->assertSame(2, User::where('role', Role::Admin)->count());
    }

    public function test_password_yang_tidak_cocok_ditolak(): void
    {
        $this->artisan('taskflow:buat-admin', ['email' => 'bos@taskflow.test'])
            ->expectsQuestion('Nama lengkap', 'Yogata Rama')
            ->expectsQuestion('Password', 'rahasia-sekali')
            ->expectsQuestion('Ulangi password', 'salah-ketik')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_password_terlalu_pendek_ditolak(): void
    {
        $this->artisan('taskflow:buat-admin', ['email' => 'bos@taskflow.test'])
            ->expectsQuestion('Nama lengkap', 'Yogata Rama')
            ->expectsQuestion('Password', 'abc')
            ->expectsQuestion('Ulangi password', 'abc')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_email_yang_tidak_sah_ditolak_sebelum_menanyakan_apa_pun(): void
    {
        $this->artisan('taskflow:buat-admin', ['email' => 'bukan-email'])
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
