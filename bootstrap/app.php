<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
        /*
         * Token CSRF yang kedaluwarsa menghasilkan halaman "419 | Page Expired"
         * yang telanjang dan tidak menjelaskan apa pun. Bagi pengunjung, itu
         * terbaca seperti aplikasi rusak -- padahal justru penjagaan yang bekerja.
         *
         * Kasus paling umum: halaman login dibuka lalu didiamkan melewati masa
         * sesi, atau tombol Back menampilkan halaman lama dari cache peramban.
         *
         * Diarahkan kembali dengan pesan yang bisa ditindaklanjuti. Kalau sesinya
         * benar-benar habis, $request->user() sudah null dan pengunjung dibawa ke
         * halaman login; kalau hanya token yang basi, ia dikembalikan ke halaman
         * asalnya tanpa kehilangan konteks.
         */
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            $pesan = 'Sesi Anda sudah berakhir karena halaman dibiarkan terlalu lama. Silakan coba lagi.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $pesan], 419);
            }

            return redirect()
                ->to($request->user() ? url()->previous() : route('login'))
                ->with('status', $pesan);
        });
    })->create();
