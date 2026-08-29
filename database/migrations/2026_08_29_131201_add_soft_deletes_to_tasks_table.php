<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Soft delete, bukan hapus permanen.
            //
            // Menghapus baris task berarti approval_logs miliknya ikut terhapus lewat
            // cascade -- riwayat "siapa menyetujui apa" lenyap tanpa jejak. Padahal
            // seluruh tahap 4 dibangun justru supaya riwayat itu tidak bisa dibengkokkan.
            //
            // Dengan deleted_at, task hilang dari semua daftar tapi barisnya tetap ada,
            // log-nya tetap utuh, dan salah hapus masih bisa dipulihkan.
            $table->softDeletes();

            // Daftar task selalu menyaring `deleted_at is null`. Indeks parsial ini
            // membuat penyaringan itu murah tanpa ikut mengindeks baris yang terhapus.
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
