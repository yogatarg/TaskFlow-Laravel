<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Log Approval
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

            <form method="GET" action="{{ route('admin.logs.index') }}"
                  class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                <div>
                    <x-input-label for="cari" value="Judul task" />
                    <x-text-input id="cari" name="cari" type="search" class="mt-1 block"
                                  :value="request('cari')" placeholder="cari judul…" />
                </div>

                <div>
                    <x-input-label for="actor_id" value="Pelaku" />
                    <select id="actor_id" name="actor_id"
                            class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Semua pelaku</option>
                        @foreach ($daftarPelaku as $pelaku)
                            <option value="{{ $pelaku->id }}" @selected((int) request('actor_id') === $pelaku->id)>
                                {{ $pelaku->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="action" value="Aksi" />
                    <select id="action" name="action"
                            class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Semua aksi</option>
                        @foreach ($daftarAksi as $aksi)
                            <option value="{{ $aksi->value }}" @selected(request('action') === $aksi->value)>
                                {{ $aksi->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <x-secondary-button type="submit">Terapkan</x-secondary-button>

                @if (request()->hasAny(['cari', 'actor_id', 'action']))
                    <a href="{{ route('admin.logs.index') }}" class="text-sm text-gray-500 hover:text-gray-800">Reset</a>
                @endif
            </form>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Waktu</th>
                            <th class="px-6 py-3">Task</th>
                            <th class="px-6 py-3">Aksi</th>
                            <th class="px-6 py-3">Pelaku</th>
                            <th class="px-6 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-gray-600">
                                    {{ $log->timestamp->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('tasks.show', $log->task_id) }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-900">
                                        {{ $log->task->title }}
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <x-badge :tone="$log->action->badgeClass()">{{ $log->action->label() }}</x-badge>
                                </td>
                                <td class="px-6 py-4 text-gray-700">{{ $log->actor->name }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $log->catatan ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                    Tidak ada log yang cocok.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}

            <p class="text-xs text-gray-500">
                Log bersifat <strong>append-only</strong> — tidak bisa diubah maupun dihapus,
                termasuk oleh Admin. Karena itu halaman ini hanya menampilkan, tanpa tombol aksi.
            </p>
        </div>
    </div>
</x-app-layout>
