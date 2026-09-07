<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan yang tidak dipasang Laravel maupun Render secara bawaan.
 *
 * Diperiksa pada 4 September 2026 terhadap situs produksi: tidak ada satu pun
 * header di bawah yang terkirim. Situs bisa disematkan dalam iframe milik orang
 * lain, dan versi PHP persis terbaca oleh siapa pun.
 *
 * Content-Security-Policy sengaja TIDAK dipasang di sini. Halaman memakai skrip
 * inline dari Alpine bawaan Breeze, sehingga CSP yang benar-benar ketat akan
 * merusak antarmuka -- sementara CSP longgar yang mengizinkan 'unsafe-inline'
 * hanya memberi rasa aman tanpa melindungi apa pun. Lebih jujur tidak memasangnya
 * daripada memasang yang tidak berguna.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Melarang situs lain menyematkan halaman ini dalam iframe (clickjacking).
        $response->headers->set('X-Frame-Options', 'DENY');

        // Peramban dilarang menebak-nebak tipe berkas dari isinya.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Alamat halaman internal tidak ikut bocor ke situs luar lewat Referer.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Aplikasi ini tidak memerlukan satu pun perangkat tersebut.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        // HSTS hanya bermakna kalau koneksinya sudah HTTPS. Memasangnya pada
        // koneksi biasa tidak berguna, dan di lingkungan lokal justru merepotkan
        // karena peramban akan memaksa https untuk seluruh localhost.
        //
        // Lingkungan produksi ikut dimasukkan karena $request->secure() ternyata
        // tidak bisa diandalkan di balik rantai proxy berlapis -- lihat penjelasan
        // di AppServiceProvider. Di produksi, HTTPS memang satu-satunya jalan masuk.
        if ($request->secure() || app()->isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // PHP menambahkan X-Powered-By berisi versi persisnya. Itu memberi tahu
        // penyerang kerentanan mana yang perlu dicoba. Dimatikan lewat expose_php
        // di Dockerfile, dan dibuang di sini sebagai jaring pengaman untuk
        // lingkungan yang php.ini-nya tidak ikut terpasang.
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }
}
