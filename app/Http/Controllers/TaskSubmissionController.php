<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Pembuat task mengajukan task-nya ke approver.
 *
 * Dibuat sebagai controller sendiri dengan satu method __invoke(), bukan ditambahkan
 * sebagai method ke-8 di TaskController. Alasannya: "mengajukan" bukan operasi CRUD --
 * ia tidak membuat, mengubah isi, atau menghapus apa pun. Ia menggerakkan task di
 * sepanjang state machine. Memisahkannya membuat TaskController tetap murni CRUD.
 */
class TaskSubmissionController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ApprovalService $approval)
    {
        // ApprovalService disuntikkan oleh service container Laravel -- tidak perlu
        // `new ApprovalService()` di sini. Efeknya: di test, service bisa diganti tiruan.
    }

    public static function middleware(): array
    {
        return [
            'auth',
            new Middleware('can:submit,task'),
        ];
    }

    public function __invoke(Request $request, Task $task): RedirectResponse
    {
        $this->approval->ajukan($task, $request->user());

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', 'Task diajukan ke '.$task->approver()->name.'. Menunggu keputusan.');
    }
}
