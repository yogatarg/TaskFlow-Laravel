<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(Role::User->value)->after('password');

            // Approver ditentukan per-user, BUKAN per-task (lihat catatan di CLAUDE.md).
            // restrictOnDelete: user yang masih menjadi approver orang lain tidak boleh dihapus
            // begitu saja — harus dipindahkan dulu, supaya tidak ada user yatim tanpa approver.
            $table->foreignId('approver_id')
                ->nullable()
                ->after('role')
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_id');
            $table->dropColumn('role');
        });
    }
};
