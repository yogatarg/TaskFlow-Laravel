<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Dashboard Admin
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat label="Total User" :nilai="$totalUser" :href="route('admin.users.index')" />

                <x-stat label="Total Task" :nilai="$totalTask" :href="route('tasks.index')" />

                <x-stat label="Menunggu Approval" :nilai="$menungguSemua" tone="text-amber-600"
                        keterangan="Di seluruh organisasi" />

                <x-stat label="Tanpa Approver" :nilai="$tanpaApprover"
                        :tone="$tanpaApprover > 0 ? 'text-red-600' : 'text-gray-900'"
                        keterangan="User yang belum bisa mengajukan task"
                        :href="route('admin.users.index')" />
            </dl>

            {{--
                Grafik batang murni CSS: tinggi tiap batang dihitung sebagai persentase
                terhadap nilai tertinggi. Tanpa pustaka grafik, tanpa JavaScript, dan
                tanpa permintaan ke jaringan luar.
            --}}
            @php
                $puncak = max(1, max($grafikBulanan));
            @endphp

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-900">Task Dibuat per Bulan</h3>
                <p class="mt-1 text-xs text-gray-500">12 bulan terakhir</p>

                <div class="mt-6 flex h-48 items-end gap-2 sm:gap-3">
                    @foreach ($grafikBulanan as $bulan => $jumlah)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <span class="text-xs font-medium {{ $jumlah > 0 ? 'text-gray-700' : 'text-gray-300' }}">
                                {{ $jumlah }}
                            </span>

                            <div class="w-full rounded-t bg-indigo-500 {{ $jumlah === 0 ? 'bg-gray-100' : '' }}"
                                 style="height: {{ $jumlah === 0 ? 2 : round($jumlah / $puncak * 100) }}%"
                                 role="img"
                                 aria-label="{{ $bulan }}: {{ $jumlah }} task"></div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex gap-2 sm:gap-3">
                    @foreach (array_keys($grafikBulanan) as $bulan)
                        <div class="flex-1 text-center text-[10px] leading-tight text-gray-500">
                            {{ $bulan }}
                        </div>
                    @endforeach
                </div>
            </div>

            <x-aktivitas :logs="$aktivitasTerbaru"
                         judul="Aktivitas Terbaru Seluruh Organisasi"
                         kosong="Belum ada aktivitas approval sama sekali." />

            <p class="text-xs text-gray-500">
                Riwayat lengkap beserta filter ada di
                <a href="{{ route('admin.logs.index') }}" class="text-indigo-600 hover:underline">halaman Log</a>.
            </p>

        </div>
    </div>
</x-app-layout>
