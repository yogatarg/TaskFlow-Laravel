<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * Otorisasi "siapa yang boleh membuka form ini" sudah ditangani middleware `role:Admin`
     * di routes/web.php, jadi di sini cukup true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],

            // Approver harus user lain yang memang berhak menyetujui (Approver atau Admin).
            'approver_id' => [
                'nullable',
                Rule::exists('users', 'id')->whereIn('role', [
                    Role::Approver->value,
                    Role::Admin->value,
                ]),
            ],
        ];
    }

    /**
     * Aturan yang butuh melihat beberapa field sekaligus, atau butuh query lanjutan.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var User $target */
                $target = $this->route('user');

                // 1. Tidak boleh jadi approver bagi dirinya sendiri.
                if ($this->integer('approver_id') === $target->id) {
                    $validator->errors()->add('approver_id', 'User tidak boleh menjadi approver bagi dirinya sendiri.');
                }

                // 2. Cegah lingkaran langsung: A approver-nya B, sementara B approver-nya A.
                if ($this->filled('approver_id')) {
                    $calon = User::find($this->integer('approver_id'));

                    if ($calon && $calon->approver_id === $target->id) {
                        $validator->errors()->add('approver_id', 'Tidak bisa: user tersebut sudah dijadikan approver oleh yang bersangkutan (lingkaran approval).');
                    }
                }

                // 3. Admin tidak boleh menurunkan role dirinya sendiri — mencegah sistem
                //    kehilangan admin terakhir dan tidak ada lagi yang bisa mengatur role.
                if ($target->is($this->user()) && $this->input('role') !== Role::Admin->value) {
                    $validator->errors()->add('role', 'Anda tidak bisa menurunkan role akun Anda sendiri.');
                }

                // 4. User yang masih menjadi approver orang lain tidak boleh diturunkan
                //    menjadi role User, karena bawahannya jadi tidak punya approver yang sah.
                if ($this->input('role') === Role::User->value && $target->approvees()->exists()) {
                    $validator->errors()->add('role', 'User ini masih menjadi approver bagi user lain. Pindahkan dulu bawahannya sebelum menurunkan role.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'approver_id' => 'approver',
        ];
    }
}
