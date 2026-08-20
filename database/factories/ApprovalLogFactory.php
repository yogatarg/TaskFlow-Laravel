<?php

namespace Database\Factories;

use App\Enums\ApprovalAction;
use App\Models\ApprovalLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalLog>
 */
class ApprovalLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'actor_id' => User::factory(),
            'action' => ApprovalAction::Submit,
            'catatan' => null,
            'timestamp' => now(),
        ];
    }

    public function aksi(ApprovalAction $aksi, ?string $catatan = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => $aksi,
            'catatan' => $catatan,
        ]);
    }
}
