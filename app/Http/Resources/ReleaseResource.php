<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'deployed_at' => $this->deployed_at,
            'commit_sha' => $this->commit_sha,
            'commit_url' => $this->commit_url,
            'commit_message' => $this->commit_message,
            'commit_author' => $this->commit_author,
        ];
    }
}
