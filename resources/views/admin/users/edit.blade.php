<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Ubah User — {{ $user->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="email" value="Email" />
                        <p class="mt-1 text-sm text-gray-600">{{ $user->email }}</p>
                    </div>

                    <div>
                        <x-input-label for="role" value="Role" />
                        <select id="role" name="role"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>
                                    {{ $role->label() }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />
                    </div>

                    <div>
                        <x-input-label for="approver_id" value="Approver" />
                        <select id="approver_id" name="approver_id"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— Tidak ada approver —</option>
                            @foreach ($kandidatApprover as $kandidat)
                                <option value="{{ $kandidat->id }}" @selected((int) old('approver_id', $user->approver_id) === $kandidat->id)>
                                    {{ $kandidat->name }} ({{ $kandidat->role->label() }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            Task yang dibuat user ini akan diajukan ke approver yang dipilih di sini.
                            Mengubahnya tidak mengubah task lama — riwayat approval tetap tersimpan di log.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('approver_id')" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Simpan</x-primary-button>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
