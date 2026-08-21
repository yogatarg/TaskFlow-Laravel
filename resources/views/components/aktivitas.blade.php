@props([
    'logs',
    'judul' => 'Aktivitas Terbaru',
    'kosong' => 'Belum ada aktivitas.',
    'tampilkanTask' => true,
])

{{--
    Daftar aktivitas approval. Dipakai ketiga dashboard dengan judul berbeda.
    Data disiapkan controller lengkap dengan relasi task dan actor, jadi komponen
    ini tidak melakukan query sendiri.
--}}

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h3 class="font-semibold text-gray-900">{{ $judul }}</h3>

    @forelse ($logs as $log)
        @if ($loop->first)
            <ul class="mt-4 divide-y divide-gray-100">
        @endif

        <li class="flex flex-wrap items-center gap-x-2 gap-y-1 py-3 text-sm">
            <x-badge :tone="$log->action->badgeClass()">{{ $log->action->label() }}</x-badge>

            @if ($tampilkanTask)
                <a href="{{ route('tasks.show', $log->task_id) }}"
                   class="font-medium text-indigo-600 hover:text-indigo-900">{{ $log->task->title }}</a>
            @endif

            <span class="text-gray-600">oleh {{ $log->actor->name }}</span>
            <span class="ms-auto text-xs text-gray-500">{{ $log->timestamp->diffForHumans() }}</span>

            @if ($log->catatan)
                <p class="w-full text-xs text-gray-600">“{{ $log->catatan }}”</p>
            @endif
        </li>

        @if ($loop->last)
            </ul>
        @endif
    @empty
        <p class="mt-2 text-sm text-gray-500">{{ $kosong }}</p>
    @endforelse
</div>
