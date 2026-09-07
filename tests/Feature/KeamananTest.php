<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class KeamananTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- header keamanan

    public function test_header_keamanan_terpasang_di_setiap_halaman(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy');
    }

    public function test_header_keamanan_juga_terpasang_pada_halaman_terautentikasi(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_versi_php_tidak_diumumkan(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeaderMissing('X-Powered-By');
    }

    public function test_hsts_tidak_dipasang_pada_koneksi_tidak_aman(): void
    {
        // HSTS pada koneksi biasa tidak berguna, dan di lingkungan lokal justru
        // memaksa peramban memakai https untuk seluruh localhost.
        $this->get(route('login'))
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    // ------------------------------------------- pembatasan percobaan login

    public function test_login_terkunci_setelah_lima_percobaan_gagal(): void
    {
        RateLimiter::clear('salah@taskflow.test|127.0.0.1');

        $user = User::factory()->create(['email' => 'salah@taskflow.test']);

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'sandi-salah',
            ])->assertSessionHasErrors('email');
        }

        // Percobaan keenam harus ditolak karena terlalu sering, bukan karena
        // sandinya salah. Pesannya menyebutkan berapa detik lagi bisa dicoba.
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'sandi-salah',
        ])->assertSessionHasErrors('email');

        $pesan = session('errors')->get('email')[0];

        $this->assertStringContainsString('seconds', $pesan);
    }

    public function test_login_yang_berhasil_mengosongkan_hitungan_percobaan(): void
    {
        RateLimiter::clear('benar@taskflow.test|127.0.0.1');

        $user = User::factory()->create(['email' => 'benar@taskflow.test']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticated();

        $this->assertSame(0, RateLimiter::attempts('benar@taskflow.test|127.0.0.1'));
    }
}
