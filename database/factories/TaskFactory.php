<?php

namespace Database\Factories;

use App\Enums\TaskLabel;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'label' => fake()->randomElement(TaskLabel::cases()),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'status' => TaskStatus::Draft,
            'deadline' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'created_by' => User::factory(),
        ];
    }

    /**
     * State per status. Dipakai test untuk menaruh task langsung di titik tertentu
     * dari state machine tanpa harus menjalankan seluruh alurnya.
     */
    public function berstatus(TaskStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function menungguApproval(): static
    {
        return $this->berstatus(TaskStatus::PendingApproval);
    }

    public function disetujui(): static
    {
        return $this->berstatus(TaskStatus::Approved);
    }

    public function ditolak(): static
    {
        return $this->berstatus(TaskStatus::Rejected);
    }

    public function dimintaRevisi(): static
    {
        return $this->berstatus(TaskStatus::RevisionRequested);
    }
}
