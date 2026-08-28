# syntax=docker/dockerfile:1

# =============================================================================
# TaskFlow — image produksi
#
# Memakai FrankenPHP: satu binary yang menggabungkan server web (Caddy) dan PHP
# dalam satu proses. Alternatif klasiknya nginx + php-fpm + supervisor, yang
# berarti tiga proses dan empat berkas konfigurasi di dalam satu kontainer.
# Untuk aplikasi sebesar ini, satu proses lebih mudah dipahami dan di-debug.
#
# Build dilakukan dalam tiga tahap supaya image akhir tidak membawa Node.js,
# Composer, maupun dependensi pengembangan.
# =============================================================================


# --- Tahap 1: bangun aset frontend -------------------------------------------
FROM node:22-alpine AS aset

WORKDIR /app

# package.json disalin lebih dulu supaya lapisan npm ci bisa di-cache dan tidak
# diulang setiap kali ada perubahan kode PHP.
COPY package.json package-lock.json ./
RUN npm ci

# Tailwind memindai berkas Blade dan PHP untuk menentukan kelas mana yang dipakai,
# jadi seluruh proyek perlu ada saat build (lihat .dockerignore untuk yang dibuang).
COPY . .
RUN npm run build


# --- Tahap 2: pasang dependensi PHP ------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-dev membuang PHPUnit, Pint, dan kawan-kawan dari image produksi.
# --no-scripts karena skrip Laravel butuh berkas aplikasi yang belum disalin;
# autoloader dibuat ulang setelah kode lengkap masuk.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-progress

COPY . .

RUN composer dump-autoload --optimize --no-dev


# --- Tahap 3: image akhir ----------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app

# pdo_pgsql untuk PostgreSQL, opcache supaya PHP tidak mengurai ulang berkas
# yang sama pada setiap request. install-php-extensions sudah tersedia di image.
RUN install-php-extensions \
        pdo_pgsql \
        opcache \
        intl \
        zip

# Pengaturan opcache untuk produksi: kode tidak pernah berubah di dalam kontainer,
# jadi tidak ada gunanya memeriksa timestamp berkas pada setiap request.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
    } > "$PHP_INI_DIR/conf.d/opcache.ini" \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Image FrankenPHP memasang file capability cap_net_bind_service pada binary-nya
# supaya bisa mengikat port di bawah 1024. Render menjalankan kontainer dengan
# no-new-privileges, dan dalam kondisi itu kernel MENOLAK menjalankan berkas yang
# punya file capability -- execve gagal dengan EPERM ("Operation not permitted").
#
# Capability itu memang tidak dibutuhkan di sini: Render menyuntikkan port tinggi
# lewat $PORT (10000), dan port di atas 1024 tidak perlu hak istimewa apa pun.
RUN apk add --no-cache libcap \
    && setcap -r /usr/local/bin/frankenphp \
    && ! getcap /usr/local/bin/frankenphp | grep -q cap_

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=aset /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint"]
