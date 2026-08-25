<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Models\ApprovalLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ User

    public function test_user_melihat_dashboard_user(): void
    {
        $user = User::factory()->create(['name' => 'Sari Biasa']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.user')
            ->assertSee('Halo, Sari Biasa')
            ->assertSee('In Progress');
    }

    public function test_hitungan_status_di_dashboard_user_benar(): void
    {
        $user = User::factory()->create();

        Task::factory()->count(2)->create(['created_by' => $user->id]);                    // Draft
        Task::factory()->dimintaRevisi()->create(['created_by' => $user->id]);             // Revision Requested
        Task::factory()->menungguApproval()->count(3)->create(['created_by' => $user->id]);
        Task::factory()->disetujui()->count(4)->create(['created_by' => $user->id]);
        Task::factory()->ditolak()->create(['created_by' => $user->id]);

        // Task orang lain tidak boleh ikut terhitung.
        Task::factory()->count(5)->create();

        $hitungan = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('hitungan');

        $this->assertSame(11, (int) $hitungan->total);
        $this->assertSame(3, (int) $hitungan->pending);
        $this->assertSame(3, (int) $hitungan->dikerjakan); // 2 Draft + 1 Revision Requested
        $this->assertSame(4, (int) $hitungan->selesai);
        $this->assertSame(1, (int) $hitungan->ditolak);

        // Kelima angka harus berjumlah total -- tidak ada task yang luput dihitung.
        $this->assertSame(
            (int) $hitungan->total,
            (int) $hitungan->pending + (int) $hitungan->dikerjakan
                + (int) $hitungan->selesai + (int) $hitungan->ditolak,
        );
    }

    public function test_pending_approval_tidak_ikut_terhitung_sebagai_in_progress(): void
    {
        $user = User::factory()->create();
        Task::factory()->menungguApproval()->create(['created_by' => $user->id]);

        $hitungan = $this->actingAs($user)->get(route('dashboard'))->viewData('hitungan');

        $this->assertSame(1, (int) $hitungan->pending);
        $this->assertSame(0, (int) $hitungan->dikerjakan);
    }

    public function test_daftar_mendekati_tenggat_mengabaikan_task_yang_sudah_final(): void
    {
        $user = User::factory()->create();

        $dekat = Task::factory()->create([
            'created_by' => $user->id,
            'title' => 'Tenggat lusa',
            'deadline' => now()->addDays(2),
        ]);
        Task::factory()->disetujui()->create([
            'created_by' => $user->id,
            'title' => 'Sudah disetujui',
            'deadline' => now()->addDay(),
        ]);
        Task::factory()->create([
            'created_by' => $user->id,
            'title' => 'Masih lama',
            'deadline' => now()->addMonths(2),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee($dekat->title)
            ->assertDontSee('Sudah disetujui')
            ->assertDontSee('Masih lama');
    }

    public function test_user_tanpa_approver_diberi_peringatan(): void
    {
        $tanpa = User::factory()->create();
        $punya = User::factory()->bawahanDari(User::factory()->approver()->create())->create();

        $this->actingAs($tanpa)->get(route('dashboard'))->assertSee('belum punya approver');
        $this->actingAs($punya)->get(route('dashboard'))->assertDontSee('belum punya approver');
    }

    public function test_user_hanya_melihat_aktivitas_task_miliknya(): void
    {
        $approver = User::factory()->approver()->create();
        $user = User::factory()->bawahanDari($approver)->create();
        $orangLain = User::factory()->bawahanDari($approver)->create();

        $milikSaya = Task::factory()->menungguApproval()->create(['created_by' => $user->id, 'title' => 'Task saya']);
        $milikOrang = Task::factory()->menungguApproval()->create(['created_by' => $orangLain->id, 'title' => 'Task orang']);

        $this->actingAs($approver)->post(route('approvals.approve', $milikSaya), ['catatan' => '']);
        $this->actingAs($approver)->post(route('approvals.approve', $milikOrang), ['catatan' => '']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('Task saya')
            ->assertDontSee('Task orang');
    }

    // -------------------------------------------------------------- Approver

    public function test_approver_melihat_dashboard_approver(): void
    {
        $approver = User::factory()->approver()->create();

        $this->actingAs($approver)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.approver')
            ->assertSee('Menunggu Approval')
            ->assertSee('Disetujui Hari Ini');
    }

    public function test_jumlah_menunggu_hanya_menghitung_task_bawahannya(): void
    {
        $approver = User::factory()->approver()->create();
        $bawahan = User::factory()->bawahanDari($approver)->create();

        Task::factory()->menungguApproval()->count(2)->create(['created_by' => $bawahan->id]);
        Task::factory()->menungguApproval()->count(3)->create(); // bukan bawahannya
        Task::factory()->create(['created_by' => $bawahan->id]); // masih draft

        $this->assertSame(2, $this->actingAs($approver)->get(route('dashboard'))->viewData('menunggu'));
    }

    public function test_disetujui_hari_ini_dihitung_dari_log_bukan_status(): void
    {
        $approver = User::factory()->approver()->create();
        $bawahan = User::factory()->bawahanDari($approver)->create();

        $task = Task::factory()->menungguApproval()->create(['created_by' => $bawahan->id]);
        $this->actingAs($approver)->post(route('approvals.approve', $task), ['catatan' => '']);

        // Persetujuan kemarin oleh approver yang sama: tidak boleh ikut terhitung.
        ApprovalLog::factory()->create([
            'task_id' => Task::factory()->disetujui()->create(['created_by' => $bawahan->id])->id,
            'actor_id' => $approver->id,
            'action' => ApprovalAction::Approve,
            'timestamp' => now()->subDay(),
        ]);

        // Penolakan hari ini: bukan persetujuan, jadi juga tidak terhitung.
        $ditolak = Task::factory()->menungguApproval()->create(['created_by' => $bawahan->id]);
        $this->actingAs($approver)->post(route('approvals.reject', $ditolak), ['catatan' => 'Tidak sesuai.']);

        $this->assertSame(1, $this->actingAs($approver)->get(route('dashboard'))->viewData('disetujuiHariIni'));
    }

    public function test_riwayat_approver_hanya_berisi_keputusannya_sendiri(): void
    {
        $approver = User::factory()->approver()->create();
        $approverLain = User::factory()->approver()->create();
        $bawahan = User::factory()->bawahanDari($approver)->create();
        $bawahanLain = User::factory()->bawahanDari($approverLain)->create();

        $punyaSaya = Task::factory()->menungguApproval()->create(['created_by' => $bawahan->id, 'title' => 'Diputuskan saya']);
        $punyaDia = Task::factory()->menungguApproval()->create(['created_by' => $bawahanLain->id, 'title' => 'Diputuskan dia']);

        $this->actingAs($approver)->post(route('approvals.approve', $punyaSaya), ['catatan' => '']);
        $this->actingAs($approverLain)->post(route('approvals.approve', $punyaDia), ['catatan' => '']);

        $this->actingAs($approver)
            ->get(route('dashboard'))
            ->assertSee('Diputuskan saya')
            ->assertDontSee('Diputuskan dia');
    }

    // ----------------------------------------------------------------- Admin

    public function test_admin_melihat_dashboard_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.admin')
            ->assertSee('Total User')
            ->assertSee('Task Dibuat per Bulan');
    }

    public function test_admin_melihat_hitungan_seluruh_organisasi(): void
    {
        $admin = User::factory()->admin()->create();
        $approver = User::factory()->approver()->create();
        [$sari, $rina] = User::factory()->count(2)->bawahanDari($approver)->create()->all();

        // created_by ditetapkan eksplisit. Tanpa itu, TaskFactory membuat user baru
        // untuk setiap task lewat 'created_by' => User::factory(), dan angka totalUser
        // ikut membengkak tanpa disadari.
        Task::factory()->count(4)->create(['created_by' => $sari->id]);
        Task::factory()->menungguApproval()->count(2)->create(['created_by' => $rina->id]);

        $respons = $this->actingAs($admin)->get(route('dashboard'));

        $this->assertSame(4, $respons->viewData('totalUser'));   // admin + approver + 2 bawahan
        $this->assertSame(6, $respons->viewData('totalTask'));
        $this->assertSame(2, $respons->viewData('menungguSemua'));
    }

    public function test_admin_diberi_tahu_berapa_user_yang_belum_punya_approver(): void
    {
        $admin = User::factory()->admin()->create();          // approver_id null
        $approver = User::factory()->approver()->create();    // approver_id null
        User::factory()->bawahanDari($approver)->create();    // punya approver

        $this->assertSame(2, $this->actingAs($admin)->get(route('dashboard'))->viewData('tanpaApprover'));
    }

    public function test_grafik_bulanan_selalu_berisi_dua_belas_bulan(): void
    {
        $admin = User::factory()->admin()->create();

        Task::factory()->count(3)->create(['created_at' => now()]);
        Task::factory()->create(['created_at' => now()->subMonths(2)]);
        // Di luar jendela 12 bulan -- tidak boleh ikut terhitung.
        Task::factory()->create(['created_at' => now()->subMonths(20)]);

        $grafik = $this->actingAs($admin)->get(route('dashboard'))->viewData('grafikBulanan');

        $this->assertCount(12, $grafik);
        $this->assertSame(4, array_sum($grafik));
        $this->assertSame(3, $grafik[now()->translatedFormat('M Y')]);
        $this->assertSame(1, $grafik[now()->subMonths(2)->translatedFormat('M Y')]);

        // Bulan tanpa task tetap muncul sebagai nol, bukan hilang dari grafik.
        $this->assertSame(0, $grafik[now()->subMonth()->translatedFormat('M Y')]);
    }

    public function test_dashboard_admin_tetap_bisa_dirender_saat_belum_ada_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum ada aktivitas approval sama sekali.');
    }
}
