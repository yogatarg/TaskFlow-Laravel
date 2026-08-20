<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $task->title }}</h2>
            <x-badge :tone="$task->status->badgeClass()">{{ $task->status->label() }}</x-badge>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">

            <x-flash />

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Label</dt>
                        <dd class="mt-1"><x-badge :tone="$task->label->badgeClass()">{{ $task->label->label() }}</x-badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Prioritas</dt>
                        <dd class="mt-1"><x-badge :tone="$task->priority->badgeClass()">{{ $task->priority->label() }}</x-badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Tenggat</dt>
                        <dd class="mt-1 text-sm {{ $task->sudahLewatTenggat() ? 'font-medium text-red-600' : 'text-gray-900' }}">
                            {{ $task->deadline?->translatedFormat('d F Y') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Dibuat oleh</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $task->creator->name }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Akan disetujui oleh</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if ($task->approver())
                                {{ $task->approver()->name }}
                            @else
                                <span class="text-amber-700">
                                    Belum ada approver. Hubungi Admin untuk menetapkan approver
                                    bagi {{ $task->creator->name }}.
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Deskripsi</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">
                            {{ $task->description ?: '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @can('update', $task)
                    <a href="{{ route('tasks.edit', $task) }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        Ubah
                    </a>
                @endcan

                @can('delete', $task)
                    <form method="POST" action="{{ route('tasks.destroy', $task) }}"
                          onsubmit="return confirm('Hapus task ini? Tindakan ini tidak bisa dibatalkan.')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">Hapus</x-danger-button>
                    </form>
                @endcan

                <a href="{{ route('tasks.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    Kembali ke daftar
                </a>
            </div>

            @unless ($task->status->isEditable())
                <p class="text-sm text-gray-500">
                    @if ($task->status->menungguKeputusan())
                        Task sedang dinilai approver, jadi isinya dikunci. Minta approver mengembalikannya
                        lewat "minta revisi" kalau ada yang perlu diperbaiki.
                    @else
                        Status <strong>{{ $task->status->label() }}</strong> bersifat final — task ini
                        menjadi arsip dan tidak bisa diubah atau dihapus lagi.
                    @endif
                </p>
            @endunless

        </div>
    </div>
</x-app-layout>
