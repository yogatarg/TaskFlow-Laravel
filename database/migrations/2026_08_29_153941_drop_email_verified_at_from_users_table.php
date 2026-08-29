<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TaskFlow tidak memverifikasi alamat email, jadi kolom ini tidak pernah dibaca
     * maupun ditulis oleh kode mana pun.
     *
     * Kenapa dibuang, bukan sekadar dibiarkan: kolom bernama `email_verified_at` di
     * tabel users menyiratkan aplikasi ini melakukan verifikasi email. Ia tidak.
     * Skema yang menyisakan janji yang tidak ditepati sama menyesatkannya dengan
     * middleware `verified` yang tidak menolak siapa pun.
     *
     * Yang menjadi penjaga di aplikasi ini adalah Admin yang menetapkan role dan
     * approver -- user baru tidak bisa berbuat apa-apa yang berarti sebelum itu.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kolomnya bisa dikembalikan, tapi nilainya tidak: stempel waktu lama
            // hilang bersama kolomnya. Itu bisa diterima justru karena tidak ada
            // yang pernah membacanya.
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }
};
