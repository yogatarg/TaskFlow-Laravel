<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalAction;
use App\Enums\TaskStatus;
use App\Models\ApprovalLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\View\View;

/**
 * Dashboard bercabang per role.
 *
 * Satu route, satu controller, tiga view berbeda. Alternatifnya membuat tiga route
 * terpisah, tapi itu memaksa user tahu URL mana yang cocok untuk dirinya -- padahal
 * yang dia mau cuma "beranda". Percabangannya di sini, URL-nya tetap satu.
 */
class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth'];
    }

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return match (true) {
            $user->isAdmin() => $this->admin(),
            $user->isApprover() => $this->approver($user),
            default => $this->user($user),
        };
    }

    // ------------------------------------------------------------------ User

    private function user(User $user): View
    {
        /*
         * Satu query untuk semua hitungan status, bukan lima query terpisah.
         * selectRaw + count(case when ...) memindahkan pekerjaan menghitung ke
         * database, yang jauh lebih murah daripada menarik semua baris ke PHP.
         */
        $hitungan = Task::query()
            ->milik($user)
            ->selectRaw('count(*) as total')
            ->selectRaw('count(case when status = ? then 1 end) as pending', [TaskStatus::PendingApproval->value])
            ->selectRaw('count(case when status in (?, ?) then 1 end) as dikerjakan', [
                TaskStatus::Draft->value,
                TaskStatus::RevisionRequested->value,
            ])
            ->selectRaw('count(case when status = ? then 1 end) as selesai', [TaskStatus::Approved->value])
            ->selectRaw('count(case when status = ? then 1 end) as ditolak', [TaskStatus::Rejected->value])
            ->first();

        return view('dashboard.user', [
            'hitungan' => $hitungan,

            // Mendekati tenggat: 7 hari ke depan, plus yang sudah lewat tapi belum
            // selesai. Task yang sudah Approved tidak perlu diingatkan lagi.
            'mendekatiTenggat' => Task::query()
                ->milik($user)
                ->whereNotNull('deadline')
                ->whereNotIn('status', [TaskStatus::Approved->value, TaskStatus::Rejected->value])
                ->whereDate('deadline', '<=', now()->addWeek())
                ->orderBy('deadline')
                ->limit(5)
                ->get(),

            'aktivitasTerbaru' => ApprovalLog::query()
                ->whereHas('task', fn ($q) => $q->where('created_by', $user->id))
                ->with(['task:id,title', 'actor:id,name'])
                ->terbaru()
                ->limit(5)
                ->get(),
        ]);
    }

    // -------------------------------------------------------------- Approver

    private function approver(User $user): View
    {
        return view('dashboard.approver', [
            'menunggu' => Task::query()
                ->untukApprover($user)
                ->berstatus(TaskStatus::PendingApproval)
                ->count(),

            // "Disetujui hari ini" dihitung dari LOG, bukan dari status task.
            // Status hanya menyimpan keadaan sekarang; log menyimpan kapan
            // keputusannya diambil -- dan itulah yang ditanyakan di sini.
            'disetujuiHariIni' => ApprovalLog::query()
                ->where('actor_id', $user->id)
                ->where('action', ApprovalAction::Approve->value)
                ->whereDate('timestamp', today())
                ->count(),

            'riwayat' => ApprovalLog::query()
                ->where('actor_id', $user->id)
                ->with(['task:id,title', 'actor:id,name'])
                ->terbaru()
                ->limit(10)
                ->get(),

            // Task milik bawahan yang tenggatnya sudah dekat tapi belum diajukan --
            // bahan untuk mengingatkan, bukan untuk diputuskan.
            'perluDitagih' => Task::query()
                ->untukApprover($user)
                ->berstatus(...TaskStatus::diTanganPembuat())
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<=', now()->addWeek())
                ->with('creator:id,name')
                ->orderBy('deadline')
                ->limit(5)
                ->get(),
        ]);
    }

    // ----------------------------------------------------------------- Admin

    private function admin(): View
    {
        return view('dashboard.admin', [
            'totalUser' => User::count(),
            'totalTask' => Task::count(),
            'menungguSemua' => Task::berstatus(TaskStatus::PendingApproval)->count(),
            'tanpaApprover' => User::whereNull('approver_id')->count(),

            'aktivitasTerbaru' => ApprovalLog::query()
                ->with(['task:id,title', 'actor:id,name'])
                ->terbaru()
                ->limit(10)
                ->get(),

            'grafikBulanan' => $this->taskPerBulan(),
        ]);
    }

    /**
     * Jumlah task per bulan untuk 12 bulan terakhir, termasuk bulan yang kosong.
     *
     * Pengelompokannya dilakukan di PHP, bukan lewat DATE_TRUNC / to_char di SQL.
     * Alasannya portabilitas: fungsi tanggal berbeda antar database, dan test
     * berjalan di SQLite sementara produksi memakai PostgreSQL. Rentangnya dibatasi
     * 12 bulan sehingga jumlah baris yang ditarik tetap wajar.
     *
     * Kalau suatu saat jumlah task menjadi sangat besar, agregasi ini yang pertama
     * perlu dipindahkan ke SQL.
     *
     * @return array<string, int> ['Sep 2025' => 4, ...]
     */
    private function taskPerBulan(): array
    {
        $mulai = now()->startOfMonth()->subMonths(11);

        // Siapkan dulu 12 bulan berisi nol, supaya bulan tanpa task tetap muncul
        // sebagai batang kosong -- bukan hilang dan membuat grafiknya berbohong.
        $bulan = [];
        for ($i = 0; $i < 12; $i++) {
            $bulan[$mulai->copy()->addMonths($i)->translatedFormat('M Y')] = 0;
        }

        Task::query()
            ->where('created_at', '>=', $mulai)
            ->get(['created_at'])
            ->each(function (Task $task) use (&$bulan) {
                $kunci = $task->created_at->translatedFormat('M Y');

                if (array_key_exists($kunci, $bulan)) {
                    $bulan[$kunci]++;
                }
            });

        return $bulan;
    }
}
