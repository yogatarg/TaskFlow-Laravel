<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();

            // Task ikut terhapus -> log-nya ikut terhapus. Tidak ada gunanya menyimpan
            // riwayat approval untuk task yang sudah tidak ada. Lagi pula task hanya bisa
            // dihapus selagi Draft/Revision Requested, jadi kasus ini praktis tidak terjadi.
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();

            // Pelaku TIDAK boleh hilang. Inti dari log ini adalah menjawab "siapa yang
            // memproses", jadi user yang pernah memutuskan sesuatu tidak bisa dihapus
            // begitu saja. Lihat ProfileController untuk penanganannya di sisi aplikasi.
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();

            $table->string('action');
            $table->text('catatan')->nullable();

            // Nama kolomnya `timestamp` -- mengikuti spesifikasi apa adanya, bukan
            // created_at/updated_at bawaan Laravel. Log ini append-only: tidak pernah
            // diubah, jadi kolom updated_at memang tidak ada gunanya.
            $table->timestamp('timestamp');

            // Query paling sering: riwayat satu task secara kronologis.
            $table->index(['task_id', 'timestamp']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
