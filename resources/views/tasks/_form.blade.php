{{--
    Bagian form yang dipakai bersama oleh halaman create dan edit.
    Tidak ada field `status` di sini: status hanya berpindah lewat state machine
    (tombol Ajukan / keputusan approver), bukan lewat form isian.
--}}

<div>
    <x-input-label for="title" value="Judul" />
    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                  :value="old('title', $task->title ?? '')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('title')" />
</div>

<div>
    <x-input-label for="description" value="Deskripsi" />
    <textarea id="description" name="description" rows="5"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $task->description ?? '') }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('description')" />
</div>

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="label" value="Label" />
        <select id="label" name="label"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($daftarLabel as $opsi)
                <option value="{{ $opsi->value }}" @selected(old('label', $task->label?->value ?? null) === $opsi->value)>
                    {{ $opsi->label() }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Kategori tampilan saja — tidak memengaruhi alur approval.</p>
        <x-input-error class="mt-2" :messages="$errors->get('label')" />
    </div>

    <div>
        <x-input-label for="priority" value="Prioritas" />
        <select id="priority" name="priority"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($daftarPrioritas as $opsi)
                <option value="{{ $opsi->value }}" @selected(old('priority', $task->priority?->value ?? null) === $opsi->value)>
                    {{ $opsi->label() }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('priority')" />
    </div>
</div>

<div>
    <x-input-label for="deadline" value="Tenggat" />
    <x-text-input id="deadline" name="deadline" type="date" class="mt-1 block"
                  :value="old('deadline', $task->deadline?->format('Y-m-d') ?? '')" />
    <x-input-error class="mt-2" :messages="$errors->get('deadline')" />
</div>
