<?php

namespace App\Enums;

/**
 * Aksi yang menggerakkan task di sepanjang state machine.
 *
 * Nilainya memakai snake_case karena inilah yang akan disimpan di kolom
 * `approval_logs.action` sesuai spesifikasi (tahap 4).
 */
enum ApprovalAction: string
{
    case Submit = 'submit';
    case Approve = 'approve';
    case Reject = 'reject';
    case RequestRevision = 'request_revision';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Diajukan',
            self::Approve => 'Disetujui',
            self::Reject => 'Ditolak',
            self::RequestRevision => 'Diminta revisi',
        };
    }

    /**
     * Status yang dituju oleh aksi ini. Pemetaan aksi -> status tujuan ditaruh di sini
     * supaya ApprovalService tidak perlu punya rantai match sendiri, dan supaya
     * menambah aksi baru cukup disentuh di satu tempat.
     */
    public function statusTujuan(): TaskStatus
    {
        return match ($this) {
            self::Submit => TaskStatus::PendingApproval,
            self::Approve => TaskStatus::Approved,
            self::Reject => TaskStatus::Rejected,
            self::RequestRevision => TaskStatus::RevisionRequested,
        };
    }

    /**
     * Aksi yang dilakukan approver (bukan pembuat task).
     */
    public function olehApprover(): bool
    {
        return $this !== self::Submit;
    }

    /**
     * Wajib menyertakan catatan? Menolak dan meminta revisi harus disertai alasan --
     * tanpa itu pembuat task tidak tahu apa yang harus diperbaiki.
     */
    public function butuhCatatan(): bool
    {
        return $this === self::Reject || $this === self::RequestRevision;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Submit => 'bg-amber-100 text-amber-800',
            self::Approve => 'bg-green-100 text-green-800',
            self::Reject => 'bg-red-100 text-red-800',
            self::RequestRevision => 'bg-orange-100 text-orange-800',
        };
    }
}
