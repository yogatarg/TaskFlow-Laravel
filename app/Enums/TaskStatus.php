<?php

namespace App\Enums;

/**
 * Status task sekaligus SATU-SATUNYA sumber kebenaran state machine-nya.
 *
 *   Draft              --submit--------------> Pending Approval
 *   Pending Approval   --approve-------------> Approved  [terminal]
 *   Pending Approval   --reject--------------> Rejected  [terminal]
 *   Pending Approval   --request revision----> Revision Requested
 *   Revision Requested --submit--------------> Pending Approval
 *
 * Aturan transisi sengaja ditaruh di enum, bukan di Controller atau Service, supaya
 * tidak ada dua tempat yang bisa berbeda pendapat soal "boleh pindah ke mana".
 * Policy dan ApprovalService keduanya bertanya ke sini.
 */
enum TaskStatus: string
{
    case Draft = 'Draft';
    case PendingApproval = 'Pending Approval';
    case RevisionRequested = 'Revision Requested';
    case Approved = 'Approved';
    case Rejected = 'Rejected';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Status akhir: tidak ada transisi keluar lagi. Task yang ditolak dan ingin
     * diajukan ulang harus dibuat sebagai task BARU, bukan dihidupkan kembali.
     */
    public function isTerminal(): bool
    {
        return $this->transisiYangDiizinkan() === [];
    }

    /**
     * Boleh diubah isinya (judul, deskripsi, dst.) oleh pemiliknya?
     *
     * Pending Approval sengaja TIDAK bisa diedit: approver sedang menilai isi task itu,
     * jadi isinya tidak boleh berubah di tengah jalan. Kalau mau memperbaiki, minta
     * approver mengembalikannya lewat "request revision" lebih dulu.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::RevisionRequested;
    }

    /** Boleh diajukan ke approver? */
    public function bisaDisubmit(): bool
    {
        return $this->canTransitionTo(self::PendingApproval);
    }

    /** Sedang menunggu keputusan approver? */
    public function menungguKeputusan(): bool
    {
        return $this === self::PendingApproval;
    }

    /**
     * @return list<self> status yang boleh dituju dari status ini
     */
    public function transisiYangDiizinkan(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval],
            self::RevisionRequested => [self::PendingApproval],
            self::PendingApproval => [self::Approved, self::Rejected, self::RevisionRequested],
            self::Approved, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiYangDiizinkan(), strict: true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::PendingApproval => 'bg-amber-100 text-amber-800',
            self::RevisionRequested => 'bg-orange-100 text-orange-800',
            self::Approved => 'bg-green-100 text-green-800',
            self::Rejected => 'bg-red-100 text-red-800',
        };
    }
}
