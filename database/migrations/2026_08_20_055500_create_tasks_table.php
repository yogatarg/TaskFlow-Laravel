<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            // Enum disimpan sebagai string biasa, bukan tipe enum native PostgreSQL.
            // Alasannya: menambah nilai baru pada enum native butuh migrasi DDL khusus,
            // sedangkan di sini cukup menambah case di app/Enums. Yang menjaga
            // nilainya tetap sah adalah cast Eloquent + validasi Form Request.
            $table->string('label');
            $table->string('priority');
            $table->string('status')->default(TaskStatus::Draft->value);

            $table->date('deadline')->nullable();

            // Sesuai spesifikasi: TIDAK ada approver_id di tabel ini. Approver ditentukan
            // lewat users.approver_id milik pembuatnya, supaya perubahan approver tidak
            // perlu menyentuh task lama.
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            // Query paling sering: "task saya, terbaru dulu" dan "task yang menunggu approval".
            $table->index(['created_by', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
