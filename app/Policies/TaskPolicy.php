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

    /**
     * Mengajukan task ke approver.
     *
     * Tiga syarat, dan ketiganya perlu:
     *   1. Hanya pembuatnya yang boleh mengajukan. Admin sekalipun tidak -- mengajukan
     *      berarti menyatakan "isi ini sudah siap dinilai", dan itu hak pemiliknya.
     *   2. Statusnya memang boleh diajukan (Draft atau Revision Requested).
     *   3. Pembuatnya harus punya approver. Tanpa itu task akan menggantung di
     *      Pending Approval tanpa seorang pun yang bisa memutuskannya.
     */
    public function submit(User $user, Task $task): bool
    {
        return $task->created_by === $user->id
            && $task->status->bisaDisubmit()
            && $task->creator?->approver_id !== null;
    }

    /**
     * Memutuskan task: menyetujui, menolak, atau meminta revisi.
     *
     * Yang berhak HANYA approver yang ditunjuk untuk pembuat task ini
     * (users.approver_id milik pembuatnya). Admin tidak otomatis berhak.
     *
     * Alasannya: "akses penuh" milik Admin dalam spesifikasi adalah akses LIHAT.
     * Kalau siapa pun yang berpangkat tinggi bisa memutuskan, catatan siapa-menyetujui-apa
     * kehilangan maknanya. Kalau Admin memang perlu memutuskan, Admin cukup menjadikan
     * dirinya approver lewat halaman Kelola User -- keputusannya jadi tercatat sebagai
     * kewenangan yang memang diberikan, bukan diambil diam-diam.
     */
    public function decide(User $user, Task $task): bool
    {
        return $task->status->menungguKeputusan()
            && $task->creator?->approver_id === $user->id
            // Jaga-jaga: tidak ada yang boleh menyetujui task buatannya sendiri,
            // meski datanya sempat rusak sehingga seseorang jadi approver dirinya.
            && $task->created_by !== $user->id;
    }

    private function pemilikAtauAdmin(User $user, Task $task): bool
    {
        return $task->created_by === $user->id || $user->isAdmin();
    }
}
