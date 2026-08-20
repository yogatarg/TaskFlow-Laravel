<?php

namespace App\Http\Requests;

/**
 * Aturannya identik dengan StoreTaskRequest, jadi cukup diwarisi.
 *
 * Dibuat sebagai kelas terpisah — bukan memakai StoreTaskRequest langsung — supaya
 * ketika nanti aturan simpan dan aturan ubah mulai berbeda (misal tenggat tidak boleh
 * dimundurkan setelah pernah diajukan), tempat menaruhnya sudah ada.
 */
class UpdateTaskRequest extends StoreTaskRequest
{
}
