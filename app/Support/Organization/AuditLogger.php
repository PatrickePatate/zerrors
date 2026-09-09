<?php

namespace App\Support\Organization;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(Organization $organization, ?User $user, string $action, ?string $subjectLabel = null, array $meta = []): AuditLog
    {
        return $organization->auditLogs()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_label' => $subjectLabel,
            'meta' => $meta,
        ]);
    }
}
