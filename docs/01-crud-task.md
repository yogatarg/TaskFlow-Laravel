# Membedah CRUD Task — Perjalanan Satu Request di Laravel

Dokumen ini membedah **Tahap 2 (CRUD Task)** dari sisi *bagaimana Laravel bekerja*, bukan dari sisi
arsitektur proyek. Sasarannya satu: setelah membaca ini, Anda bisa menunjuk baris mana yang jalan
lebih dulu dan kenapa, tanpa menebak.

Cara membacanya: buka file yang disebut di tiap bagian, jangan hanya membaca dokumen ini.

---

## 1. Satu baris route yang melahirkan tujuh route

Di `routes/web.php`:

```php
Route::resource('tasks', TaskController::class);
```

Satu baris itu mendaftarkan tujuh route sekaligus. Buktikan sendiri:

```
php artisan route:list --path=tasks
```

| Method | URI | Nama | Controller |
|---|---|---|---|
| GET | `tasks` | `tasks.index` | `index` |
| GET | `tasks/create` | `tasks.create` | `create` |
| POST | `tasks` | `tasks.store` | `store` |
| GET | `tasks/{task}` | `tasks.show` | `show` |
| GET | `tasks/{task}/edit` | `tasks.edit` | `edit` |
| PUT/PATCH | `tasks/{task}` | `tasks.update` | `update` |
| DELETE | `tasks/{task}` | `tasks.destroy` | `destroy` |

Perhatikan polanya: **URL yang sama dibedakan oleh HTTP method**. `GET /tasks` menampilkan daftar,
`POST /tasks` membuat data baru. `GET /tasks/5` menampilkan, `PUT /tasks/5` mengubah,
`DELETE /tasks/5` menghapus. Inilah kenapa `create` dan `edit` (yang hanya *menampilkan form*)
punya URL sendiri, terpisah dari `store` dan `update` (yang *memproses* form).

Karena route punya nama, di Blade Anda tidak pernah menulis URL secara literal:

```blade
<a href="{{ route('tasks.edit', $task) }}">Ubah</a>
```

`route('tasks.edit', $task)` menghasilkan `/tasks/5/edit`. Laravel tahu mengambil `id` dari objek
`$task` karena model punya `getRouteKey()` yang secara default mengembalikan primary key. Kalau
suatu hari URL-nya diubah jadi `/pekerjaan/5/edit`, semua Blade tidak perlu disentuh.

---

## 2. Perjalanan `PUT /tasks/5` dari awal sampai akhir

Ini bagian utamanya. Skenarionya: Sari sedang mengubah judul task miliknya.

### Langkah 0 — Form di browser

`resources/views/tasks/edit.blade.php`:

```blade
<form method="POST" action="{{ route('tasks.update', $task) }}" class="space-y-6">
    @csrf
    @method('PUT')
    @include('tasks._form', ['task' => $task])
    ...
</form>
```

Ada dua hal ganjil di situ, dan keduanya penting.

**Kenapa `method="POST"` padahal route-nya `PUT`?**

Form HTML hanya mengenal dua method: `GET` dan `POST`. Tidak ada browser yang bisa mengirim `PUT`
lewat form biasa. Solusi Laravel disebut *method spoofing*: `@method('PUT')` menghasilkan

```html
<input type="hidden" name="_method" value="PUT">
```

Laravel membaca field `_method` itu pada request POST, lalu memperlakukan request tersebut seolah
datang sebagai `PUT`. Kalau `@method('PUT')` dihapus, request masuk sebagai `POST /tasks/5` — dan
tidak ada route yang cocok, jadi Anda dapat **405 Method Not Allowed**. Coba hapus dan lihat sendiri.

**Apa gunanya `@csrf`?**

Menghasilkan `<input type="hidden" name="_token" value="...">`. Tanpa itu, request POST/PUT/DELETE
ditolak dengan **419 Page Expired**. Gunanya mencegah *Cross-Site Request Forgery*: situs jahat bisa
saja memasang form tersembunyi yang mengirim `DELETE /tasks/5` ke aplikasi Anda memakai cookie login
korban. Karena situs jahat itu tidak bisa membaca token dari session korban, requestnya gagal.

Ini juga alasan tombol Logout di Breeze berupa `<form>`, bukan `<a>` — link tidak bisa membawa token.

### Langkah 1 — Middleware grup `web`

Sebelum route dijalankan, request melewati antrean middleware. Urutannya bukan kebetulan;
Laravel menjamin urutan tertentu lewat `$middlewarePriority`
(lihat `vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php`):

```
EncryptCookies
  -> StartSession              (session dibuka)
  -> ShareErrorsFromSession    (variabel $errors disiapkan untuk SEMUA view)
  -> ValidateCsrfToken         (token _token diperiksa)
  -> SubstituteBindings        (route model binding dikerjakan di sini)
```

Dua di antaranya perlu Anda ingat baik-baik:

- **`ShareErrorsFromSession`** — inilah sebabnya `$errors` selalu ada di setiap Blade, bahkan di
  halaman yang tidak pernah memvalidasi apa pun. Anda tidak pernah mengirim `$errors` dari controller,
  tapi `resources/views/tasks/_form.blade.php` tetap bisa memakai `$errors->get('title')`.
- **`SubstituteBindings`** — mengubah `{task}` di URL menjadi objek `Task` sungguhan.

### Langkah 2 — Route model binding

Karena parameter route bernama `{task}` dan method controller-nya bertipe `Task $task`, Laravel
menjalankan `Task::findOrFail(5)` untuk Anda:

```php
public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
```

Konsekuensinya: **id yang tidak ada otomatis jadi 404**, tanpa satu baris pun kode dari Anda.
Buka `/tasks/999999` dan lihat.

Perhatikan juga apa yang *tidak* dilakukan binding: ia tidak peduli task itu milik siapa. Ia hanya
mengambil. Penjagaan kepemilikan ada di langkah berikutnya — dan urutan ini penting, karena Policy
butuh objeknya lebih dulu supaya ada yang bisa diperiksa.

### Langkah 3 — Middleware `can:` memanggil Policy

Di `app/Http/Controllers/TaskController.php`:

```php
public static function middleware(): array
{
    return [
        'auth',
        new Middleware('can:viewAny,'.Task::class, only: ['index']),
        new Middleware('can:create,'.Task::class, only: ['create', 'store']),
        new Middleware('can:view,task',   only: ['show']),
        new Middleware('can:update,task', only: ['edit', 'update']),
        new Middleware('can:delete,task', only: ['destroy']),
    ];
}
```

Bedakan dua bentuk argumen kedua:

- `can:create,App\Models\Task` — nama **kelas**. Dipakai untuk aksi yang belum punya objek
  (belum ada task yang mau dibuat). Policy menerima `create(User $user)` saja.
- `can:update,task` — nama **parameter route**. Laravel mengambil objek hasil binding di langkah 2,
  lalu memanggil `TaskPolicy::update($user, $task)`.

Kalau Policy mengembalikan `false`, Laravel melempar `AuthorizationException` → **403**, dan
**method `update()` di controller tidak pernah dijalankan sama sekali**. Ini yang membuat pengecekan
izin sulit terlupa: ia tidak bergantung pada Anda ingat menulis `if` di dalam controller.

Isi aturannya di `app/Policies/TaskPolicy.php`:

```php
public function update(User $user, Task $task): bool
{
    return $this->pemilikAtauAdmin($user, $task)
        && $task->status->isEditable();
}
```

Perhatikan Policy tidak menyimpan aturan status sendiri — ia bertanya ke `TaskStatus::isEditable()`.

> **Catatan Laravel 12:** helper `authorizeResource()` yang banyak dijumpai di tutorial lama
> **tidak bisa dipakai** di sini. `App\Http\Controllers\Controller` pada skeleton Laravel 12 adalah
> kelas kosong — tanpa trait `AuthorizesRequests` dan tanpa method `middleware()` yang dibutuhkan
> helper itu. Interface `HasMiddleware` di atas adalah penggantinya.

### Langkah 4 — Form Request divalidasi otomatis

```php
public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
```

Anda tidak pernah memanggil `validate()`. Yang terjadi: saat Laravel menyiapkan argumen untuk method
`update()`, ia melihat tipe `UpdateTaskRequest` dan membuat objeknya lewat service container. Dalam
proses pembuatan itulah `rules()` dijalankan.

Kalau validasi **gagal**, `ValidationException` dilempar dan Laravel otomatis:

1. redirect kembali ke halaman sebelumnya,
2. menyimpan seluruh input lama ke session (*flash*), supaya `old()` bisa membacanya,
3. menyimpan pesan error ke session, yang lalu dibagikan sebagai `$errors` oleh `ShareErrorsFromSession`.

Dan sekali lagi: **badan method `update()` tidak pernah tersentuh**. Controller Anda selalu bekerja
dengan data yang sudah pasti valid. Itu sebabnya `TaskController::update()` hanya dua baris.

Lihat `app/Http/Requests/StoreTaskRequest.php`. Yang paling penting justru apa yang **tidak** ada di
sana: tidak ada aturan untuk `status` maupun `created_by`, karena dua field itu memang tidak diterima
dari input sama sekali.

### Langkah 5 — `validated()` vs `all()`

```php
$task->update($request->validated());
```

`$request->validated()` hanya mengembalikan field yang **punya aturan dan lolos**. Kalau penyerang
menambahkan `status=Approved` ke form, field itu tidak ada di `rules()`, jadi tidak ikut keluar dari
`validated()`.

Ini penyaring pertama. Ada penyaring kedua di `app/Models/Task.php`:

```php
protected $fillable = [
    'title', 'description', 'label', 'priority', 'deadline',
];
```

`update()` dan `create()` hanya mengisi kolom yang terdaftar di `$fillable`. `status` dan `created_by`
sengaja tidak ada di situ, jadi keduanya mustahil diubah lewat *mass assignment* — dari form mana pun,
di controller mana pun.

Kenapa perlu dua lapis kalau satu saja sudah cukup? Karena keduanya bisa gagal dengan cara berbeda.
Suatu hari ada yang menulis `$task->update($request->all())` — `validated()` terlewat, tapi `$fillable`
masih menjaga. Sebaliknya, `$fillable` bisa saja kelupaan diperbarui saat kolom baru ditambah, dan
`validated()` masih menyaring. Ini dibuktikan oleh dua test di `tests/Feature/TaskCrudTest.php`:
`test_status_tidak_bisa_disuntikkan_lewat_form` dan `test_pembuat_task_tidak_bisa_dipalsukan_lewat_form`.

Bandingkan dengan `store()`, yang memakai trik berbeda untuk `created_by`:

```php
$task = $request->user()->tasks()->create($request->validated());
```

Karena `create()` dipanggil lewat relasi `tasks()`, Eloquent otomatis mengisi `created_by` dengan id
user yang sedang login. Pemilik task tidak pernah datang dari input — ia datang dari session.

### Langkah 6 — Cast enum bekerja dua arah

Di `app/Models/Task.php`:

```php
protected function casts(): array
{
    return [
        'label' => TaskLabel::class,
        'priority' => TaskPriority::class,
        'status' => TaskStatus::class,
        'deadline' => 'date',
    ];
}
```

Efeknya:

- **Saat menyimpan:** `TaskLabel::Harian` diubah jadi string `'Harian'` untuk kolom database.
- **Saat membaca:** string `'Harian'` diubah jadi objek `TaskLabel::Harian`.

Karena itu di Blade Anda bisa menulis `$task->status->badgeClass()` — `$task->status` bukan string,
melainkan objek enum yang punya method. Dan `$task->deadline` bukan string melainkan objek Carbon,
sehingga `$task->deadline->translatedFormat('d F Y')` bisa langsung dipakai.

Ini juga menjelaskan kenapa di test perbandingannya `assertSame(TaskStatus::Draft, $task->status)`
dan bukan `assertSame('Draft', $task->status)`.

### Langkah 7 — Redirect dan session flash

```php
return redirect()
    ->route('tasks.show', $task)
    ->with('status', 'Perubahan tersimpan.');
```

Controller **tidak** mengembalikan view. Ia mengembalikan redirect. Polanya disebut
*POST/Redirect/GET*: setelah data berubah, selalu redirect. Kalau controller langsung merender view,
menekan F5 akan mengirim ulang request PUT-nya dan perubahan diterapkan dua kali.

`->with('status', ...)` menaruh pesan di session sebagai **flash data** — hidup untuk satu request
berikutnya saja, lalu hilang sendiri. Yang membacanya `resources/views/components/flash.blade.php`:

```blade
@if (session('status'))
    <div class="...">{{ session('status') }}</div>
@endif
```

Karena itu pesan "Perubahan tersimpan." muncul sekali, dan hilang saat halaman di-refresh.

### Ringkasan urutannya

```
Browser  POST /tasks/5 (+ _method=PUT, + _token)
   |
   |-- StartSession
   |-- ShareErrorsFromSession   -> $errors siap untuk semua view
   |-- ValidateCsrfToken        -> gagal? 419
   |-- SubstituteBindings       -> {task} jadi objek Task, tidak ada? 404
   |-- auth                     -> belum login? redirect ke /login
   |-- can:update,task          -> TaskPolicy::update() false? 403   <- controller belum jalan
   |
   |-- UpdateTaskRequest         -> validasi gagal? redirect + old() + $errors  <- controller belum jalan
   |
   |-- TaskController::update()  -> validated() -> $fillable -> cast enum -> UPDATE
   |
   `-- redirect ke tasks.show + flash 'status'
```

Perhatikan berapa banyak yang sudah tersaring **sebelum** baris pertama controller Anda dijalankan.
Itulah kenapa controller-controller di proyek ini pendek-pendek: pekerjaan penjagaan sudah dikerjakan
lapisan lain yang lebih cocok untuk itu.

---

## 3. Sisi baca: `GET /tasks`

`TaskController::index()` memakai **query builder yang disusun bertahap**:

```php
$tasks = Task::query()
    ->when(! $user->isAdmin(), fn ($q) => $q->milik($user))
    ->with('creator')
    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
    ->when($request->filled('label'),  fn ($q) => $q->where('label',  $request->string('label')))
    ->latest()
    ->paginate(10)
    ->withQueryString();
```

Empat hal yang layak diperhatikan:

**`when()`** menjalankan closure hanya kalau syaratnya benar. Ini pengganti rantai `if` yang memotong
alur pemanggilan berantai. Query baru benar-benar dikirim ke database saat `paginate()` dipanggil —
sebelum itu Anda hanya sedang menyusun rencana.

**`milik($user)`** adalah *local scope*, didefinisikan di `app/Models/Task.php` sebagai
`scopeMilik(Builder $query, User $user)`. Laravel membuang awalan `scope` saat dipanggil. Gunanya:
aturan "task milik siapa" ditulis sekali di model, bukan diulang di setiap controller.

**`with('creator')`** mencegah masalah **N+1 query**. Tanpa itu, tabel dengan 10 baris akan menjalankan
1 query untuk daftar task plus 10 query untuk mengambil nama pembuat masing-masing. Buktikan sendiri:
tambahkan `\DB::enableQueryLog();` di awal method dan `dd(\DB::getQueryLog());` di akhirnya, lalu coba
dengan dan tanpa `with('creator')`.

**`withQueryString()`** membuat link paginasi tetap membawa filter yang aktif. Tanpa itu, pindah ke
halaman 2 akan menghapus filter status yang barusan Anda pilih.

---

## 4. Tiga hal yang sering membingungkan

### `old()` vs `$errors`

Keduanya dibaca dari session, tapi isinya beda:

- `old('title')` — **nilai** yang tadi diketik user, supaya form tidak kosong lagi setelah validasi gagal.
- `$errors->get('title')` — **pesan kesalahan** untuk field itu.

Di `_form.blade.php` keduanya dipakai berdampingan:

```blade
<x-text-input name="title" :value="old('title', $task?->title)" />
<x-input-error :messages="$errors->get('title')" />
```

Argumen kedua `old()` adalah nilai cadangan: dipakai kalau tidak ada input lama di session — yaitu saat
halaman dibuka pertama kali, bukan setelah validasi gagal.

### `??` tidak menyelamatkan pemanggilan method

Ini bug nyata yang sempat terjadi di proyek ini. Bentuk awalnya:

```blade
{{-- SALAH --}}
:value="old('deadline', $task->deadline?->format('Y-m-d') ?? '')"
```

Terlihat aman, tapi halaman *create* error dengan **"Undefined variable $task"**. Sebabnya: operator
`??` memakai semantik `isset()`, yang hanya memaafkan variabel atau properti yang belum ada. Begitu ada
**pemanggilan method** di rantainya (`->format(...)`), PHP harus mengevaluasi `$task` sungguhan lebih
dulu — dan `$task` memang tidak ada di halaman create.

Perbaikannya: kirim `$task` secara eksplisit dan pakai nullsafe di seluruh partial.

```blade
{{-- create.blade.php --}}  @include('tasks._form', ['task' => null])
{{-- edit.blade.php   --}}  @include('tasks._form', ['task' => $task])
{{-- _form.blade.php  --}}  :value="old('deadline', $task?->deadline?->format('Y-m-d'))"
```

Pelajarannya bukan soal sintaks, tapi soal kebiasaan: **partial yang dipakai bersama sebaiknya menerima
semua variabelnya secara eksplisit**, jangan mengandalkan variabel yang "kebetulan ada" di view pemanggil.

### `@can` di Blade dan `can:` di middleware

Keduanya memanggil Policy yang sama, tapi tujuannya berbeda:

```blade
@can('update', $task)
    <a href="{{ route('tasks.edit', $task) }}">Ubah</a>
@endcan
```

`@can` di Blade hanya **menyembunyikan tombol** — itu urusan tampilan, bukan keamanan. Orang tetap bisa
mengetik URL-nya langsung. Yang benar-benar menjaga adalah middleware `can:update,task`.

Jadi `@can` bukan pengganti penjagaan, melainkan pelengkapnya: penjagaan supaya tidak bisa ditembus,
`@can` supaya user tidak melihat tombol yang pasti gagal.

---

## 5. Membuktikan sendiri

Cara tercepat memastikan Anda benar-benar paham: rusak sesuatu dengan sengaja, tebak akibatnya, lalu
lihat apakah tebakan Anda benar. Jalankan `php artisan test` setelah tiap percobaan, dan kembalikan
lagi setelahnya.

| Percobaan | Tebak dulu, lalu buktikan |
|---|---|
| Hapus `@method('PUT')` dari `edit.blade.php` | 405 Method Not Allowed |
| Hapus `@csrf` | 419 Page Expired |
| Tambahkan `'status'` ke `$fillable` di `Task` | test `status_tidak_bisa_disuntikkan` gagal |
| Ubah `isEditable()` agar `PendingApproval` ikut `true` | dua test status gagal — sekaligus menunjukkan bahwa aturan itu memang tinggal di satu tempat |
| Hapus `with('creator')` lalu aktifkan query log | jumlah query melonjak (N+1) |
| Buka `/tasks/999999` | 404 dari route model binding |
| Login sebagai Rina, buka URL task milik Sari | 403 dari `TaskPolicy::view()` |

---

## 6. Peta file Tahap 2

```
routes/web.php                              Route::resource -> 7 route

app/Http/Controllers/TaskController.php     alur; menentukan middleware can: sendiri
app/Http/Requests/StoreTaskRequest.php      aturan validasi
app/Http/Requests/UpdateTaskRequest.php     mewarisi Store; tempat aturan ubah kalau nanti berbeda
app/Policies/TaskPolicy.php                 boleh apa terhadap task ITU

app/Models/Task.php                         $fillable, cast, relasi, scope
app/Enums/TaskStatus.php                    state machine + isEditable()
app/Enums/TaskLabel.php                     Harian | Proposal | Meeting
app/Enums/TaskPriority.php                  Rendah | Sedang | Tinggi

resources/views/tasks/index.blade.php       daftar + filter
resources/views/tasks/create.blade.php      form baru      -> _form dengan task = null
resources/views/tasks/edit.blade.php        form ubah      -> _form dengan task = $task
resources/views/tasks/_form.blade.php       field bersama
resources/views/tasks/show.blade.php        detail + tombol ber-@can
resources/views/components/badge.blade.php  komponen kecil
resources/views/components/flash.blade.php  pembaca session flash

database/migrations/*_create_tasks_table.php
database/factories/TaskFactory.php          state per status untuk test
database/seeders/TaskSeeder.php             5 task contoh, semuanya Draft
tests/Feature/TaskCrudTest.php              22 test
```

---

## Satu kalimat penutup

Yang membuat CRUD ini bukan sekadar "form yang menyimpan ke database" adalah pembagian tugasnya:
**route** menentukan apa yang ada, **middleware** menentukan siapa yang boleh, **Form Request**
menentukan data apa yang sah, **model** menentukan field apa yang boleh diisi, **enum** menentukan
aturan domainnya, dan **controller** hanya merangkai. Begitu satu tanggung jawab bocor ke lapisan yang
salah — misalnya aturan status ditulis ulang di dalam controller — sistemnya mulai bisa berbeda
pendapat dengan dirinya sendiri.
