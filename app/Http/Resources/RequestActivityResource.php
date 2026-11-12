<?php

namespace App\Http\Resources;

use App\Enums\RequestStatus;
use App\Models\RequestActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestActivity
 */
class RequestActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_request_id' => $this->service_request_id,
            'from_status' => $this->from_status instanceof RequestStatus ? $this->from_status->value : (string) $this->from_status,
            'to_status' => $this->to_status instanceof RequestStatus ? $this->to_status->value : (string) $this->to_status,
            'comment' => $this->comment,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
