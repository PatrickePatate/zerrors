<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class FaultEventDetailResource extends FaultEventResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'culprit' => $this->culprit,
            'transaction' => $this->transaction,
            'server_name' => $this->server_name,
            'payload' => $this->payload,
        ];
    }
}
