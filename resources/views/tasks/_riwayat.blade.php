{{--
    Riwayat approval satu task, urut kronologis. Data disiapkan di
    TaskController::show() lewat eager loading, jadi partial ini tidak melakukan
    query sendiri -- penting supaya ia tetap murah dipanggil dari mana pun.
--}}

<div class="bg-white p-6 shadow-sm sm:rounded-lg">
    <h3 class="font-semibold text-gray-900">Riwayat Approval</h3>

    @forelse ($task->approvalLogs as $log)
        @if ($loop->first)
            <ol class="mt-4 space-y-4">
        @endif

        <li class="flex gap-4">
            <div class="flex flex-col items-center">
                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-gray-400"></span>
                @unless ($loop->last)
                    <span class="mt-1 w-px grow bg-gray-200"></span>
                @endunless
            </div>

            <div class="pb-1">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge :tone="$log->action->badgeClass()">{{ $log->action->label() }}</x-badge>
                    <span class="text-sm font-medium text-gray-900">{{ $log->actor->name }}</span>
                    <span class="text-xs text-gray-500">
                        {{ $log->timestamp->translatedFormat('d M Y, H:i') }}
                    </span>
                </div>

                @if ($log->catatan)
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $log->catatan }}</p>
                @endif
            </div>
        </li>

        @if ($loop->last)
            </ol>
        @endif
    @empty
        <p class="mt-2 text-sm text-gray-500">
            Belum ada aktivitas. Riwayat mulai terisi begitu task diajukan.
        </p>
    @endforelse
</div>
