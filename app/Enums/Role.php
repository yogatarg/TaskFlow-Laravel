<?php

namespace App\Enums;

/**
 * Role pengguna. Nilai enum disimpan apa adanya sebagai string di kolom `users.role`,
 * jadi nilai di sini harus sama persis dengan yang tertulis di spesifikasi (CLAUDE.md).
 */
enum Role: string
{
    case User = 'User';
    case Approver = 'Approver';
    case Admin = 'Admin';

    /** Label untuk ditampilkan di UI. */
    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Approver => 'Approver',
            self::Admin => 'Admin',
        };
    }

    /** Apakah role ini boleh memproses approval? Admin ikut bisa karena punya akses penuh. */
    public function canApprove(): bool
    {
        return $this === self::Approver || $this === self::Admin;
    }
}
