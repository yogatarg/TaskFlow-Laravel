<?php

namespace App\Http\Controllers;

use App\Enums\TaskLabel;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class TaskController extends Controller implements HasMiddleware
{
    /**
     * Otorisasi dipasang deklaratif lewat middleware `can:`, bukan dipanggil manual
     * di dalam tiap method. Bentuknya:
     *
     *   can:<ability>,<model>            -> untuk aksi yang belum punya objek (index, create)
     *   can:<ability>,<parameter route>  -> untuk aksi atas satu task tertentu
     *
     * Kata `task` di bawah merujuk ke parameter route {task}, sehingga Laravel
     * mengambil objek Task hasil route model binding lalu menyerahkannya ke TaskPolicy.
     *
     * Catatan: authorizeResource() TIDAK dipakai karena App\Http\Controllers\Controller
     * di Laravel 12 adalah kelas kosong -- tanpa trait AuthorizesRequests maupun
     * method middleware() yang dibutuhkan helper tersebut.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            new Middleware('can:viewAny,'.Task::class, only: ['index']),
            new Middleware('can:create,'.Task::class, only: ['create', 'store']),
            new Middleware('can:view,task', only: ['show']),
            new Middleware('can:update,task', only: ['edit', 'update']),
            new Middleware('can:delete,task', only: ['destroy']),
            new Middleware('can:restore,task', only: ['restore']),
        ];
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        // Hanya Admin yang boleh melihat arsip. Tanpa penjagaan ini, user biasa bisa
        // menambahkan ?arsip=1 di URL dan melihat kembali task yang sudah disingkirkan.
        $lihatArsip = $user->isAdmin() && $request->boolean('arsip');

        $tasks = Task::query()
            ->when($lihatArsip, fn ($q) => $q->onlyTrashed())
            // Admin melihat semua task; user lain hanya miliknya sendiri.
            ->when(! $user->isAdmin(), fn ($q) => $q->milik($user))
            ->with('creator')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('label'), fn ($q) => $q->where('label', $request->string('label')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'daftarStatus' => TaskStatus::cases(),
            'daftarLabel' => TaskLabel::cases(),
            'lihatArsip' => $lihatArsip,
        ]);
    }

    public function create(): View
    {
        return view('tasks.create', $this->opsiForm());
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        // create() lewat relasi tasks() otomatis mengisi created_by dengan id user
        // yang sedang login, jadi pemilik task tidak pernah datang dari input.
        // `status` tidak diisi di sini — biarkan default kolom, yaitu Draft.
        $task = $request->user()->tasks()->create($request->validated());

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', 'Task berhasil dibuat sebagai Draft.');
    }

    public function show(Task $task): View
    {
        // Riwayat approval ikut dimuat sekalian, lengkap dengan pelakunya, supaya
        // menampilkan sepuluh baris log tidak berubah jadi sebelas query (N+1).
        $task->load([
            'creator.approver',
            'approvalLogs' => fn ($q) => $q->kronologis()->with('actor'),
        ]);

        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task): View
    {
        return view('tasks.edit', ['task' => $task] + $this->opsiForm());
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', 'Perubahan tersimpan.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()
            ->route('tasks.index')
            ->with('status', 'Task disembunyikan. Riwayat approval-nya tetap tersimpan.');
    }

    /**
     * Memulihkan task yang disembunyikan. Parameter route memakai withTrashed()
     * di routes/web.php -- tanpa itu route model binding tidak akan pernah menemukan
     * task yang sudah terhapus, dan hasilnya 404 alih-alih pemulihan.
     */
    public function restore(Task $task): RedirectResponse
    {
        $task->restore();

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', 'Task dipulihkan.');
    }

    /**
     * @return array{daftarLabel: list<TaskLabel>, daftarPrioritas: list<TaskPriority>}
     */
    private function opsiForm(): array
    {
        return [
            'daftarLabel' => TaskLabel::cases(),
            'daftarPrioritas' => TaskPriority::cases(),
        ];
    }
}
