<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\Membership;
use App\Models\RkaPlatform\QuestionInstance;
use Illuminate\Auth\Access\AuthorizationException;

class TenantAccess
{
    public function membership(int $organizationId, int $userId): Membership
    {
        $membership = Membership::query()->where('organization_id', $organizationId)
            ->where('user_id', $userId)->where('active', true)->first();
        if (!$membership) {
            throw new AuthorizationException('Akses organisasi tidak diizinkan.');
        }
        return $membership;
    }

    public function hasRole(int $organizationId, int $userId, array $roles): bool
    {
        $current = $this->membership($organizationId, $userId)->roles ?? [];
        return count(array_intersect($current, $roles)) > 0;
    }

    public function requireRole(int $organizationId, int $userId, array $roles): void
    {
        if (!$this->hasRole($organizationId, $userId, $roles)) {
            throw new AuthorizationException('Kewenangan tidak mencukupi.');
        }
    }

    public function canReadInstance(QuestionInstance $instance, int $userId): bool
    {
        $this->membership($instance->organization_id, $userId);
        if ($instance->owner_user_id === $userId ||
            $this->hasRole($instance->organization_id, $userId, ['owner', 'admin'])) {
            return true;
        }
        return $instance->invitations()->where('invited_user_id', $userId)
            ->whereIn('status', ['invited', 'accepted', 'completed'])
            ->where('expires_at', '>', now())->exists();
    }

    public function requireReadInstance(QuestionInstance $instance, int $userId): void
    {
        if (!$this->canReadInstance($instance, $userId)) {
            throw new AuthorizationException('Tidak berhak membaca pertanyaan.');
        }
    }

    public function requireAnswerInstance(QuestionInstance $instance, int $userId): void
    {
        $this->membership($instance->organization_id, $userId);
        if ($instance->status !== 'open') {
            throw new AuthorizationException('Pertanyaan tidak lagi terbuka.');
        }
        if ($instance->owner_user_id === $userId ||
            $this->hasRole($instance->organization_id, $userId, ['owner', 'admin'])) {
            return;
        }
        $allowed = $instance->invitations()->where('invited_user_id', $userId)
            ->where('status', 'accepted')->where('expires_at', '>', now())->exists();
        if (!$allowed) {
            throw new AuthorizationException('Undangan belum diterima atau tidak berlaku.');
        }
    }
}
