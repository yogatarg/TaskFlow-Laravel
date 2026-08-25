<?php

/*
|--------------------------------------------------------------------------
| Pesan Validasi
|--------------------------------------------------------------------------
|
| Berkas ini sengaja TIDAK memuat seluruh aturan validasi Laravel, hanya yang
| benar-benar dipakai aplikasi ini. Aturan yang tidak ada di sini otomatis
| jatuh ke fallback_locale (en), jadi tidak ada pesan yang hilang -- lihat
| APP_FALLBACK_LOCALE di .env.
|
| Alasannya: berkas terjemahan penuh berisi ratusan aturan yang tidak pernah
| dipanggil hanya menambah beban baca tanpa memberi manfaat.
|
*/

return [
    'accepted' => ':attribute wajib disetujui.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal setelah atau sama dengan :date.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang sah.',
    'email' => ':attribute harus berupa alamat email yang sah.',
    'enum' => ':attribute yang dipilih tidak sah.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'in' => ':attribute yang dipilih tidak sah.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'lowercase' => ':attribute harus ditulis dengan huruf kecil.',
    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'array' => ':attribute harus berisi minimal :min item.',
        'file' => ':attribute harus minimal :min kilobyte.',
        'numeric' => ':attribute harus minimal :min.',
        'string' => ':attribute harus minimal :min karakter.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah terpakai.',

    // Dipakai Rules\Password::defaults() pada registrasi dan ubah kata sandi.
    'password' => [
        'letters' => ':attribute harus memuat setidaknya satu huruf.',
        'mixed' => ':attribute harus memuat huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus memuat setidaknya satu angka.',
        'symbols' => ':attribute harus memuat setidaknya satu simbol.',
        'uncompromised' => ':attribute pernah bocor dalam kebocoran data. Pilih kata sandi lain.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama Atribut
    |--------------------------------------------------------------------------
    |
    | Menggantikan nama kolom mentah pada pesan validasi, sehingga user membaca
    | "Judul wajib diisi." dan bukan "title wajib diisi.". Form Request tertentu
    | masih boleh menimpanya lewat method attributes() masing-masing.
    |
    */
    'attributes' => [
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'current_password' => 'Kata sandi saat ini',
        'title' => 'Judul',
        'description' => 'Deskripsi',
        'label' => 'Label',
        'priority' => 'Prioritas',
        'deadline' => 'Tenggat',
        'catatan' => 'Catatan',
        'role' => 'Role',
        'approver_id' => 'Approver',
    ],
];
