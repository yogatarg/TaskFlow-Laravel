<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Exceptions\TransisiTidakSah;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perpindahan status task.
 *
 * Kenapa harus lewat sini, bukan langsung `$task->status = ...; $task->save();` di controller?
 *
 * 1. Setiap perpindahan diperiksa terhadap state machine di TaskStatus. Kalau logikanya
 *    tersebar di beberapa controller, cepat atau lambat ada satu yang lupa memeriksa.
 * 2. Mulai tahap 4, setiap perpindahan WAJIB disertai satu baris ApprovalLog. Keduanya
 *    harus berhasil atau gagal bersama -- itulah gunanya DB::transaction() di bawah.
 *    Status yang berubah tanpa log berarti riwayatnya bohong.
 * 3. Perpindahan status jadi bisa diuji tanpa menyentuh lapisan HTTP sama sekali.
 *
 * Pembagian tugas dengan lapisan lain:
 *   TaskPolicy       -> "orang ini berhak melakukannya?"      (jawabannya 403)
 *   ApprovalService  -> "perpindahannya sendiri sah?"          (jawabannya exception)
 * Keduanya diperlukan: Policy tahu soal orang, Service tahu soal status.
 */
class ApprovalService
{
    /** Pembuat task mengajukannya ke approver. */
    public function ajukan(Task $task, User $pelaku, ?string $catatan = null): Task
    {
        return $this->jalankan($task, ApprovalAction::Submit, $pelaku, $catatan);
    }

    public function setujui(Task $task, User $pelaku, ?string $catatan = null): Task
    {
        return $this->jalankan($task, ApprovalAction::Approve, $pelaku, $catatan);
    }

    public function tolak(Task $task, User $pelaku, string $catatan): Task
    {
        return $this->jalankan($task, ApprovalAction::Reject, $pelaku, $catatan);
    }

    public function mintaRevisi(Task $task, User $pelaku, string $catatan): Task
    {
        return $this->jalankan($task, ApprovalAction::RequestRevision, $pelaku, $catatan);
    }

    /**
     * @throws TransisiTidakSah kalau status sekarang tidak boleh pindah ke status tujuan
     */
    public function jalankan(Task $task, ApprovalAction $aksi, User $pelaku, ?string $catatan = null): Task
    {
        $tujuan = $aksi->statusTujuan();

        // Pemeriksaan awal supaya kegagalan yang jelas tidak perlu membuka transaksi.
        if (! $task->status->canTransitionTo($tujuan)) {
            throw TransisiTidakSah::untuk($task->status, $aksi);
        }

        return DB::transaction(function () use ($task, $aksi, $tujuan, $pelaku, $catatan) {
            /*
             * lockForUpdate() mengunci baris task ini sampai transaksi selesai, dan kita
             * BACA ULANG statusnya dari database.
             *
             * Kenapa perlu? Bayangkan approver menekan tombol "Setujui" dua kali dengan
             * cepat, atau dua approver membuka task yang sama. Dua request bisa sama-sama
             * lolos pemeriksaan di atas karena keduanya membaca status "Pending Approval"
             * sebelum salah satunya sempat menyimpan. Tanpa kunci, keduanya akan menulis --
             * dan di tahap 4 nanti, itu berarti DUA baris log approve untuk satu task.
             *
             * Dengan lockForUpdate, request kedua menunggu sampai yang pertama selesai,
             * lalu membaca status yang sudah berubah dan gagal di pemeriksaan ulang ini.
             */
            $terkunci = Task::whereKey($task->getKey())->lockForUpdate()->firstOrFail();

            if (! $terkunci->status->canTransitionTo($tujuan)) {
                throw TransisiTidakSah::untuk($terkunci->status, $aksi);
            }

            $terkunci->status = $tujuan;
            $terkunci->save();

            // TAHAP 4: satu baris ApprovalLog ditulis di sini, di dalam transaksi yang sama,
            // memakai $aksi, $pelaku, dan $catatan yang sudah tersedia sebagai parameter.

            // Segarkan objek yang dipegang pemanggil supaya tidak memakai status basi.
            $task->setRawAttributes($terkunci->getAttributes(), sync: true);

            return $task;
        });
    }
}
