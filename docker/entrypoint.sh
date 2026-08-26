#!/bin/sh
set -e

# =============================================================================
# Dijalankan setiap kali kontainer menyala.
#
# Caching konfigurasi sengaja dilakukan di sini, BUKAN saat build image.
# Alasannya: config:cache membekukan nilai env ke dalam satu berkas PHP, dan
# saat build image variabel env produksi (DB_URL, APP_KEY) belum tersedia.
# Kalau di-cache saat build, aplikasi akan berjalan dengan konfigurasi kosong.
# =============================================================================

echo "==> Menyiapkan direktori yang perlu ditulis"
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "==> Meng-cache konfigurasi, route, dan view"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Menjalankan migrasi"
# --force wajib: tanpa itu Artisan meminta konfirmasi interaktif di APP_ENV=production
# dan kontainer akan menggantung selamanya menunggu jawaban yang tidak akan datang.
php artisan migrate --force

# Render menyuntikkan nomor port lewat variabel PORT. FrankenPHP membaca alamat
# dengar dari SERVER_NAME, jadi keduanya dijembatani di sini.
: "${PORT:=80}"
export SERVER_NAME=":${PORT}"

echo "==> Menjalankan FrankenPHP pada port ${PORT}"
exec frankenphp run --config /etc/frankenphp/Caddyfile
