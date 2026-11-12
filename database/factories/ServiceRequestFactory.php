<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['hardware', 'software', 'access', 'facilities', 'general']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => RequestStatus::Draft,
            'requester_id' => User::factory()->employee(),
            'assigned_to' => null,
        ];
    }

    public function status(RequestStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
        ]);
    }

    public function assignedTo(User $assignee): static
    {
        return $this->state(fn () => [
            'assigned_to' => $assignee->id,
            'status' => RequestStatus::Assigned,
        ]);
    }
}
