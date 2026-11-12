<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\RequestActivity;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestActivity>
 */
class RequestActivityFactory extends Factory
{
    protected $model = RequestActivity::class;

    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'user_id' => User::factory(),
            'from_status' => RequestStatus::Draft,
            'to_status' => RequestStatus::Submitted,
            'comment' => fake()->sentence(),
        ];
    }
}
