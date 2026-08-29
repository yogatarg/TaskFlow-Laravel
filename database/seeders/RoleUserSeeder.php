<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun awal untuk mencoba ketiga role. Password semuanya: password
 */
class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->buatUser('Admin TaskFlow', 'admin@taskflow.test', Role::Admin);
        $approver = $this->buatUser('Budi Approver', 'approver@taskflow.test', Role::Approver);

        // Dua user biasa yang task-nya diajukan ke Budi.
        $this->buatUser('Sari User', 'sari@taskflow.test', Role::User, $approver);
        $this->buatUser('Rina User', 'rina@taskflow.test', Role::User, $approver);

        // Approver sendiri tetap punya atasan: task miliknya diajukan ke Admin.
        $approver->approver_id = $admin->id;
        $approver->save();
    }

    private function buatUser(string $nama, string $email, Role $role, ?User $approver = null): User
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->name = $nama;
        $user->password = Hash::make('password');
        $user->role = $role;
        $user->approver_id = $approver?->id;
        $user->save();

        return $user;
    }
}
