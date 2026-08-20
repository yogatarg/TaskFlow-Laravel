<?php

namespace App\Exceptions;

use App\Enums\ApprovalAction;
use App\Enums\TaskStatus;
use RuntimeException;

/**
 * Dilempar kalau ada yang mencoba memindahkan task ke status yang tidak diizinkan
 * state machine -- misalnya menyetujui task yang masih Draft.
 *
 * Ini adalah jaring pengaman lapisan terakhir. Dalam pemakaian normal, Policy sudah
 * menolak lebih dulu dengan 403 dan tombolnya pun tidak ditampilkan. Exception ini
 * baru muncul kalau ada jalur yang lolos dari keduanya -- misalnya kode baru yang
 * memanggil ApprovalService langsung tanpa lewat Controller.
 */
class TransisiTidakSah extends RuntimeException
{
    public static function untuk(TaskStatus $dari, ApprovalAction $aksi): self
    {
        return new self(sprintf(
            'Task berstatus "%s" tidak bisa "%s" (tujuan: "%s"). Transisi yang diizinkan dari sini: %s.',
            $dari->value,
            $aksi->value,
            $aksi->statusTujuan()->value,
            $dari->isTerminal()
                ? 'tidak ada, status ini final'
                : implode(', ', array_map(fn (TaskStatus $s) => $s->value, $dari->transisiYangDiizinkan())),
        ));
    }
}
