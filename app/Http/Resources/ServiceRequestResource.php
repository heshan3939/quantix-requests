<?php

namespace App\Http\Resources;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status instanceof RequestStatus ? $this->status->value : (string) $this->status,
            'requester' => new UserSummaryResource($this->whenLoaded('requester')),
            'assignee' => new UserSummaryResource($this->whenLoaded('assignee')),
            'comments' => RequestCommentResource::collection($this->whenLoaded('comments')),
            'activities' => RequestActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
