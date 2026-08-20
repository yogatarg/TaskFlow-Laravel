<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\ApprovalDecisionRequest;
use App\Models\Task;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Sisi approver: kotak masuk approval dan tiga keputusan yang bisa diambil.
 */
class ApprovalController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ApprovalService $approval) {}

    public static function middleware(): array
    {
        return [
            'auth',
            // Halaman inbox dibatasi per ROLE -- itu urusan middleware.
            new Middleware('role:Approver,Admin', only: ['index']),
            // Keputusan atas satu task dibatasi per OBJEK -- itu urusan Policy.
            new Middleware('can:decide,task', only: ['approve', 'reject', 'requestRevision']),
        ];
    }

    /**
     * Kotak masuk: task milik para bawahan yang sedang menunggu keputusan user ini.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $tasks = Task::query()
            ->untukApprover($user)
            ->berstatus(TaskStatus::PendingApproval)
            // Pembuat task ikut diambil sekalian supaya tidak terjadi N+1 query
            // saat namanya ditampilkan di tabel.
            ->with('creator')
            ->orderBy('deadline')
            ->paginate(10);

        return view('approvals.index', compact('tasks'));
    }

    public function approve(ApprovalDecisionRequest $request, Task $task): RedirectResponse
    {
        $this->approval->setujui($task, $request->user(), $request->catatan());

        return $this->kembali($task, 'Task disetujui.');
    }

    public function reject(ApprovalDecisionRequest $request, Task $task): RedirectResponse
    {
        $this->approval->tolak($task, $request->user(), $request->catatan());

        return $this->kembali($task, 'Task ditolak.');
    }

    public function requestRevision(ApprovalDecisionRequest $request, Task $task): RedirectResponse
    {
        $this->approval->mintaRevisi($task, $request->user(), $request->catatan());

        return $this->kembali($task, 'Revisi diminta. Task dikembalikan ke pembuatnya.');
    }

    private function kembali(Task $task, string $pesan): RedirectResponse
    {
        return redirect()
            ->route('approvals.index')
            ->with('status', $pesan.' ('.$task->title.')');
    }
}
