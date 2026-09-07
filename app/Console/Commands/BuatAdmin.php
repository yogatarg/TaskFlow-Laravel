<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Jalan keluar untuk masalah bootstrap: siapa yang mengangkat admin pertama?
 *
 * Seluruh pengaturan role di aplikasi ini hanya bisa dilakukan oleh Admin. Aturan
 * itu memang disengaja, tapi menimbulkan satu keadaan buntu: kalau database
 * produksi tidak pernah di-seed -- atau admin terakhir terlanjur dihapus -- tidak
 * ada lagi seorang pun yang berhak mengangkat admin baru. Aplikasinya hidup, tapi
 * tidak ada yang bisa mengelolanya, dan satu-satunya jalan tersisa adalah menyunting
 * baris di database secara langsung.
 *
 * Perintah ini menyediakan jalan yang lebih baik. Wewenangnya tidak datang dari
 * dalam aplikasi, melainkan dari akses ke shell server itu sendiri -- dan orang yang
 * sudah bisa membuka shell produksi memang sudah memegang kunci segalanya. Jadi
 * tidak ada wewenang baru yang diberikan di sini, hanya dipindahkan ke jalur yang
 * bisa diaudit.
 *
 * Password sengaja TIDAK disediakan sebagai opsi baris perintah. Argumen perintah
 * tersimpan di riwayat shell dan terbaca oleh siapa pun yang menjalankan `ps` di
 * mesin yang sama, jadi satu-satunya cara memasukkannya adalah lewat prompt
 * tersembunyi.
 */
class BuatAdmin extends Command
{
    protected $signature = 'taskflow:buat-admin
                            {email? : Email admin yang akan dibuat atau dinaikkan rolenya}
                            {--nama= : Nama untuk akun baru (ditanyakan kalau tidak diisi)}
                            {--paksa : Lanjutkan tanpa bertanya meski sudah ada admin lain}';

    protected $description = 'Membuat admin pertama, atau menaikkan user yang sudah ada menjadi Admin';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email admin');

        if (! $this->emailValid($email)) {
            return self::FAILURE;
        }

        // Perintah ini ada untuk keadaan darurat, jadi tidak dilarang saat admin
        // masih ada -- menaikkan admin kedua adalah penggunaan yang sah. Tapi kalau
        // ternyata masih ada admin, orang yang menjalankannya pantas diberi tahu:
        // mungkin ia mengira sedang memperbaiki sistem yang terkunci, padahal tidak.
        $jumlahAdmin = User::where('role', Role::Admin)->count();

        if ($jumlahAdmin > 0 && ! $this->option('paksa')) {
            $this->warn("Sudah ada {$jumlahAdmin} admin di sistem ini.");

            if (! $this->confirm('Tetap lanjutkan?', false)) {
                $this->line('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        $user = User::where('email', $email)->first();

        return $user
            ? $this->naikkan($user)
            : $this->buatBaru($email);
    }

    /**
     * User yang sudah ada cukup dinaikkan rolenya.
     *
     * Password-nya sengaja tidak disentuh. Perintah ini dijalankan oleh operator
     * server, bukan oleh pemilik akun, dan mengganti password orang lain diam-diam
     * bukan wewenang yang diminta di sini. Kalau memang perlu diganti, ada alur
     * lupa password yang jejaknya jelas.
     */
    private function naikkan(User $user): int
    {
        if ($user->role === Role::Admin) {
            $this->info("{$user->name} <{$user->email}> memang sudah Admin. Tidak ada yang diubah.");

            return self::SUCCESS;
        }

        $sebelumnya = $user->role->label();

        $user->role = Role::Admin;
        $user->save();

        $this->info("{$user->name} <{$user->email}> dinaikkan dari {$sebelumnya} menjadi Admin.");
        $this->line('Password-nya tidak diubah.');

        return self::SUCCESS;
    }

    private function buatBaru(string $email): int
    {
        $this->line("Belum ada user dengan email {$email}. Akun baru akan dibuat.");

        $nama = $this->option('nama') ?: $this->ask('Nama lengkap');
        $password = $this->secret('Password');
        $konfirmasi = $this->secret('Ulangi password');

        $validator = Validator::make(
            [
                'name' => $nama,
                'password' => $password,
                'password_confirmation' => $konfirmasi,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $nama;
        $user->email = $email;
        $user->password = $password;   // cast 'hashed' di model yang meng-hash-nya
        $user->role = Role::Admin;
        $user->approver_id = null;     // admin pertama belum punya atasan
        $user->save();

        $this->info("Admin {$user->name} <{$user->email}> berhasil dibuat.");

        return self::SUCCESS;
    }

    private function emailValid(?string $email): bool
    {
        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'string', 'email', 'max:255']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return false;
        }

        return true;
    }
}
