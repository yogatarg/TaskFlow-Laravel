<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\ApprovalLog;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RoleUserSeeder;
use Database\Seeders\TaskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $approver;

    private User $pemilik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->approver = User::factory()->approver()->create(['name' => 'Budi Penyetuju']);
        $this->pemilik = User::factory()->bawahanDari($this->approver)->create(['name' => 'Sari Pembuat']);
    }

    // ------------------------------------------------------- disembunyikan, bukan dihapus

    public function test_menghapus_task_hanya_menyembunyikannya(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->pemilik)
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect(route('tasks.index'));

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_riwayat_approval_tidak_ikut_hilang(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->pemilik)->post(route('tasks.submit', $task));
        $this->actingAs($this->approver)->post(route('approvals.approve', $task), ['catatan' => 'Setuju.']);

        $this->assertSame(2, ApprovalLog::count());

        $this->actingAs($this->admin)->delete(route('tasks.destroy', $task));

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertSame(2, ApprovalLog::count());
    }

    // ------------------------------------------------------------------ siapa boleh apa

    public function test_admin_bisa_menyembunyikan_task_berstatus_apa_pun(): void
    {
        foreach (TaskStatus::cases() as $status) {
            $task = Task::factory()->berstatus($status)->create(['created_by' => $this->pemilik->id]);

            $this->actingAs($this->admin)
                ->delete(route('tasks.destroy', $task))
                ->assertRedirect(route('tasks.index'));

            $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        }
    }

    public function test_pemilik_tetap_tidak_bisa_menyembunyikan_task_yang_sudah_diajukan(): void
    {
        foreach ([TaskStatus::PendingApproval, TaskStatus::Approved, TaskStatus::Rejected] as $status) {
            $task = Task::factory()->berstatus($status)->create(['created_by' => $this->pemilik->id]);

            $this->actingAs($this->pemilik)
                ->delete(route('tasks.destroy', $task))
                ->assertForbidden();

            $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);
        }
    }

    public function test_approver_tidak_bisa_menyembunyikan_task_bawahannya(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->approver)
            ->delete(route('tasks.destroy', $task))
            ->assertForbidden();
    }

    // -------------------------------------------------------------- benar-benar hilang

    public function test_task_tersembunyi_hilang_dari_daftar_task(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id, 'title' => 'Task rahasia']);
        $task->delete();

        $this->actingAs($this->pemilik)->get(route('tasks.index'))->assertDontSee('Task rahasia');
        $this->actingAs($this->admin)->get(route('tasks.index'))->assertDontSee('Task rahasia');
    }

    public function test_task_tersembunyi_hilang_dari_inbox_approver(): void
    {
        $task = Task::factory()->menungguApproval()
            ->create(['created_by' => $this->pemilik->id, 'title' => 'Task rahasia']);
        $task->delete();

        $this->actingAs($this->approver)
            ->get(route('approvals.index'))
            ->assertDontSee('Task rahasia');
    }

    public function test_task_tersembunyi_tidak_ikut_terhitung_di_dashboard(): void
    {
        Task::factory()->count(3)->create(['created_by' => $this->pemilik->id]);
        Task::factory()->create(['created_by' => $this->pemilik->id])->delete();

        $hitungan = $this->actingAs($this->pemilik)->get(route('dashboard'))->viewData('hitungan');

        $this->assertSame(3, (int) $hitungan->total);
        $this->assertSame(3, (int) $hitungan->dikerjakan);
    }

    public function test_task_tersembunyi_tidak_bisa_dibuka_langsung(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);
        $task->delete();

        // Route model binding biasa tidak melihat baris terhapus -> 404.
        $this->actingAs($this->pemilik)->get(route('tasks.show', $task))->assertNotFound();
        $this->actingAs($this->admin)->get(route('tasks.show', $task))->assertNotFound();
    }

    // ------------------------------------------------------------------------- arsip

    public function test_admin_bisa_melihat_arsip(): void
    {
        $aktif = Task::factory()->create(['created_by' => $this->pemilik->id, 'title' => 'Masih aktif']);
        $tersembunyi = Task::factory()->create(['created_by' => $this->pemilik->id, 'title' => 'Sudah disembunyikan']);
        $tersembunyi->delete();

        $this->actingAs($this->admin)
            ->get(route('tasks.index', ['arsip' => 1]))
            ->assertOk()
            ->assertSee('Sudah disembunyikan')
            ->assertDontSee('Masih aktif');
    }

    public function test_user_biasa_tidak_bisa_mengintip_arsip_lewat_url(): void
    {
        $tersembunyi = Task::factory()->create(['created_by' => $this->pemilik->id, 'title' => 'Task rahasia']);
        $tersembunyi->delete();

        // Menambahkan ?arsip=1 sendiri tidak boleh membuka apa pun.
        $this->actingAs($this->pemilik)
            ->get(route('tasks.index', ['arsip' => 1]))
            ->assertOk()
            ->assertDontSee('Task rahasia');

        $this->actingAs($this->approver)
            ->get(route('tasks.index', ['arsip' => 1]))
            ->assertOk()
            ->assertDontSee('Task rahasia');
    }

    // ---------------------------------------------------------------------- pemulihan

    public function test_admin_bisa_memulihkan_task(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id, 'title' => 'Kembali lagi']);
        $task->delete();

        $this->actingAs($this->admin)
            ->post(route('tasks.restore', $task))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);

        $this->actingAs($this->pemilik)->get(route('tasks.index'))->assertSee('Kembali lagi');
    }

    public function test_hanya_admin_yang_bisa_memulihkan(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);
        $task->delete();

        $this->actingAs($this->pemilik)->post(route('tasks.restore', $task))->assertForbidden();
        $this->actingAs($this->approver)->post(route('tasks.restore', $task))->assertForbidden();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    // ------------------------------------------------------------------ jejak di log

    public function test_judul_task_tersembunyi_tetap_terbaca_di_halaman_log(): void
    {
        $task = Task::factory()->create([
            'created_by' => $this->pemilik->id,
            'title' => 'Proposal yang disembunyikan',
        ]);

        $this->actingAs($this->pemilik)->post(route('tasks.submit', $task));
        $this->actingAs($this->admin)->delete(route('tasks.destroy', $task));

        // Log append-only harus tetap utuh dan terbaca. Kalau relasi task() tidak
        // memakai withTrashed(), $log->task menjadi null dan halaman ini error.
        $this->actingAs($this->admin)
            ->get(route('admin.logs.index'))
            ->assertOk()
            ->assertSee('Proposal yang disembunyikan');
    }

    public function test_seeder_tidak_menghidupkan_kembali_task_contoh_yang_disembunyikan(): void
    {
        $this->seed(RoleUserSeeder::class);
        $this->seed(TaskSeeder::class);

        $jumlahAwal = Task::count();
        $this->assertGreaterThan(0, $jumlahAwal);

        Task::first()->delete();

        // Seeder dijalankan ulang setiap kontainer menyala di produksi.
        $this->seed(TaskSeeder::class);

        $this->assertSame($jumlahAwal - 1, Task::count());
        $this->assertSame($jumlahAwal, Task::withTrashed()->count());
    }
}
