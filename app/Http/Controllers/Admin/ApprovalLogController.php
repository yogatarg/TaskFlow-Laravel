<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalAction;
use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Log approval seluruh organisasi, khusus Admin.
 *
 * Hanya ada index -- tidak ada create, edit, maupun destroy. Itu bukan kelalaian:
 * ApprovalLog append-only, jadi memang tidak ada operasi lain yang sah terhadapnya.
 */
class ApprovalLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ApprovalLog::query()
            // Task dan pelakunya dimuat sekalian supaya tabel 20 baris tidak
            // berubah jadi 41 query.
            ->with(['task:id,title', 'actor:id,name'])
            ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->integer('actor_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('cari'), function ($q) use ($request) {
                $kata = $request->string('cari')->toString();

                $q->whereHas('task', fn ($t) => $t->where('title', 'like', '%'.$kata.'%'));
            })
            ->terbaru()
            ->paginate(20)
            ->withQueryString();

        return view('admin.logs.index', [
            'logs' => $logs,
            'daftarAksi' => ApprovalAction::cases(),
            // Hanya user yang pernah benar-benar memproses approval yang perlu
            // muncul di filter; sisanya cuma bikin dropdown panjang tanpa guna.
            'daftarPelaku' => User::query()
                ->whereHas('approvalLogs')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
