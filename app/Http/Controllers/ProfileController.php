<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        /*
         * Penjagaan integritas riwayat.
         *
         * Dua foreign key memakai restrictOnDelete: users.approver_id (tahap 1) dan
         * approval_logs.actor_id (tahap 4). Tanpa penjagaan di sini, $user->delete()
         * di bawah akan melempar QueryException dan user melihat halaman error 500 --
         * bukan pesan yang bisa dimengerti.
         *
         * Riwayat approval sengaja dipertahankan: catatan "siapa menyetujui apa"
         * kehilangan artinya kalau pelakunya bisa menghapus jejaknya sendiri.
         */
        if ($alasan = $this->alasanTidakBisaDihapus($user)) {
            throw ValidationException::withMessages(['password' => $alasan])
                ->errorBag('userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Alasan kenapa akun ini tidak boleh dihapus, atau null kalau boleh.
     */
    private function alasanTidakBisaDihapus(User $user): ?string
    {
        if ($user->approvalLogs()->exists()) {
            return 'Akun ini tidak bisa dihapus karena pernah memproses approval. '
                .'Riwayat approval harus tetap bisa ditelusuri.';
        }

        if ($user->approvees()->exists()) {
            return 'Akun ini masih menjadi approver bagi user lain. '
                .'Minta Admin memindahkan bawahannya terlebih dahulu.';
        }

        return null;
    }
}
