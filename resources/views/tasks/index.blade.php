<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ auth()->user()->isAdmin() ? 'Semua Task' : 'Task Saya' }}
            </h2>
            <a href="{{ route('tasks.create') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Task Baru
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

            <x-flash />

            <form method="GET" action="{{ route('tasks.index') }}"
                  class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status"
                            class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Semua status</option>
                        @foreach ($daftarStatus as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="label" value="Label" />
                    <select id="label" name="label"
                            class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Semua label</option>
                        @foreach ($daftarLabel as $label)
                            <option value="{{ $label->value }}" @selected(request('label') === $label->value)>
                                {{ $label->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-secondary-button type="submit">Terapkan</x-secondary-button>
                @if (request()->hasAny(['status', 'label']))
                    <a href="{{ route('tasks.index') }}" class="text-sm text-gray-500 hover:text-gray-800">Reset</a>
                @endif
            </form>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Judul</th>
                            @if (auth()->user()->isAdmin())
                                <th class="px-6 py-3">Pembuat</th>
                            @endif
                            <th class="px-6 py-3">Label</th>
                            <th class="px-6 py-3">Prioritas</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Tenggat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tasks as $task)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <a href="{{ route('tasks.show', $task) }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-900">
                                        {{ $task->title }}
                                    </a>
                                </td>
                                @if (auth()->user()->isAdmin())
                                    <td class="px-6 py-4 text-gray-600">{{ $task->creator->name }}</td>
                                @endif
                                <td class="px-6 py-4">
                                    <x-badge :tone="$task->label->badgeClass()">{{ $task->label->label() }}</x-badge>
                                </td>
                                <td class="px-6 py-4">
                                    <x-badge :tone="$task->priority->badgeClass()">{{ $task->priority->label() }}</x-badge>
                                </td>
                                <td class="px-6 py-4">
                                    <x-badge :tone="$task->status->badgeClass()">{{ $task->status->label() }}</x-badge>
                                </td>
                                <td class="px-6 py-4 {{ $task->sudahLewatTenggat() ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                    {{ $task->deadline?->translatedFormat('d M Y') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                    Belum ada task.
                                    <a href="{{ route('tasks.create') }}" class="text-indigo-600 hover:underline">Buat yang pertama</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tasks->links() }}
        </div>
    </div>
</x-app-layout>
