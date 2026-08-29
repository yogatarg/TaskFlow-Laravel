<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use Database\Factories\ApprovalLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Catatan permanen setiap keputusan approval. APPEND-ONLY: sekali ditulis, tidak
 * pernah diubah dan tidak pernah dihapus.
 *
 * Sifat append-only itu ditegakkan di tiga lapis:
 *   1. Tidak ada route, controller, atau form yang bisa mengubah/menghapusnya.
 *   2. Event `updating` dan `deleting` di bawah melempar exception.
 *   3. Kolom `timestamp` diisi sekali oleh ApprovalService, tidak ada updated_at.
 *
 * Kenapa serepot itu? Karena log yang bisa diubah bukan log. Begitu riwayat approval
 * bisa dirapikan belakangan, ia tidak lagi bisa dipakai untuk menjawab pertanyaan
 * "apa yang sebenarnya terjadi".
 */
class ApprovalLog extends Model
{
    /** @use HasFactory<ApprovalLogFactory> */
    use HasFactory;

    /**
     * Laravel tidak mengelola created_at/updated_at di sini. Waktu kejadian disimpan
     * di kolom `timestamp` sesuai spesifikasi.
     */
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'actor_id',
        'action',
        'catatan',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'timestamp' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $log) {
            throw new RuntimeException(
                'ApprovalLog bersifat append-only dan tidak boleh diubah (id: '.$log->getKey().').'
            );
        });

        static::deleting(function (self $log) {
            throw new RuntimeException(
                'ApprovalLog bersifat append-only dan tidak boleh dihapus (id: '.$log->getKey().').'
            );
        });
    }

    /**
     * withTrashed() disengaja: log bersifat append-only dan harus tetap terbaca utuh
     * meski task-nya sudah di-soft-delete. Tanpa ini $log->task menjadi null, dan
     * halaman Log akan menampilkan baris tanpa judul -- atau lebih buruk, error.
     *
     * Konsekuensinya judul task yang dihapus masih terlihat di halaman Log milik Admin.
     * Itu memang harga dari riwayat yang tidak bisa dibengkokkan: yang disembunyikan
     * adalah task-nya, bukan fakta bahwa dulu ia pernah diputuskan seseorang.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    /**
     * Siapa yang melakukan aksi ini.
     *
     * Inilah alasan approver tidak perlu disimpan di tabel tasks: kalau Admin mengganti
     * approver seorang user, task lama tidak berubah, tapi catatan siapa yang DULU
     * memproses tetap utuh di sini.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Urut kronologis; id dipakai sebagai pemecah seri kalau dua log berwaktu sama. */
    public function scopeKronologis(Builder $query): Builder
    {
        return $query->orderBy('timestamp')->orderBy('id');
    }

    public function scopeTerbaru(Builder $query): Builder
    {
        return $query->orderByDesc('timestamp')->orderByDesc('id');
    }
}
