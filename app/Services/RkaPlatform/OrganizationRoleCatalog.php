<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationRoleCatalog
{
    public const BASE = [
        'owner' => 'Pemilik Organisasi',
        'admin' => 'Administrator',
        'finance' => 'Keuangan',
        'reviewer' => 'Pemeriksa',
        'editor' => 'Editor',
        'publisher' => 'Penerbit',
        'contributor' => 'Kontributor',
        'unit_manager' => 'Penanggung Jawab Unit',
    ];

    public function __construct(private TenantAccess $access, private AuditTrail $audit) {}

    public function roles(int $orgId): array
    {
        $org = Organization::findOrFail($orgId);
        $custom = $org->settings['workflow_roles'] ?? [];
        if (!is_array($custom)) $custom = [];
        return array_merge(self::BASE, array_filter($custom, fn ($k) => !array_key_exists($k, self::BASE), ARRAY_FILTER_USE_KEY));
    }

    public function add(int $orgId, int $actor, string $code, string $label): void
    {
        $this->access->requireRole($orgId, $actor, ['owner','admin']);
        $code = trim($code);
        $label = trim($label);
        if (!preg_match('/^[a-z][a-z0-9_-]{1,49}$/', $code) ||
            array_key_exists($code, self::BASE) ||
            mb_strlen($label) < 3 || mb_strlen($label) > 100 ||
            preg_match('/[[:cntrl:]]/u', $label)) {
            throw ValidationException::withMessages(['workflowRole' => 'Kode/label role tidak valid atau berbenturan dengan peran sistem.']);
        }
        DB::transaction(function () use ($orgId, $actor, $code, $label) {
            $org = Organization::whereKey($orgId)->lockForUpdate()->firstOrFail();
            $settings = $org->settings ?? [];
            $roles = $settings['workflow_roles'] ?? [];
            if (!is_array($roles)) $roles = [];
            if (isset($roles[$code]) || count($roles) >= 50) {
                throw ValidationException::withMessages(['workflowRole' => 'Kode role sudah digunakan atau batas 50 role tercapai.']);
            }
            $roles[$code] = $label;
            $settings['workflow_roles'] = $roles;
            $org->update(['settings' => $settings]);
            $this->audit->record($orgId, $actor, 'organization_role.added', 'organization', $orgId,
                ['role' => $code]);
        });
    }

    public function validateAssignable(int $orgId, string $role): void
    {
        if ($role === 'owner' || !array_key_exists($role, $this->roles($orgId))) {
            throw new AuthorizationException('Role belum terdaftar di organisasi atau tidak dapat diberikan.');
        }
    }

    public function validateApprovalRoles(int $orgId, array $steps): void
    {
        $available = $this->roles($orgId);
        foreach ($steps as $step) {
            foreach ($step['roles'] as $role) {
                if (!array_key_exists($role, $available)) {
                    throw ValidationException::withMessages(['policyStepsJson' => 'Role pada kebijakan belum terdaftar: '.$role]);
                }
            }
        }
    }
}
