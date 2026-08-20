<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Task Baru</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="mb-6 text-sm text-gray-600">
                    Task tersimpan sebagai <strong>Draft</strong>. Setelah isinya siap, ajukan ke approver
                    dari halaman detail task.
                </p>

                <form method="POST" action="{{ route('tasks.store') }}" class="space-y-6">
                    @csrf
                    @include('tasks._form', ['task' => null])

                    <div class="flex items-center gap-4">
                        <x-primary-button>Simpan sebagai Draft</x-primary-button>
                        <a href="{{ route('tasks.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
