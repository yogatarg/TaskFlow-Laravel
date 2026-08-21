<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Halo, {{ auth()->user()->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            <dl class="grid gap-4 sm:grid-cols-3">
                <x-stat label="Menunggu Approval" :nilai="$menunggu" tone="text-amber-600"
                        keterangan="Perlu keputusan Anda"
                        :href="route('approvals.index')" />

                <x-stat label="Disetujui Hari Ini" :nilai="$disetujuiHariIni" tone="text-green-600"
                        keterangan="Dihitung dari log, bukan dari status" />

                <x-stat label="Bawahan" :nilai="auth()->user()->approvees()->count()"
                        keterangan="User yang task-nya Anda setujui" />
            </dl>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-tenggat :tasks="$perluDitagih"
                           judul="Bawahan Perlu Ditagih"
                           kosong="Tidak ada task bawahan yang mendekati tenggat."
                           :tampilkan-pembuat="true" />

                <x-aktivitas :logs="$riwayat"
                             judul="Riwayat Keputusan Anda"
                             kosong="Anda belum pernah memproses approval." />
            </div>

            <p class="text-xs text-gray-500">
                Anda juga bisa membuat dan mengajukan task sendiri —
                task Anda diajukan ke {{ auth()->user()->approver?->name ?? 'approver Anda (belum ditetapkan)' }}.
            </p>

        </div>
    </div>
</x-app-layout>
