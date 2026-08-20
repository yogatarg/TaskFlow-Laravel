<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Menunggu Persetujuan Anda
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

            <x-flash />

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Judul</th>
                            <th class="px-6 py-3">Diajukan oleh</th>
                            <th class="px-6 py-3">Label</th>
                            <th class="px-6 py-3">Prioritas</th>
                            <th class="px-6 py-3">Tenggat</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tasks as $task)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $task->title }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $task->creator->name }}</td>
                                <td class="px-6 py-4">
                                    <x-badge :tone="$task->label->badgeClass()">{{ $task->label->label() }}</x-badge>
                                </td>
                                <td class="px-6 py-4">
                                    <x-badge :tone="$task->priority->badgeClass()">{{ $task->priority->label() }}</x-badge>
                                </td>
                                <td class="px-6 py-4 {{ $task->sudahLewatTenggat() ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                    {{ $task->deadline?->translatedFormat('d M Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('tasks.show', $task) }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-900">Tinjau</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                    Tidak ada task yang menunggu keputusan Anda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tasks->links() }}

            <p class="text-xs text-gray-500">
                Daftar ini berisi task berstatus <strong>Pending Approval</strong> milik user yang
                approver-nya adalah Anda. Diurutkan berdasarkan tenggat terdekat.
            </p>
        </div>
    </div>
</x-app-layout>
