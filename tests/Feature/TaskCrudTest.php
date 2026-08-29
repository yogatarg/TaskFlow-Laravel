<?php

namespace Tests\Feature;

use App\Enums\TaskLabel;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    private function dataTaskValid(array $override = []): array
    {
        return array_merge([
            'title' => 'Menyusun laporan mingguan',
            'description' => 'Rekap progres tim minggu ini.',
            'label' => TaskLabel::Harian->value,
            'priority' => TaskPriority::Sedang->value,
            'deadline' => now()->addWeek()->format('Y-m-d'),
        ], $override);
    }

    public function test_tamu_tidak_bisa_mengakses_daftar_task(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
    }

    public function test_task_baru_selalu_berstatus_draft_dan_milik_pembuatnya(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tasks.store'), $this->dataTaskValid())
            ->assertRedirect();

        $task = Task::firstOrFail();

        $this->assertSame(TaskStatus::Draft, $task->status);
        $this->assertSame($user->id, $task->created_by);
    }

    public function test_status_tidak_bisa_disuntikkan_lewat_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), $this->dataTaskValid([
            'status' => TaskStatus::Approved->value,
        ]));

        // `status` tidak ada di $fillable, jadi input tambahan ini diabaikan.
        $this->assertSame(TaskStatus::Draft, Task::firstOrFail()->status);
    }

    public function test_pembuat_task_tidak_bisa_dipalsukan_lewat_form(): void
    {
        $user = User::factory()->create();
        $korban = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), $this->dataTaskValid([
            'created_by' => $korban->id,
        ]));

        $this->assertSame($user->id, Task::firstOrFail()->created_by);
    }

    public function test_judul_wajib_diisi(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tasks.store'), $this->dataTaskValid(['title' => '']))
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Task::count());
    }

    public function test_label_di_luar_daftar_ditolak(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tasks.store'), $this->dataTaskValid(['label' => 'Liburan']))
            ->assertSessionHasErrors('label');
    }

    public function test_daftar_task_hanya_menampilkan_milik_sendiri(): void
    {
        $user = User::factory()->create();
        $orangLain = User::factory()->create();

        $milikSaya = Task::factory()->create(['created_by' => $user->id, 'title' => 'Punya saya']);
        $milikOrangLain = Task::factory()->create(['created_by' => $orangLain->id, 'title' => 'Punya orang lain']);

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertSee($milikSaya->title)
            ->assertDontSee($milikOrangLain->title);
    }

    public function test_admin_melihat_semua_task(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create(['title' => 'Task milik orang lain']);

        $this->actingAs($admin)
            ->get(route('tasks.index'))
            ->assertSee($task->title);
    }

    public function test_user_tidak_bisa_membuka_task_milik_orang_lain(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($user)->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_approver_bisa_membuka_task_milik_bawahannya(): void
    {
        $approver = User::factory()->approver()->create();
        $bawahan = User::factory()->bawahanDari($approver)->create();
        $task = Task::factory()->create(['created_by' => $bawahan->id]);

        $this->actingAs($approver)->get(route('tasks.show', $task))->assertOk();
    }

    public function test_approver_tidak_bisa_membuka_task_di_luar_bawahannya(): void
    {
        $approver = User::factory()->approver()->create();
        $task = Task::factory()->create();

        $this->actingAs($approver)->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_pemilik_bisa_mengubah_task_draft(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->put(route('tasks.update', $task), $this->dataTaskValid(['title' => 'Judul diperbarui']))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('Judul diperbarui', $task->refresh()->title);
    }

    public function test_task_yang_menunggu_approval_tidak_bisa_diubah(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->menungguApproval()->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->put(route('tasks.update', $task), $this->dataTaskValid(['title' => 'Coba diubah']))
            ->assertForbidden();

        $this->actingAs($user)->get(route('tasks.edit', $task))->assertForbidden();
    }

    public function test_task_berstatus_revision_requested_bisa_diubah_lagi(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->dimintaRevisi()->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->put(route('tasks.update', $task), $this->dataTaskValid(['title' => 'Sudah direvisi']))
            ->assertRedirect();

        $this->assertSame('Sudah direvisi', $task->refresh()->title);
    }

    public function test_task_berstatus_terminal_tidak_bisa_diubah_atau_dihapus(): void
    {
        $user = User::factory()->create();

        foreach ([TaskStatus::Approved, TaskStatus::Rejected] as $status) {
            $task = Task::factory()->berstatus($status)->create(['created_by' => $user->id]);

            $this->actingAs($user)
                ->put(route('tasks.update', $task), $this->dataTaskValid())
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('tasks.destroy', $task))
                ->assertForbidden();

            $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        }
    }

    public function test_pemilik_bisa_menghapus_task_draft(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect(route('tasks.index'));

        // Task memakai SoftDeletes, jadi barisnya sengaja tetap ada dengan deleted_at
        // terisi -- bukan assertDatabaseMissing. Perilaku lengkapnya diuji di
        // SoftDeleteTaskTest; di sini cukup dipastikan route destroy memang bekerja.
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_user_lain_tidak_bisa_menghapus_task_yang_bukan_miliknya(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_daftar_task_bisa_disaring_per_status(): void
    {
        $user = User::factory()->create();
        $draft = Task::factory()->create(['created_by' => $user->id, 'title' => 'Masih draft']);
        $menunggu = Task::factory()->menungguApproval()->create(['created_by' => $user->id, 'title' => 'Sedang diajukan']);

        $this->actingAs($user)
            ->get(route('tasks.index', ['status' => TaskStatus::PendingApproval->value]))
            ->assertSee($menunggu->title)
            ->assertDontSee($draft->title);
    }

    public function test_halaman_buat_task_bisa_dirender(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tasks.create'))
            ->assertOk()
            ->assertSee('Simpan sebagai Draft');
    }

    public function test_halaman_ubah_task_bisa_dirender_dan_terisi_data_lama(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'created_by' => $user->id,
            'title' => 'Judul lama',
        ]);

        $this->actingAs($user)
            ->get(route('tasks.edit', $task))
            ->assertOk()
            ->assertSee('Judul lama', escape: false);
    }

    public function test_halaman_detail_menampilkan_nama_approver(): void
    {
        $approver = User::factory()->approver()->create(['name' => 'Budi Penyetuju']);
        $pemilik = User::factory()->bawahanDari($approver)->create();
        $task = Task::factory()->create(['created_by' => $pemilik->id]);

        $this->actingAs($pemilik)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Budi Penyetuju');
    }

    public function test_detail_task_memperingatkan_kalau_pemilik_belum_punya_approver(): void
    {
        $pemilik = User::factory()->create(); // approver_id masih null
        $task = Task::factory()->create(['created_by' => $pemilik->id]);

        $this->actingAs($pemilik)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Belum ada approver');
    }
}
