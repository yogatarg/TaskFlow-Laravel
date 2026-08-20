<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi route ke role tertentu. Dipakai lewat alias, misal:
 *
 *     Route::middleware('role:Admin')->group(...)
 *     Route::middleware('role:Approver,Admin')->group(...)
 *
 * Middleware ini hanya mengurus "role apa yang boleh masuk halaman ini".
 * Untuk "boleh apa terhadap task tertentu", pakai Policy — bukan middleware.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $allowed = array_map(
            fn (string $role) => Role::from($role),
            $roles,
        );

        if (! $user->hasRole(...$allowed)) {
            abort(403, 'Role Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
