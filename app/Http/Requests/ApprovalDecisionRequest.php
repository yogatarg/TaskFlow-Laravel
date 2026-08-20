<?php

namespace App\Http\Requests;

use App\Enums\ApprovalAction;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi catatan approver.
 *
 * Aturannya berbeda tergantung aksi: menyetujui boleh tanpa catatan, tapi menolak dan
 * meminta revisi wajib menyertakan alasan. Aksi diambil dari route, bukan dari input,
 * supaya user tidak bisa menghindari kewajiban catatan dengan mengganti field tersembunyi.
 */
class ApprovalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi ditangani middleware `can:decide,task` di ApprovalController.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'catatan' => [
                $this->aksi()->butuhCatatan() ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'catatan.required' => $this->aksi() === ApprovalAction::Reject
                ? 'Sertakan alasan penolakan supaya pembuat task tahu duduk perkaranya.'
                : 'Sertakan catatan revisi supaya pembuat task tahu apa yang harus diperbaiki.',
        ];
    }

    /**
     * Aksi ditentukan oleh route yang dipanggil, bukan oleh input.
     */
    public function aksi(): ApprovalAction
    {
        return match ($this->route()->getName()) {
            'approvals.approve' => ApprovalAction::Approve,
            'approvals.reject' => ApprovalAction::Reject,
            'approvals.request-revision' => ApprovalAction::RequestRevision,
        };
    }

    public function catatan(): ?string
    {
        $catatan = trim((string) $this->input('catatan'));

        return $catatan === '' ? null : $catatan;
    }
}
