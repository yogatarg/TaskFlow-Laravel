<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\TaskStatus;
use App\Exceptions\TransisiTidakSah;
use App\Models\Task;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
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

    private function taskDraft(): Task
    {
        return Task::factory()->create(['created_by' => $this->pemilik->id]);
    }

    private function taskMenunggu(): Task
    {
        return Task::factory()->menungguApproval()->create(['created_by' => $this->pemilik->id]);
    }

    // ---------------------------------------------------------------- submit

    public function test_pemilik_bisa_mengajukan_task_draft(): void
    {
        $task = $this->taskDraft();

        $this->actingAs($this->pemilik)
            ->post(route('tasks.submit', $task))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame(TaskStatus::PendingApproval, $task->refresh()->status);
    }

    public function test_orang_lain_tidak_bisa_mengajukan_task_milik_kita(): void
    {
        $task = $this->taskDraft();
        $orangLain = User::factory()->bawahanDari($this->approver)->create();

        $this->actingAs($orangLain)->post(route('tasks.submit', $task))->assertForbidden();
        $this->assertSame(TaskStatus::Draft, $task->refresh()->status);
    }

    public function test_approver_tidak_bisa_mengajukan_task_bawahannya(): void
    {
        $task = $this->taskDraft();

        $this->actingAs($this->approver)->post(route('tasks.submit', $task))->assertForbidden();
    }

    public function test_task_tidak_bisa_diajukan_kalau_pembuatnya_belum_punya_approver(): void
    {
        $yatim = User::factory()->create(); // approver_id null
        $task = Task::factory()->create(['created_by' => $yatim->id]);

        $this->actingAs($yatim)->post(route('tasks.submit', $task))->assertForbidden();
        $this->assertSame(TaskStatus::Draft, $task->refresh()->status);
    }

    public function test_task_yang_sudah_diajukan_tidak_bisa_diajukan_lagi(): void
    {
        $task = $this->taskMenunggu();

        $this->actingAs($this->pemilik)->post(route('tasks.submit', $task))->assertForbidden();
    }

    public function test_task_terminal_tidak_bisa_diajukan(): void
    {
        foreach ([TaskStatus::Approved, TaskStatus::Rejected] as $status) {
            $task = Task::factory()->berstatus($status)->create(['created_by' => $this->pemilik->id]);

            $this->actingAs($this->pemilik)->post(route('tasks.submit', $task))->assertForbidden();
            $this->assertSame($status, $task->refresh()->status);
        }
    }

    // --------------------------------------------------------------- inbox

    public function test_inbox_hanya_berisi_task_bawahan_yang_menunggu_keputusan(): void
    {
        $menunggu = Task::factory()->menungguApproval()
            ->create(['created_by' => $this->pemilik->id, 'title' => 'Menunggu keputusan']);
        $masihDraft = Task::factory()
            ->create(['created_by' => $this->pemilik->id, 'title' => 'Masih draft']);

        // Task orang lain yang bukan bawahan approver ini.
        $luar = Task::factory()->menungguApproval()->create(['title' => 'Bukan bawahan saya']);

        $this->actingAs($this->approver)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee($menunggu->title)
            ->assertDontSee($masihDraft->title)
            ->assertDontSee($luar->title);
    }

    public function test_user_biasa_tidak_bisa_membuka_inbox(): void
    {
        $this->actingAs($this->pemilik)->get(route('approvals.index'))->assertForbidden();
    }

    // ------------------------------------------------------------ keputusan

    public function test_approver_bisa_menyetujui_tanpa_catatan(): void
    {
        $task = $this->taskMenunggu();

        $this->actingAs($this->approver)
            ->post(route('approvals.approve', $task), ['catatan' => ''])
            ->assertRedirect(route('approvals.index'));

        $this->assertSame(TaskStatus::Approved, $task->refresh()->status);
    }

    public function test_menolak_wajib_menyertakan_catatan(): void
    {
        $task = $this->taskMenunggu();

        $this->actingAs($this->approver)
            ->post(route('approvals.reject', $task), ['catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->assertSame(TaskStatus::PendingApproval, $task->refresh()->status);
    }

    public function test_meminta_revisi_wajib_menyertakan_catatan(): void
    {
        $task = $this->taskMenunggu();

        $this->actingAs($this->approver)
            ->post(route('approvals.request-revision', $task), ['catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->assertSame(TaskStatus::PendingApproval, $task->refresh()->status);
    }

    public function test_approver_bisa_menolak_dengan_catatan(): void
    {
        $task = $this->taskMenunggu();

        $this->actingAs($this->approver)
            ->post(route('approvals.reject', $task), ['catatan' => 'Anggarannya belum wajar.'])
            ->assertRedirect(route('approvals.index'));

        $this->assertSame(TaskStatus::Rejected, $task->refresh()->status);
    }

    public function test_approver_bisa_meminta_revisi(): void
    {
        $task = $this->taskMenunggu();

        $this->actingAs($this->approver)
            ->post(route('approvals.request-revision', $task), ['catatan' => 'Lampirkan rinciannya.'])
            ->assertRedirect(route('approvals.index'));

        $this->assertSame(TaskStatus::RevisionRequested, $task->refresh()->status);
    }

    public function test_approver_lain_tidak_bisa_memutuskan(): void
    {
        $task = $this->taskMenunggu();
        $approverLain = User::factory()->approver()->create();

        $this->actingAs($approverLain)
            ->post(route('approvals.approve', $task), ['catatan' => ''])
            ->assertForbidden();

        $this->assertSame(TaskStatus::PendingApproval, $task->refresh()->status);
    }

    public function test_admin_tidak_otomatis_bisa_memutuskan(): void
    {
        $task = $this->taskMenunggu();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('approvals.approve', $task), ['catatan' => ''])
            ->assertForbidden();
    }

    public function test_pembuat_tidak_bisa_menyetujui_task_sendiri(): void
    {
        $task = $this->taskMenunggu();

        $this->actingAs($this->pemilik)
            ->post(route('approvals.approve', $task), ['catatan' => ''])
            ->assertForbidden();
    }

    public function test_task_yang_belum_diajukan_tidak_bisa_diputuskan(): void
    {
        $task = $this->taskDraft();

        $this->actingAs($this->approver)
            ->post(route('approvals.approve', $task), ['catatan' => ''])
            ->assertForbidden();
    }

    public function test_task_yang_sudah_diputuskan_tidak_bisa_diputuskan_lagi(): void
    {
        $task = Task::factory()->disetujui()->create(['created_by' => $this->pemilik->id]);

        $this->actingAs($this->approver)
            ->post(route('approvals.reject', $task), ['catatan' => 'Berubah pikiran.'])
            ->assertForbidden();

        $this->assertSame(TaskStatus::Approved, $task->refresh()->status);
    }

    // ----------------------------------------------------- siklus revisi

    public function test_siklus_penuh_ajukan_revisi_perbaiki_ajukan_lagi_setujui(): void
    {
        $task = $this->taskDraft();

        // 1. Pemilik mengajukan.
        $this->actingAs($this->pemilik)->post(route('tasks.submit', $task));
        $this->assertSame(TaskStatus::PendingApproval, $task->refresh()->status);

        // 2. Approver meminta revisi.
        $this->actingAs($this->approver)
            ->post(route('approvals.request-revision', $task), ['catatan' => 'Tambahkan rincian biaya.']);
        $this->assertSame(TaskStatus::RevisionRequested, $task->refresh()->status);

        // 3. Pemilik memperbaiki isinya -- baru mungkin karena status ini editable lagi.
        $this->actingAs($this->pemilik)->put(route('tasks.update', $task), [
            'title' => 'Proposal (revisi 1)',
            'description' => 'Sudah dilengkapi rincian biaya.',
            'label' => $task->label->value,
            'priority' => $task->priority->value,
            'deadline' => now()->addWeek()->format('Y-m-d'),
        ])->assertRedirect();
        $this->assertSame('Proposal (revisi 1)', $task->refresh()->title);

        // 4. Diajukan ulang.
        $this->actingAs($this->pemilik)->post(route('tasks.submit', $task));
        $this->assertSame(TaskStatus::PendingApproval, $task->refresh()->status);

        // 5. Disetujui, dan sejak itu terkunci.
        $this->actingAs($this->approver)->post(route('approvals.approve', $task), ['catatan' => 'Sudah sesuai.']);
        $this->assertSame(TaskStatus::Approved, $task->refresh()->status);

        $this->actingAs($this->pemilik)->get(route('tasks.edit', $task))->assertForbidden();
        $this->actingAs($this->pemilik)->delete(route('tasks.destroy', $task))->assertForbidden();
    }

    // ------------------------------------------------- service tanpa HTTP

    public function test_service_menolak_transisi_yang_melompati_state_machine(): void
    {
        $task = $this->taskDraft();

        $this->expectException(TransisiTidakSah::class);

        // Draft langsung ke Approved: tidak ada di state machine.
        app(ApprovalService::class)->jalankan($task, ApprovalAction::Approve, $this->approver);
    }

    public function test_service_menolak_menyentuh_task_berstatus_terminal(): void
    {
        $task = Task::factory()->ditolak()->create(['created_by' => $this->pemilik->id]);

        $this->expectException(TransisiTidakSah::class);

        app(ApprovalService::class)->ajukan($task, $this->pemilik);
    }

    public function test_service_menyegarkan_objek_task_yang_dipegang_pemanggil(): void
    {
        $task = $this->taskDraft();

        app(ApprovalService::class)->ajukan($task, $this->pemilik);

        // Tanpa memanggil refresh() sekalipun, objek di tangan pemanggil sudah mutakhir.
        $this->assertSame(TaskStatus::PendingApproval, $task->status);
    }

    // ------------------------------------------------------------- tampilan

    public function test_tombol_ajukan_muncul_untuk_pemilik_saja(): void
    {
        $task = $this->taskDraft();

        $this->actingAs($this->pemilik)
            ->get(route('tasks.show', $task))
            ->assertSee('Ajukan ke Approver');

        $this->actingAs($this->approver)
            ->get(route('tasks.show', $task))
            ->assertDontSee('Ajukan ke Approver');
    }

    public function test_panel_keputusan_hanya_muncul_untuk_approver_yang_ditunjuk(): void
    {
        $task = $this->taskMenunggu();

        $this->actingAs($this->approver)
            ->get(route('tasks.show', $task))
            ->assertSee('Keputusan Anda')
            ->assertSee('Minta Revisi');

        $this->actingAs($this->pemilik)
            ->get(route('tasks.show', $task))
            ->assertDontSee('Keputusan Anda');
    }

    public function test_pemilik_tanpa_approver_diberi_peringatan_di_halaman_detail(): void
    {
        $yatim = User::factory()->create();
        $task = Task::factory()->create(['created_by' => $yatim->id]);

        $this->actingAs($yatim)
            ->get(route('tasks.show', $task))
            ->assertSee('belum punya approver')
            ->assertDontSee('Ajukan ke Approver');
    }
}
