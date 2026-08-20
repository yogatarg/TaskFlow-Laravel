<?php

namespace Database\Seeders;

use App\Enums\TaskLabel;
use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contoh task untuk mencoba CRUD. Semuanya sengaja berstatus Draft — perpindahan
 * status hanya boleh terjadi lewat state machine (tahap 3), bukan lewat seeder.
 */
class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $sari = User::where('email', 'sari@taskflow.test')->first();
        $rina = User::where('email', 'rina@taskflow.test')->first();

        if (! $sari || ! $rina) {
            return;
        }

        $contoh = [
            [$sari, 'Rekap absensi tim minggu ini', TaskLabel::Harian, TaskPriority::Sedang, 3],
            [$sari, 'Proposal anggaran kuartal berikutnya', TaskLabel::Proposal, TaskPriority::Tinggi, 10],
            [$sari, 'Notulen rapat koordinasi', TaskLabel::Meeting, TaskPriority::Rendah, 1],
            [$rina, 'Perbarui data vendor', TaskLabel::Harian, TaskPriority::Rendah, 5],
            [$rina, 'Proposal kerja sama dengan mitra baru', TaskLabel::Proposal, TaskPriority::Tinggi, 14],
        ];

        foreach ($contoh as [$pemilik, $judul, $label, $prioritas, $hari]) {
            Task::firstOrCreate(
                ['created_by' => $pemilik->id, 'title' => $judul],
                [
                    'description' => 'Contoh data awal untuk mencoba alur TaskFlow.',
                    'label' => $label,
                    'priority' => $prioritas,
                    'deadline' => now()->addDays($hari),
                ],
            );
        }
    }
}
