<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\AuditEvent;

class AuditTrail
{
    public function record(int $orgId, ?int $actorId, string $action, string $subjectType, int $subjectId, array $payload = []): void
    {
        AuditEvent::create([
            'organization_id' => $orgId,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
        ]);
    }
}
