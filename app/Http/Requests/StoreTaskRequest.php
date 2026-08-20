<?php

namespace App\Http\Requests;

use App\Enums\TaskLabel;
use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi ditangani TaskPolicy lewat authorizeResource() di TaskController.
        return true;
    }

    /**
     * Perhatikan: `status` dan `created_by` TIDAK divalidasi di sini karena memang
     * tidak diterima dari input. Status awal selalu Draft (default kolom), dan
     * pemiliknya selalu user yang sedang login.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'label' => ['required', Rule::enum(TaskLabel::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'description' => 'deskripsi',
            'deadline' => 'tenggat',
        ];
    }
}
