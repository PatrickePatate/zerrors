<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaultEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'level' => $this->level,
            'message' => $this->message,
            'environment' => $this->environment,
            'release' => $this->release,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
