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

                // 2. Cegah lingkaran approval sedalam apa pun, bukan hanya A<->B.
                //
                //    Rantai approver bisa bertingkat: staf -> supervisor -> manajer ->
                //    direktur. Semakin panjang rantainya, semakin mudah lingkaran
                //    terbentuk tanpa disadari -- misalnya A -> B -> C -> A. Kalau itu
                //    terjadi tidak ada yang error: task tetap bisa diajukan dan
                //    diputuskan. Yang hilang adalah maknanya -- tidak ada lagi puncak
                //    rantai, dan semua saling menyetujui secara melingkar tanpa ada
                //    yang benar-benar bertanggung jawab.
                if ($this->filled('approver_id') && $this->integer('approver_id') !== $target->id) {
                    if ($nama = $this->lingkaranTerbentukLewat($target, $this->integer('approver_id'))) {
                        $validator->errors()->add(
                            'approver_id',
                            "Tidak bisa: {$nama} berada di bawah {$target->name} dalam rantai approval, "
                            .'sehingga pilihan ini akan membentuk lingkaran.'
                        );
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

    /**
     * Menelusuri rantai approver ke atas mulai dari calon approver.
     *
     * Mengembalikan nama simpul tempat lingkaran terbentuk, atau null kalau rantainya
     * aman. Menetapkan $calonId sebagai approver bagi $target membentuk lingkaran bila
     * $target sendiri berada di suatu tempat pada rantai di atas $calonId.
     *
     * Seluruh pasangan id/approver_id diambil sekali saja, lalu ditelusuri di memori.
     * Menelusurinya lewat query per tingkat berarti satu query untuk tiap tingkat
     * rantai, dan tabel user pada aplikasi seperti ini memang kecil.
     *
     * $sudahDilewati juga menjaga dari data yang sudah terlanjur melingkar sebelum
     * aturan ini ada -- tanpa itu, penelusuran tidak akan pernah berhenti.
     */
    private function lingkaranTerbentukLewat(User $target, int $calonId): ?string
    {
        $rantai = User::pluck('approver_id', 'id');
        $nama = User::pluck('name', 'id');

        $sudahDilewati = [];
        $kursor = $calonId;

        while ($kursor !== null) {
            if ($kursor === $target->id) {
                return $nama[$calonId] ?? 'User tersebut';
            }

            if (in_array($kursor, $sudahDilewati, true)) {
                break;
            }

            $sudahDilewati[] = $kursor;
            $kursor = $rantai[$kursor] ?? null;
        }

        return null;
    }
}
