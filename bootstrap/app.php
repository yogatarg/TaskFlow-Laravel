<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        /*
         * Di produksi aplikasi berjalan di belakang load balancer milik penyedia
         * hosting, yang meneruskan request ke PHP lewat HTTP biasa. Tanpa ini
         * Laravel mengira koneksinya memang http://, lalu:
         *
         *   - url() dan route() menghasilkan tautan http://, sehingga peramban
         *     memblokir atau mencampur konten
         *   - cookie session yang ditandai `secure` tidak pernah terkirim balik,
         *     sehingga login seolah selalu gagal
         *   - $request->ip() mengembalikan alamat load balancer, bukan pengunjung
         *
         * `at: '*'` mempercayai proxy mana pun. Itu aman pada PaaS karena satu-satunya
         * jalan masuk ke aplikasi memang lewat load balancer penyedia. Kalau nanti
         * dipindah ke VPS yang bisa diakses langsung, ganti dengan daftar IP proxy
         * yang sebenarnya.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
