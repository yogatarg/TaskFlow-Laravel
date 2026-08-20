<?php

namespace App\Models;

use App\Enums\TaskLabel;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * `status` dan `created_by` sengaja tidak ikut fillable.
     *
     * - `status` hanya boleh berpindah lewat state machine (ApprovalService, tahap 3),
     *   tidak lewat form edit. Kalau ia fillable, user bisa mengirim status=Approved
     *   dari form dan melompati approval sama sekali.
     * - `created_by` ditetapkan dari user yang sedang login, bukan dari input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'label',
        'priority',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'label' => TaskLabel::class,
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'deadline' => 'date',
        ];
    }

    /** Pembuat task. Dari sinilah approver-nya ditentukan: $task->creator->approver. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Approver yang berlaku untuk task ini SAAT INI.
     *
     * Sengaja dihitung, bukan disimpan: kalau approver pembuatnya diganti Admin,
     * task lama otomatis ikut mengarah ke approver baru. Siapa yang benar-benar
     * memproses approval tetap terekam permanen di ApprovalLog.actor_id (tahap 4).
     */
    public function approver(): ?User
    {
        return $this->creator?->approver;
    }

    /** Task milik user tertentu. */
    public function scopeMilik(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->id);
    }

    public function scopeBerstatus(Builder $query, TaskStatus ...$status): Builder
    {
        return $query->whereIn('status', array_map(fn (TaskStatus $s) => $s->value, $status));
    }

    /**
     * Task yang harus disetujui oleh $approver: milik semua bawahannya
     * (users.approver_id = $approver->id).
     */
    public function scopeUntukApprover(Builder $query, User $approver): Builder
    {
        return $query->whereHas('creator', fn (Builder $q) => $q->where('approver_id', $approver->id));
    }

    public function sudahLewatTenggat(): bool
    {
        return $this->deadline !== null
            && $this->deadline->isPast()
            && $this->status !== TaskStatus::Approved;
    }
}
