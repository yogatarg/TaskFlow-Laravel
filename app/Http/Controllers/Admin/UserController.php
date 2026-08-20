<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Pengelolaan user oleh Admin: mengubah role dan menentukan approver tiap user.
 *
 * Sengaja hanya index/edit/update. Pembuatan user tetap lewat halaman registrasi,
 * dan penghapusan user belum dibuka karena akan menyangkut task & log yang sudah
 * terlanjur dibuat user tersebut (dibahas di tahap Activity Log).
 */
class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with('approver')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user): View
    {
        // Kandidat approver: siapa pun yang role-nya Approver/Admin, kecuali dirinya sendiri.
        $kandidatApprover = User::query()
            ->whereIn('role', [Role::Approver, Role::Admin])
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->get();

        return view('admin.users.edit', [
            'user' => $user,
            'kandidatApprover' => $kandidatApprover,
            'roles' => Role::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // `role` dan `approver_id` tidak ada di $fillable model User, jadi tidak bisa
        // di-mass-assign. Di sinilah satu-satunya tempat keduanya boleh diubah.
        $user->role = Role::from($request->string('role')->toString());
        $user->approver_id = $request->filled('approver_id')
            ? $request->integer('approver_id')
            : null;

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Data {$user->name} berhasil diperbarui.");
    }
}
