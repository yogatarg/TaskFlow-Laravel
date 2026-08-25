<?php

namespace Tests\Unit;

use App\Enums\ApprovalAction;
use App\Enums\TaskStatus;
use PHPUnit\Framework\TestCase;

/**
 * State machine adalah logika murni: tidak menyentuh database, HTTP, maupun session.
 * Karena itu diuji sebagai unit test yang mewarisi PHPUnit\Framework\TestCase langsung
 * (bukan Tests\TestCase), sehingga tidak perlu menyalakan aplikasi Laravel sama sekali.
 *
 * Feature test di tests/Feature sudah menguji alur approval lewat HTTP. Yang diuji di
 * sini adalah aturannya sendiri, terlepas dari bagaimana ia dipanggil.
 */
class TaskStatusTest extends TestCase
{
    public function test_transisi_yang_sah_sesuai_spesifikasi(): void
    {
        $this->assertTrue(TaskStatus::Draft->canTransitionTo(TaskStatus::PendingApproval));
        $this->assertTrue(TaskStatus::RevisionRequested->canTransitionTo(TaskStatus::PendingApproval));
        $this->assertTrue(TaskStatus::PendingApproval->canTransitionTo(TaskStatus::Approved));
        $this->assertTrue(TaskStatus::PendingApproval->canTransitionTo(TaskStatus::Rejected));
        $this->assertTrue(TaskStatus::PendingApproval->canTransitionTo(TaskStatus::RevisionRequested));
    }

    public function test_tidak_bisa_melompati_pengajuan(): void
    {
        // Draft langsung disetujui berarti approval-nya dilewati sama sekali.
        $this->assertFalse(TaskStatus::Draft->canTransitionTo(TaskStatus::Approved));
        $this->assertFalse(TaskStatus::Draft->canTransitionTo(TaskStatus::Rejected));
        $this->assertFalse(TaskStatus::RevisionRequested->canTransitionTo(TaskStatus::Approved));
    }

    public function test_status_terminal_tidak_punya_jalan_keluar(): void
    {
        foreach ([TaskStatus::Approved, TaskStatus::Rejected] as $terminal) {
            $this->assertTrue($terminal->isTerminal());
            $this->assertSame([], $terminal->transisiYangDiizinkan());

            foreach (TaskStatus::cases() as $tujuan) {
                $this->assertFalse(
                    $terminal->canTransitionTo($tujuan),
                    "{$terminal->value} seharusnya tidak bisa pindah ke {$tujuan->value}",
                );
            }
        }
    }

    public function test_tidak_ada_status_yang_bisa_pindah_ke_dirinya_sendiri(): void
    {
        foreach (TaskStatus::cases() as $status) {
            $this->assertFalse(
                $status->canTransitionTo($status),
                "{$status->value} seharusnya tidak bisa pindah ke dirinya sendiri",
            );
        }
    }

    public function test_hanya_status_non_terminal_yang_punya_transisi(): void
    {
        foreach (TaskStatus::cases() as $status) {
            $punyaTransisi = $status->transisiYangDiizinkan() !== [];

            $this->assertSame(
                $punyaTransisi,
                ! $status->isTerminal(),
                "{$status->value}: isTerminal() dan transisiYangDiizinkan() tidak sepakat",
            );
        }
    }

    public function test_yang_bisa_diedit_persis_yang_ada_di_tangan_pembuat(): void
    {
        // Dua konsep ini ditulis terpisah di enum, jadi keduanya bisa berbeda tanpa
        // disadari. Test ini mengunci keduanya tetap sejalan.
        foreach (TaskStatus::cases() as $status) {
            $this->assertSame(
                in_array($status, TaskStatus::diTanganPembuat(), strict: true),
                $status->isEditable(),
                "{$status->value}: isEditable() dan diTanganPembuat() tidak sepakat",
            );
        }
    }

    public function test_pending_approval_tidak_bisa_diedit_maupun_dianggap_di_tangan_pembuat(): void
    {
        $this->assertFalse(TaskStatus::PendingApproval->isEditable());
        $this->assertNotContains(TaskStatus::PendingApproval, TaskStatus::diTanganPembuat());
        $this->assertTrue(TaskStatus::PendingApproval->menungguKeputusan());
    }

    public function test_yang_bisa_disubmit_persis_yang_bisa_diedit(): void
    {
        foreach (TaskStatus::cases() as $status) {
            $this->assertSame(
                $status->isEditable(),
                $status->bisaDisubmit(),
                "{$status->value}: task yang bisa diedit seharusnya juga bisa diajukan",
            );
        }
    }

    public function test_daftar_terminal_cocok_dengan_is_terminal(): void
    {
        $this->assertSame(
            [TaskStatus::Approved, TaskStatus::Rejected],
            TaskStatus::terminal(),
        );
    }

    public function test_setiap_aksi_menuju_status_yang_benar(): void
    {
        $this->assertSame(TaskStatus::PendingApproval, ApprovalAction::Submit->statusTujuan());
        $this->assertSame(TaskStatus::Approved, ApprovalAction::Approve->statusTujuan());
        $this->assertSame(TaskStatus::Rejected, ApprovalAction::Reject->statusTujuan());
        $this->assertSame(TaskStatus::RevisionRequested, ApprovalAction::RequestRevision->statusTujuan());
    }

    public function test_catatan_wajib_hanya_untuk_penolakan_dan_permintaan_revisi(): void
    {
        $this->assertTrue(ApprovalAction::Reject->butuhCatatan());
        $this->assertTrue(ApprovalAction::RequestRevision->butuhCatatan());
        $this->assertFalse(ApprovalAction::Approve->butuhCatatan());
        $this->assertFalse(ApprovalAction::Submit->butuhCatatan());
    }

    public function test_hanya_submit_yang_dilakukan_pembuat_task(): void
    {
        $this->assertFalse(ApprovalAction::Submit->olehApprover());

        foreach ([ApprovalAction::Approve, ApprovalAction::Reject, ApprovalAction::RequestRevision] as $aksi) {
            $this->assertTrue($aksi->olehApprover());
        }
    }

    public function test_setiap_status_tujuan_aksi_benar_benar_bisa_dicapai(): void
    {
        // Setiap aksi harus punya setidaknya satu status asal yang mengizinkannya.
        // Aksi yang tidak bisa dicapai dari mana pun berarti kode mati.
        foreach (ApprovalAction::cases() as $aksi) {
            $bisaDicapai = false;

            foreach (TaskStatus::cases() as $asal) {
                if ($asal->canTransitionTo($aksi->statusTujuan())) {
                    $bisaDicapai = true;
                    break;
                }
            }

            $this->assertTrue($bisaDicapai, "Aksi {$aksi->value} tidak bisa dicapai dari status mana pun");
        }
    }
}
