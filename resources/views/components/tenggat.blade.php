@props([
    'tasks',
    'judul' => 'Mendekati Tenggat',
    'kosong' => 'Tidak ada tenggat dalam waktu dekat.',
    'tampilkanPembuat' => false,
])

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h3 class="font-semibold text-gray-900">{{ $judul }}</h3>

    @forelse ($tasks as $task)
        @if ($loop->first)
            <ul class="mt-4 divide-y divide-gray-100">
        @endif

        <li class="flex flex-wrap items-center gap-x-2 gap-y-1 py-3 text-sm">
            <a href="{{ route('tasks.show', $task) }}"
               class="font-medium text-indigo-600 hover:text-indigo-900">{{ $task->title }}</a>

            @if ($tampilkanPembuat)
                <span class="text-gray-600">— {{ $task->creator->name }}</span>
            @endif

            <x-badge :tone="$task->status->badgeClass()">{{ $task->status->label() }}</x-badge>

            <span class="ms-auto text-xs {{ $task->sudahLewatTenggat() ? 'font-medium text-red-600' : 'text-gray-500' }}">
                {{ $task->deadline->translatedFormat('d M Y') }}
                @if ($task->sudahLewatTenggat())
                    (lewat)
                @endif
            </span>
        </li>

        @if ($loop->last)
            </ul>
        @endif
    @empty
        <p class="mt-2 text-sm text-gray-500">{{ $kosong }}</p>
    @endforelse
</div>
