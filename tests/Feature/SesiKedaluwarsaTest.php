<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Token CSRF yang kedaluwarsa tidak boleh berakhir di halaman "419 | Page Expired"
 * yang telanjang. Bagi pengunjung, halaman itu terbaca seperti aplikasi rusak.
 *
 * Middleware CSRF dimatikan pada test biasa, jadi di sini route uji sengaja
 * dibuat untuk melempar TokenMismatchException secara langsung -- yang diuji
 * adalah penanganannya di bootstrap/app.php, bukan middleware-nya.
 */
class SesiKedaluwarsaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__uji-token-basi', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    }

    public function test_tamu_diarahkan_ke_login_dengan_pesan_yang_jelas(): void
    {
        $this->get('/__uji-token-basi')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertStringContainsString(
            'Sesi Anda sudah berakhir',
            session('status'),
        );
    }

    public function test_pesannya_benar_benar_tampil_di_halaman_login(): void
    {
        $this->followingRedirects()
            ->get('/__uji-token-basi')
            ->assertOk()
            ->assertSee('Sesi Anda sudah berakhir');
    }

    public function test_user_yang_masih_login_dikembalikan_ke_halaman_asalnya(): void
    {
        $user = User::factory()->create();

        // Sesi masih hidup, hanya tokennya yang basi -- pengunjung tidak perlu
        // dibawa ke halaman login, cukup dikembalikan ke tempat semula.
        $this->actingAs($user)
            ->from(route('tasks.index'))
            ->get('/__uji-token-basi')
            ->assertRedirect(route('tasks.index'))
            ->assertSessionHas('status');
    }

    public function test_permintaan_json_mendapat_419_bukan_pengalihan(): void
    {
        $this->getJson('/__uji-token-basi')
            ->assertStatus(419)
            ->assertJsonStructure(['message']);
    }

    public function test_masa_sesi_cukup_panjang_untuk_demo_publik(): void
    {
        // Bawaan Laravel 120 menit terlalu pendek untuk demo di instance yang
        // tidur tiap 15 menit; pengunjung yang kembali disambut sesi berakhir.
        $this->assertGreaterThanOrEqual(480, config('session.lifetime'));
    }
}
