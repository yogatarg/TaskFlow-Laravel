<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Di produksi, HTTPS dinyatakan secara eksplisit alih-alih disimpulkan dari
         * header X-Forwarded-Proto.
         *
         * Alasannya ditemukan lewat percobaan, bukan teori. Saat trusted proxies
         * diubah dari '*' menjadi rentang CIDR -- perubahan yang diperlukan agar
         * $request->ip() menghasilkan alamat pengunjung sebenarnya, sehingga
         * pembatasan percobaan login berfungsi -- Symfony mulai mengambil nilai yang
         * berbeda dari rantai X-Forwarded-Proto, dan menyimpulkan koneksinya http.
         * Akibatnya seluruh URL absolut berubah menjadi http:// dan HSTS tidak
         * pernah terkirim.
         *
         * Menyatakannya langsung menghilangkan ketergantungan pada bentuk rantai
         * header milik penyedia hosting, yang bisa berubah kapan saja tanpa
         * pemberitahuan. Aman dilakukan karena satu-satunya jalan masuk ke aplikasi
         * ini di produksi memang HTTPS.
         *
         * Lingkungan lokal sengaja tidak disentuh: memaksa https di sana justru
         * membuat aplikasi tidak bisa dibuka lewat php artisan serve.
         */
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
