# TaskFlow

Sistem manajemen task organisasi dengan alur approval bertingkat. Dibangun sebagai proyek
portofolio dengan fokus pada **kedalaman pemahaman, bukan luasnya fitur** — setiap keputusan
desain di sini diambil sadar dan bisa dijelaskan alasannya.

[![CI](https://github.com/yogatarg/TaskFlow-Laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/yogatarg/TaskFlow-Laravel/actions/workflows/ci.yml)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Neon-4169E1?logo=postgresql&logoColor=white)

---

## Tentang

Seorang pegawai membuat task, mengajukannya ke atasannya, lalu atasan itu menyetujui,
menolak, atau meminta revisi. Setiap keputusan tercatat permanen. Sesederhana itu — dan
justru karena sederhana, seluruh perhatian bisa diarahkan ke hal-hal yang biasanya
dilewati: siapa boleh melakukan apa, kapan data boleh berubah, dan bagaimana memastikan
riwayatnya tidak bisa dibengkokkan.

Antarmukanya memakai **Blade dan Controller murni** — tanpa Livewire, Inertia, atau React.
Ini pilihan sadar: lapisan abstraksi tambahan menyamarkan fundamental Laravel yang justru
ingin dipahami di proyek ini.

## Peran dan wewenang

| Peran | Wewenang |
|---|---|
| **User** | Membuat, mengelola, dan mengajukan task miliknya sendiri |
| **Approver** | Semua wewenang User, ditambah menyetujui/menolak/meminta revisi task yang diajukan **kepadanya** |
| **Admin** | Mengelola user dan role, menetapkan approver tiap user, melihat seluruh task dan log |

Approver ditentukan **per user**, bukan per task. Task yang dibuat Sari otomatis diajukan
ke approver Sari. Kalau Admin mengganti approver Sari, task lama tidak perlu disentuh —
dan catatan siapa yang dulu memproses tetap utuh di log.

## Alur approval

```mermaid
stateDiagram-v2
    [*] --> Draft
    Draft --> PendingApproval: submit
    PendingApproval --> Approved: approve
    PendingApproval --> Rejected: reject
    PendingApproval --> RevisionRequested: request revision
    RevisionRequested --> PendingApproval: perbaiki lalu submit lagi
    Approved --> [*]
    Rejected --> [*]

    PendingApproval: Pending Approval
    RevisionRequested: Revision Requested
```

Aturan yang menyertainya:

- **`Approved` dan `Rejected` bersifat final.** Task yang ditolak dan ingin diajukan ulang
  dibuat sebagai task baru, bukan dihidupkan kembali.
- **Task yang sedang `Pending Approval` tidak bisa diedit.** Approver sedang menilai isinya,
  jadi isinya dibekukan. Perbaikan dilakukan setelah approver mengembalikannya lewat
  *request revision*.
- **Menolak dan meminta revisi wajib menyertakan catatan.** Tanpa itu pembuat task tidak
  tahu apa yang harus diperbaiki.

## Keputusan desain

Bagian ini yang paling layak dibaca kalau Anda menilai proyek ini.

### State machine tinggal di satu tempat

Aturan "boleh pindah ke status mana" ada di `app/Enums/TaskStatus.php`, bukan tersebar di
controller. Policy dan `ApprovalService` sama-sama bertanya ke sana, sehingga keduanya
mustahil berbeda pendapat.

### Tiga lapis penjagaan yang tugasnya berbeda

Ketiganya sering dikira saling menggantikan, padahal masing-masing menutup celah yang
berbeda:

| Lapis | Menjawab | Contoh |
|---|---|---|
| Middleware | "Role apa yang boleh masuk halaman ini?" | `role:Admin` pada grup route admin |
| Policy | "User ini boleh apa terhadap **task itu**?" | `TaskPolicy::update()` |
| `$fillable` | "Field apa yang boleh datang dari input?" | `status` dan `created_by` diblokir |

`role:Admin` tidak bisa mencegah Admin A mengubah task Admin B. `TaskPolicy` tidak bisa
mencegah `status=Approved` diselipkan ke form. Karena itu ketiganya dipakai bersama, dan
masing-masing punya test yang membuktikannya.

### Log dan perubahan status tidak bisa terpisah

`ApprovalService` menulis `ApprovalLog` di dalam transaksi yang **sama** dengan perubahan
status. Kalau penulisan log gagal, perubahan status ikut dibatalkan. Alternatifnya adalah
task yang tiba-tiba `Approved` tanpa ada seorang pun tercatat menyetujuinya — dan riwayat
yang bohong lebih berbahaya daripada aksi yang gagal lalu diulang.

Ada test yang menjatuhkan tabel log lalu memastikan status benar-benar ter-*rollback*.

### Log bersifat append-only, dan itu ditegakkan

Tidak ada route, controller, atau form yang bisa mengubah maupun menghapus `ApprovalLog`.
Selain itu model memasang penjaga pada event `updating` dan `deleting` yang melempar
exception. User yang pernah memproses approval juga tidak bisa menghapus akunnya —
`approval_logs.actor_id` memakai `restrictOnDelete`, dan `ProfileController` memberi pesan
yang jelas alih-alih membiarkannya jadi error 500.

Log yang bisa dirapikan belakangan bukan log.

### Anti klik-ganda pada keputusan approval

Perpindahan status memakai `lockForUpdate()` disertai pembacaan ulang status di dalam
transaksi. Tanpa itu, dua request yang datang hampir bersamaan — misalnya tombol "Setujui"
ditekan dua kali — bisa sama-sama lolos pemeriksaan dan sama-sama menulis, meninggalkan dua
baris log untuk satu keputusan.

Perilaku ini tidak bisa diverifikasi lewat test otomatis karena SQLite mengabaikan row
locking; sudah diuji manual terhadap PostgreSQL.

## Stack

- **Laravel 12** (PHP 8.4+)
- **Blade + Controller murni** — tanpa Livewire/Inertia/React
- **Laravel Breeze** (stack Blade) untuk autentikasi
- **PostgreSQL** (Neon) via Eloquent
- **Tailwind CSS + Vite**
- **PHPUnit** — 130 test

## Menjalankan secara lokal

### Prasyarat

- PHP 8.4+ dengan ekstensi **`pdo_pgsql`** dan **`pgsql`** aktif
- Composer dan Node.js
- Sebuah database PostgreSQL (proyek ini memakai [Neon](https://neon.tech), tapi PostgreSQL
  lokal juga bisa)

> Di Laragon/XAMPP, dua ekstensi PostgreSQL biasanya masih dinonaktifkan. Hapus tanda `;`
> pada baris `extension=pdo_pgsql` dan `extension=pgsql` di `php.ini`, lalu restart.
> Periksa dengan `php -m | grep pgsql`.

### Langkah

```bash
git clone https://github.com/yogatarg/TaskFlow-Laravel.git
cd TaskFlow-Laravel

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Isi `DB_URL` di `.env` dengan connection string PostgreSQL Anda:

```dotenv
DB_CONNECTION=pgsql
DB_URL=postgresql://user:password@host/namadb
DB_SSLMODE=require
```

> **Pengguna Neon:** pakai endpoint **direct**, yaitu host **tanpa** `-pooler`. Endpoint
> pooler memakai PgBouncer dalam *transaction mode*, sementara Laravel membungkus tiap
> migration PostgreSQL dalam satu transaksi — akibatnya migrasi gagal dengan
> `SQLSTATE 25P02`.

Lalu:

```bash
php artisan migrate --seed
npm run dev          # terminal 1
php artisan serve    # terminal 2
```

Buka http://localhost:8000.

### Akun demo

Seeder membuat empat akun. Password semuanya `password`.

| Email | Peran | Approver-nya |
|---|---|---|
| `admin@taskflow.test` | Admin | — |
| `approver@taskflow.test` | Approver | Admin TaskFlow |
| `sari@taskflow.test` | User | Budi Approver |
| `rina@taskflow.test` | User | Budi Approver |

Perhatikan bahwa Approver pun punya atasan: task miliknya sendiri diajukan ke Admin.

### Mencoba alurnya

1. Login sebagai **Sari** → buat task → buka detailnya → **Ajukan ke Approver**
2. Login sebagai **Budi** → menu **Approval** → tinjau → coba **Minta Revisi** tanpa
   mengisi catatan (harus ditolak), lalu isi catatannya
3. Login sebagai **Sari** lagi → task bisa diedit kembali → ajukan ulang
4. Login sebagai **Budi** → **Setujui**. Task terkunci sejak saat itu.
5. Login sebagai **Admin** → menu **Log** untuk melihat seluruh jejaknya

## Pengujian

```bash
php artisan test
```

130 test berjalan di SQLite in-memory, jadi tidak menyentuh database pengembangan Anda.
Seluruhnya dijalankan otomatis di GitHub Actions pada setiap push dan pull request ke `main`
— di PHP 8.4 dan 8.5 — bersama pemeriksaan gaya penulisan Laravel Pint.

```bash
./vendor/bin/pint          # rapikan gaya penulisan
./vendor/bin/pint --test   # hanya periksa, sama seperti yang dijalankan CI
```

| Berkas | Test | Cakupan |
|---|---:|---|
| `TaskCrudTest` | 22 | CRUD, kepemilikan, penguncian per status |
| `ApprovalFlowTest` | 25 | Pengajuan, keputusan, siklus revisi penuh |
| `ApprovalLogTest` | 17 | Pencatatan, append-only, integritas transaksi |
| `DashboardTest` | 16 | Hitungan dan pemetaan status per peran |
| `Admin/UserManagementTest` | 12 | Pengelolaan role dan approver |
| `Unit/TaskStatusTest` | 13 | State machine sebagai logika murni, tanpa database |

Sisanya bawaan Breeze (autentikasi dan profil).

## Struktur proyek

```
app/
  Enums/            Role, TaskStatus (state machine), TaskLabel,
                    TaskPriority, ApprovalAction
  Models/           User, Task, ApprovalLog
  Policies/         TaskPolicy — izin per objek
  Services/         ApprovalService — satu-satunya pintu perpindahan status
  Exceptions/       TransisiTidakSah
  Http/
    Controllers/    Dashboard, Task, TaskSubmission, Approval,
                    Admin\User, Admin\ApprovalLog
    Requests/       StoreTask, UpdateTask, ApprovalDecision, UpdateUser
    Middleware/     EnsureUserHasRole — izin per role

resources/views/    dashboard/, tasks/, approvals/, admin/, components/
database/           migrations, factories, seeders
docs/               catatan teknis
tests/Feature/      117 test
```

## Dokumentasi

- **[`CLAUDE.md`](CLAUDE.md)** — spesifikasi teknis: skema data, state machine, dan urutan
  pengembangan yang diikuti
- **[`docs/01-crud-task.md`](docs/01-crud-task.md)** — pembedahan satu request `PUT /tasks/5`
  dari form di browser sampai flash message: urutan middleware, route model binding,
  Form Request, `$fillable`, cast enum, dan pola POST/Redirect/GET

## Status pengembangan

Kelima tahap dalam spesifikasi sudah selesai, dikerjakan berurutan dan masing-masing menjadi
satu commit tersendiri:

- [x] Authentication & Role
- [x] CRUD Task
- [x] Approval Flow
- [x] Activity Log
- [x] Dashboard per peran

Yang belum ada dan bukan bagian dari spesifikasi: notifikasi ke approver saat ada task
masuk, ekspor laporan, dan approval berjenjang lebih dari satu tingkat.

## Catatan

Proyek ini sempat dirintis dengan Next.js + Prisma + NextAuth sebelum dibangun ulang dengan
Laravel. Alasan pindah: fokus belajar saat itu adalah mendalami Laravel, dan lapisan React
justru mengaburkan fundamental yang ingin dipahami.
