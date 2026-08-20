<?php

namespace App\Enums;

/**
 * Kategori tampilan saja. Sesuai spesifikasi, label TIDAK memengaruhi alur approval —
 * task berlabel Proposal dan Harian melewati state machine yang sama persis.
 */
enum TaskLabel: string
{
    case Harian = 'Harian';
    case Proposal = 'Proposal';
    case Meeting = 'Meeting';

    public function label(): string
    {
        return $this->value;
    }

    /** Kelas Tailwind untuk badge di tabel. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Harian => 'bg-gray-100 text-gray-800',
            self::Proposal => 'bg-amber-100 text-amber-800',
            self::Meeting => 'bg-sky-100 text-sky-800',
        };
    }
}
