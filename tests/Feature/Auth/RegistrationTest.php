<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_halaman_login_menautkan_ke_pendaftaran(): void
    {
        // Breeze menaruh tautan ini di halaman sambutan, yang sudah dihapus.
        // Tanpa test ini, halaman pendaftaran bisa kembali tidak terjangkau
        // tanpa ada yang menyadarinya.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'), escape: false)
            ->assertSee('Daftar di sini');
    }
}
