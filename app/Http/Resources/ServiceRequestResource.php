<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'status' => $this->status?->value ?? $this->status,
            'requester' => new UserSummaryResource($this->whenLoaded('requester')),
            'assignee' => new UserSummaryResource($this->whenLoaded('assignee')),
            'comments' => RequestCommentResource::collection($this->whenLoaded('comments')),
            'activities' => RequestActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

