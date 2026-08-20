<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Menjawab "user ini boleh apa terhadap task ITU".
 *
 * Bedakan dengan EnsureUserHasRole yang hanya menjawab "role apa yang boleh masuk
 * halaman ini". Middleware tidak tahu apa-apa soal task tertentu; Policy-lah yang tahu.
 *
 * Dua sumbu pertimbangan selalu dipakai bersama:
 *   1. Kepemilikan  — apakah task ini miliknya? (atau dia Admin)
 *   2. Status       — apakah status task saat ini mengizinkan aksi tersebut?
 */
class TaskPolicy
{
    /** Setiap user yang login punya daftar task-nya sendiri. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        if ($this->pemilikAtauAdmin($user, $task)) {
            return true;
        }

        // Approver boleh melihat task milik bawahannya. Baru benar-benar terpakai
        // di tahap 3 (inbox approval), tapi aturannya memang milik lapisan ini.
        return $task->creator?->approver_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Isi task hanya boleh diubah selama masih Draft atau Revision Requested.
     * Saat Pending Approval isinya dibekukan supaya approver menilai versi yang tetap;
     * saat Approved/Rejected task sudah jadi arsip.
     */
    public function update(User $user, Task $task): bool
    {
        return $this->pemilikAtauAdmin($user, $task)
            && $task->status->isEditable();
    }

    /**
     * Aturan hapus sengaja disamakan dengan aturan edit. Task yang sudah diajukan
     * atau sudah diputuskan tidak boleh hilang, karena riwayat approval-nya (tahap 4)
     * harus tetap bisa ditelusuri.
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->pemilikAtauAdmin($user, $task)
            && $task->status->isEditable();
    }

    private function pemilikAtauAdmin(User $user, Task $task): bool
    {
        return $task->created_by === $user->id || $user->isAdmin();
    }
}
