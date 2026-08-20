<?php

namespace App\Enums;

/**
 * Prioritas task. Spesifikasi hanya menyebut adanya kolom `priority` tanpa merinci
 * nilainya, jadi tiga tingkat ini adalah keputusan implementasi — bukan kutipan spesifikasi.
 */
enum TaskPriority: string
{
    case Rendah = 'Rendah';
    case Sedang = 'Sedang';
    case Tinggi = 'Tinggi';

    public function label(): string
    {
        return $this->value;
    }

    /** Urutan untuk sorting: makin besar makin mendesak. */
    public function bobot(): int
    {
        return match ($this) {
            self::Rendah => 1,
            self::Sedang => 2,
            self::Tinggi => 3,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Rendah => 'bg-slate-100 text-slate-700',
            self::Sedang => 'bg-yellow-100 text-yellow-800',
            self::Tinggi => 'bg-red-100 text-red-800',
        };
    }
}
