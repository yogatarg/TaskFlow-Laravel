<?php

use App\Http\Controllers\Admin\ApprovalLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskSubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
 * CRUD Task. Middleware `auth` dan seluruh pengecekan izin per-task dipasang
 * di dalam TaskController::middleware(), bukan di sini.
 */
Route::resource('tasks', TaskController::class);

/*
 * Perpindahan status task. Sengaja di luar Route::resource karena bukan operasi CRUD:
 * tidak ada data yang dibuat, diubah isinya, atau dihapus -- yang bergerak adalah
 * posisi task di dalam state machine.
 *
 * Semuanya POST, bukan GET, karena mengubah keadaan. Aksi yang mengubah data tidak
 * boleh bisa dipicu hanya dengan membuka sebuah URL (atau di-prefetch browser).
 */
Route::post('tasks/{task}/submit', TaskSubmissionController::class)
    ->middleware('auth')
    ->name('tasks.submit');

Route::middleware('auth')->prefix('approvals')->name('approvals.')->group(function () {
    Route::get('/', [ApprovalController::class, 'index'])->name('index');
    Route::post('{task}/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::post('{task}/reject', [ApprovalController::class, 'reject'])->name('reject');
    Route::post('{task}/request-revision', [ApprovalController::class, 'requestRevision'])->name('request-revision');
});

/*
 * Area Admin. Middleware `role:Admin` berasal dari alias yang didaftarkan di
 * bootstrap/app.php dan menunjuk ke EnsureUserHasRole.
 */
Route::middleware(['auth', 'role:Admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

        // Hanya index: ApprovalLog append-only, tidak ada operasi lain yang sah.
        Route::get('logs', [ApprovalLogController::class, 'index'])->name('logs.index');
    });

require __DIR__.'/auth.php';
