<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaultIssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'culprit' => $this->culprit,
            'level' => $this->level,
            'status' => $this->status,
            'times_seen' => $this->times_seen,
            'first_seen_at' => $this->first_seen_at,
            'last_seen_at' => $this->last_seen_at,
            'assigned_to_user_id' => $this->assigned_to_user_id,
        ];
    }
}
