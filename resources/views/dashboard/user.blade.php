<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Halo, {{ auth()->user()->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-stat label="Total Task" :nilai="$hitungan->total"
                        :href="route('tasks.index')" />

                <x-stat label="Pending Approval" :nilai="$hitungan->pending" tone="text-amber-600"
                        keterangan="Menunggu keputusan approver"
                        :href="route('tasks.index', ['status' => \App\Enums\TaskStatus::PendingApproval->value])" />

                <x-stat label="In Progress" :nilai="$hitungan->dikerjakan" tone="text-gray-900"
                        keterangan="Draft &amp; diminta revisi — masih di tangan Anda" />

                <x-stat label="Selesai" :nilai="$hitungan->selesai" tone="text-green-600"
                        keterangan="Disetujui approver"
                        :href="route('tasks.index', ['status' => \App\Enums\TaskStatus::Approved->value])" />

                <x-stat label="Ditolak" :nilai="$hitungan->ditolak" tone="text-red-600"
                        keterangan="Ajukan ulang sebagai task baru"
                        :href="route('tasks.index', ['status' => \App\Enums\TaskStatus::Rejected->value])" />
            </dl>

            @if (! auth()->user()->approver)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Anda belum punya approver, jadi task belum bisa diajukan.
                    Hubungi Admin untuk menetapkannya.
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <x-tenggat :tasks="$mendekatiTenggat" />
                <x-aktivitas :logs="$aktivitasTerbaru"
                             kosong="Belum ada aktivitas. Riwayat terisi begitu task diajukan." />
            </div>

        </div>
    </div>
</x-app-layout>
