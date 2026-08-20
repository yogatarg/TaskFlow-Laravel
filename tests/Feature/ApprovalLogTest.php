<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Models\ApprovalLog;
use App\Models\Task;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ApprovalLogTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;

    private User $pemilik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approver = User::factory()->approver()->create(['name' => 'Budi Penyetuju']);
        $this->pemilik = User::factory()->bawahanDari($this->approver)->create(['name' => 'Sari Pembuat']);
    }

    // ------------------------------------------------- log ditulis otomatis

    public function test_mengajukan_task_menulis_satu_baris_log(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->pemilik)->post(route('tasks.submit', $task));

        $log = ApprovalLog::sole();

        $this->assertSame($task->id, $log->task_id);
        $this->assertSame($this->pemilik->id, $log->actor_id);
        $this->assertSame(ApprovalAction::Submit, $log->action);
        $this->assertNull($log->catatan);
        $this->assertNotNull($log->timestamp);
    }

    public function test_catatan_approver_tersimpan_di_log(): void
    {
        $task = Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->approver)->post(route('approvals.reject', $task), [
            'catatan' => 'Anggarannya belum wajar.',
        ]);

        $log = ApprovalLog::sole();

        $this->assertSame(ApprovalAction::Reject, $log->action);
        $this->assertSame($this->approver->id, $log->actor_id);
        $this->assertSame('Anggarannya belum wajar.', $log->catatan);
    }

    public function test_catatan_kosong_disimpan_sebagai_null_bukan_string_kosong(): void
    {
        $task = Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->approver)->post(route('approvals.approve', $task), ['catatan' => '   ']);

        $this->assertNull(ApprovalLog::sole()->catatan);
    }

    public function test_siklus_penuh_meninggalkan_empat_baris_log_berurutan(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->pemilik)->post(route('tasks.submit', $task));
        $this->actingAs($this->approver)->post(route('approvals.request-revision', $task), ['catatan' => 'Lengkapi rinciannya.']);
        $this->actingAs($this->pemilik)->post(route('tasks.submit', $task));
        $this->actingAs($this->approver)->post(route('approvals.approve', $task), ['catatan' => 'Sudah sesuai.']);

        $urutan = $task->approvalLogs()->kronologis()->get();

        $this->assertCount(4, $urutan);
        $this->assertSame([
            ApprovalAction::Submit,
            ApprovalAction::RequestRevision,
            ApprovalAction::Submit,
            ApprovalAction::Approve,
        ], $urutan->pluck('action')->all());

        $this->assertSame([
            $this->pemilik->id,
            $this->approver->id,
            $this->pemilik->id,
            $this->approver->id,
        ], $urutan->pluck('actor_id')->all());
    }

    // ------------------------------------------- status dan log tak terpisah

    public function test_transisi_yang_ditolak_tidak_meninggalkan_log(): void
    {
        $task = Task::factory()->disetujui()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->approver)
            ->post(route('approvals.reject', $task), ['catatan' => 'Berubah pikiran.'])
            ->assertForbidden();

        $this->assertSame(0, ApprovalLog::count());
    }

    public function test_validasi_gagal_tidak_meninggalkan_log(): void
    {
        $task = Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id]);

        // Menolak tanpa catatan -> validasi gagal sebelum service dipanggil.
        $this->actingAs($this->approver)
            ->post(route('approvals.reject', $task), ['catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->assertSame(0, ApprovalLog::count());
    }

    public function test_kegagalan_menulis_log_ikut_membatalkan_perubahan_status(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);
        $statusAwal = $task->status;

        // Paksa penulisan log gagal dengan menghapus tabelnya, lalu pastikan
        // status task ikut ter-rollback -- bukan berubah tanpa jejak.
        DB::statement('DROP TABLE approval_logs');

        try {
            app(ApprovalService::class)->ajukan($task, $this->pemilik);
            $this->fail('Seharusnya melempar exception.');
        } catch (\Throwable) {
            // diabaikan; yang diuji adalah keadaan database setelahnya
        }

        $this->assertSame($statusAwal, $task->fresh()->status);
    }

    // ------------------------------------------------------- append-only

    public function test_log_tidak_bisa_diubah(): void
    {
        $log = ApprovalLog::factory()->create();

        $this->expectException(RuntimeException::class);

        $log->update(['catatan' => 'diam-diam diubah']);
    }

    public function test_log_tidak_bisa_dihapus(): void
    {
        $log = ApprovalLog::factory()->create();

        $this->expectException(RuntimeException::class);

        $log->delete();
    }

    public function test_akun_yang_pernah_memproses_approval_tidak_bisa_dihapus(): void
    {
        $task = Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id]);
        $this->actingAs($this->approver)->post(route('approvals.approve', $task), ['catatan' => '']);

        $this->actingAs($this->approver)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password', null, 'userDeletion');

        $this->assertDatabaseHas('users', ['id' => $this->approver->id]);
    }

    public function test_akun_yang_masih_punya_bawahan_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->approver)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password', null, 'userDeletion');

        $this->assertDatabaseHas('users', ['id' => $this->approver->id]);
    }

    public function test_akun_tanpa_riwayat_masih_bisa_dihapus(): void
    {
        $biasa = User::factory()->create();

        $this->actingAs($biasa)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $biasa->id]);
    }

    // ---------------------------------------------------------- tampilan

    public function test_riwayat_tampil_di_halaman_detail_task(): void
    {
        $task = Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id]);
        $this->actingAs($this->approver)->post(route('approvals.request-revision', $task), [
            'catatan' => 'Tolong lampirkan rincian biaya.',
        ]);

        $this->actingAs($this->pemilik)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Riwayat Approval')
            ->assertSee('Diminta revisi')
            ->assertSee('Tolong lampirkan rincian biaya.')
            ->assertSee('Budi Penyetuju');
    }

    public function test_task_tanpa_riwayat_menampilkan_keadaan_kosong(): void
    {
        $task = Task::factory()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->pemilik)
            ->get(route('tasks.show', $task))
            ->assertSee('Belum ada aktivitas');
    }

    // ------------------------------------------------------ log admin

    public function test_hanya_admin_yang_bisa_membuka_log_global(): void
    {
        $this->actingAs($this->pemilik)->get(route('admin.logs.index'))->assertForbidden();
        $this->actingAs($this->approver)->get(route('admin.logs.index'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.logs.index'))
            ->assertOk();
    }

    public function test_log_global_bisa_disaring_per_aksi(): void
    {
        $admin = User::factory()->admin()->create();

        $disetujui = Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id, 'title' => 'Task disetujui']);
        $ditolak = Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id, 'title' => 'Task ditolak']);

        $this->actingAs($this->approver)->post(route('approvals.approve', $disetujui), ['catatan' => '']);
        $this->actingAs($this->approver)->post(route('approvals.reject', $ditolak), ['catatan' => 'Tidak sesuai.']);

        $this->actingAs($admin)
            ->get(route('admin.logs.index', ['action' => ApprovalAction::Reject->value]))
            ->assertSee('Task ditolak')
            ->assertDontSee('Task disetujui');
    }

    public function test_log_global_bisa_disaring_per_pelaku(): void
    {
        $admin = User::factory()->admin()->create();
        $approverLain = User::factory()->approver()->create(['name' => 'Citra Penyetuju']);
        $pemilikLain = User::factory()->bawahanDari($approverLain)->create();

        $punyaBudi = Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id, 'title' => 'Diputuskan Budi']);
        $punyaCitra = Task::factory()->menungguApproval()->create(['created_by' => $pemilikLain->id, 'title' => 'Diputuskan Citra']);

        $this->actingAs($this->approver)->post(route('approvals.approve', $punyaBudi), ['catatan' => '']);
        $this->actingAs($approverLain)->post(route('approvals.approve', $punyaCitra), ['catatan' => '']);

        $this->actingAs($admin)
            ->get(route('admin.logs.index', ['actor_id' => $approverLain->id]))
            ->assertSee('Diputuskan Citra')
            ->assertDontSee('Diputuskan Budi');
    }
}
