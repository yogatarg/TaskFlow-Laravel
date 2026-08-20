<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * Catatan: `role` dan `approver_id` sengaja TIDAK dimasukkan ke sini.
     * Keduanya hanya boleh diubah lewat Admin\UserController, bukan lewat
     * form registrasi atau profil, supaya user tidak bisa menaikkan role-nya
     * sendiri dengan menyelipkan field tambahan di request.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /** Atasan yang menyetujui task milik user ini. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approver_id');
    }

    /** User-user yang task-nya harus disetujui oleh user ini. */
    public function approvees(): HasMany
    {
        return $this->hasMany(self::class, 'approver_id');
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, strict: true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isApprover(): bool
    {
        return $this->role === Role::Approver;
    }
}
