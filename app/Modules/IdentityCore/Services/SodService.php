<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantSodRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SodService
{
    public function evaluateRoleSet(string $tenantId, array $proposedRoleIds): array
    {
        $roleIds = array_values(array_unique(array_filter($proposedRoleIds)));
        if (count($roleIds) < 2) {
            return ['conflicts' => [], 'has_block' => false, 'has_warn' => false];
        }

        $this->reactivateExpired($tenantId);

        $rules = TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();

        $set = array_flip($roleIds);
        $conflicts = [];
        $hasBlock = false;
        $hasWarn = false;

        foreach ($rules as $rule) {
            $a = (string) $rule->role_a_id;
            $b = (string) $rule->role_b_id;
            if (isset($set[$a]) && isset($set[$b])) {
                $conflicts[] = [
                    'sod_rule_id' => $rule->sod_rule_id,
                    'code'        => $rule->code,
                    'name'        => $rule->name,
                    'severity'    => (int) $rule->severity,
                    'enforcement' => $rule->enforcement,
                    'role_a_id'   => $a,
                    'role_b_id'   => $b,
                ];
                if ($rule->enforcement === TenantSodRule::ENFORCEMENT_BLOCK) {
                    $hasBlock = true;
                } else {
                    $hasWarn = true;
                }
            }
        }

        return [
            'conflicts' => $conflicts,
            'has_block' => $hasBlock,
            'has_warn'  => $hasWarn,
        ];
    }

    public function assertAssignable(string $tenantId, array $proposedRoleIds): array
    {
        $result = $this->evaluateRoleSet($tenantId, $proposedRoleIds);
        if ($result['has_block']) {
            $names = array_map(
                static fn (array $c) => (string) ($c['name'] ?? $c['code'] ?? 'قانون'),
                array_filter($result['conflicts'], static fn (array $c) => ($c['enforcement'] ?? '') === TenantSodRule::ENFORCEMENT_BLOCK)
            );
            throw new HttpException(
                422,
                'تخصیص نقش به‌خاطر تعارض تفکیک وظایف مجاز نیست: '.implode('، ', $names)
            );
        }

        return $result['conflicts'];
    }

    public function listRules(array $filters = []): Collection
    {
        $tenantId = $this->tenantId();
        $this->reactivateExpired($tenantId);

        $q = TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'roleA:tenant_role_id,code,name',
                'roleB:tenant_role_id,code,name',
            ]);

        $onlyTrashed = filter_var($filters['only_trashed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($onlyTrashed) {
            $q->onlyTrashed();
        } else {
            $q->whereNull('deleted_at');
        }

        $status = $filters['status'] ?? null;
        if ($status === 'active' && !$onlyTrashed) {
            $q->where('is_active', true);
        } elseif ($status === 'inactive' && !$onlyTrashed) {
            $q->where('is_active', false);
        }

        return $q->orderByDesc('severity')->orderBy('code')->get();
    }

    public function createRule(array $data): TenantSodRule
    {
        $tenantId = $this->tenantId();
        $roleA = (string) $data['role_a_id'];
        $roleB = (string) $data['role_b_id'];

        if ($roleA === $roleB) {
            throw new HttpException(422, 'دو نقش یک قانون نمی‌توانند یکسان باشند.');
        }

        if (strcmp($roleA, $roleB) > 0) {
            [$roleA, $roleB] = [$roleB, $roleA];
        }

        TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleA)
            ->whereNull('deleted_at')
            ->firstOrFail();
        TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleB)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $code = isset($data['code']) && trim((string) $data['code']) !== ''
            ? trim((string) $data['code'])
            : 'sod-'.Str::lower(Str::random(8));

        $enforcement = strtoupper((string) ($data['enforcement'] ?? TenantSodRule::ENFORCEMENT_BLOCK));
        if (!in_array($enforcement, [TenantSodRule::ENFORCEMENT_BLOCK, TenantSodRule::ENFORCEMENT_WARN], true)) {
            $enforcement = TenantSodRule::ENFORCEMENT_BLOCK;
        }
        $severity = max(1, min(4, (int) ($data['severity'] ?? 3)));

        return DB::transaction(function () use ($tenantId, $roleA, $roleB, $code, $data, $enforcement, $severity) {
            $exists = TenantSodRule::query()
                ->where('tenant_id', $tenantId)
                ->where('role_a_id', $roleA)
                ->where('role_b_id', $roleB)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                throw new HttpException(422, 'قانون برای این جفت نقش از قبل وجود دارد.');
            }

            return TenantSodRule::create([
                'sod_rule_id'    => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'role_a_id'      => $roleA,
                'role_b_id'      => $roleB,
                'code'           => $code,
                'name'           => trim((string) $data['name']),
                'description'    => isset($data['description']) ? trim((string) $data['description']) : null,
                'severity'       => $severity,
                'enforcement'    => $enforcement,
                'is_active'      => (bool) ($data['is_active'] ?? true),
                'inactive_until' => null,
            ]);
        });
    }

    public function updateRule(string $sodRuleId, array $data): TenantSodRule
    {
        $tenantId = $this->tenantId();
        $rule = TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->where('sod_rule_id', $sodRuleId)
            ->firstOrFail();

        if (array_key_exists('name', $data) && trim((string) $data['name']) !== '') {
            $rule->name = trim((string) $data['name']);
        }
        if (array_key_exists('description', $data)) {
            $rule->description = $data['description'] !== null
                ? trim((string) $data['description'])
                : null;
        }
        if (array_key_exists('severity', $data) && $data['severity'] !== null) {
            $rule->severity = max(1, min(4, (int) $data['severity']));
        }
        if (array_key_exists('enforcement', $data) && $data['enforcement'] !== null) {
            $enf = strtoupper((string) $data['enforcement']);
            if (in_array($enf, [TenantSodRule::ENFORCEMENT_BLOCK, TenantSodRule::ENFORCEMENT_WARN], true)) {
                $rule->enforcement = $enf;
            }
        }
        if (array_key_exists('is_active', $data)) {
            $rule->is_active = (bool) $data['is_active'];
            if ($rule->is_active) {
                $rule->inactive_until = null;
            }
        }
        if (array_key_exists('inactive_until', $data)) {
            $until = $data['inactive_until'];
            if ($until === null || $until === '') {
                $rule->inactive_until = null;
            } else {
                $rule->inactive_until = $until;
                $rule->is_active = false;
            }
        }

        $rule->row_version = (int) $rule->row_version + 1;
        $rule->save();

        return $rule->load(['roleA:tenant_role_id,code,name', 'roleB:tenant_role_id,code,name']);
    }

    public function softDeleteRule(string $sodRuleId): void
    {
        $tenantId = $this->tenantId();
        $rule = TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->where('sod_rule_id', $sodRuleId)
            ->firstOrFail();
        $rule->delete();
    }

    public function restoreRule(string $sodRuleId): TenantSodRule
    {
        $tenantId = $this->tenantId();
        $rule = TenantSodRule::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('sod_rule_id', $sodRuleId)
            ->firstOrFail();

        if (!$rule->trashed()) {
            throw new HttpException(422, 'این قانون حذف نشده است.');
        }

        $pairExists = TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->where('role_a_id', $rule->role_a_id)
            ->where('role_b_id', $rule->role_b_id)
            ->whereNull('deleted_at')
            ->exists();
        if ($pairExists) {
            throw new HttpException(422, 'جفت نقش مشابهی هم‌اکنون فعال است؛ بازگردانی ممکن نیست.');
        }

        $rule->restore();
        $rule->is_active = true;
        $rule->inactive_until = null;
        $rule->save();

        return $rule->load(['roleA:tenant_role_id,code,name', 'roleB:tenant_role_id,code,name']);
    }

    private function reactivateExpired(string $tenantId): void
    {
        if (!Schema::hasColumn('tenant_sod_rules', 'inactive_until')) {
            return;
        }

        $expired = TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', false)
            ->whereNotNull('inactive_until')
            ->where('inactive_until', '<=', now())
            ->whereNull('deleted_at')
            ->get(['sod_rule_id', 'code', 'name', 'inactive_until']);

        if ($expired->isEmpty()) {
            return;
        }

        $ids = $expired->pluck('sod_rule_id')->all();
        TenantSodRule::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('sod_rule_id', $ids)
            ->update([
                'is_active' => true,
                'inactive_until' => null,
                'updated_at' => now(),
            ]);

        $this->emitSystemNotification($tenantId, [
            'message_type' => 'sod.rule.auto_reactivated',
            'channel'      => 'system',
            'title'        => 'فعال‌سازی خودکار قانون تفکیک وظایف',
            'body'         => count($ids) === 1
                ? 'مهلت غیرفعال‌سازی موقت قانون «'.($expired->first()->name ?? '').'» به پایان رسید و قانون دوباره فعال شد.'
                : count($ids).' قانون تفکیک وظایف پس از پایان مهلت غیرفعال‌سازی موقت، خودکار فعال شدند.',
            'rules'        => $expired->map(static fn ($r) => [
                'sod_rule_id' => (string) $r->sod_rule_id,
                'code'        => $r->code,
                'name'        => $r->name,
            ])->values()->all(),
            'reactivated_at' => now()->toIso8601String(),
        ]);
    }

    private function emitSystemNotification(string $tenantId, array $payload): void
    {
        if (!Schema::hasTable('event_outbox')) {
            return;
        }

        $payload['recipient_user_ids'] = $this->resolveSystemNotificationRecipients($tenantId);

        try {
            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'tenant_sod_rule',
                'aggregate_id'   => (string) ($payload['rules'][0]['sod_rule_id'] ?? $tenantId),
                'event_type'     => 'identity.system_notification.v1',
                'payload'        => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('system_notification outbox failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /** @return list<string> */
    private function resolveSystemNotificationRecipients(string $tenantId): array
    {
        $ids = [];

        if (Schema::hasTable('tenant_permissions') && Schema::hasTable('tenant_role_permissions') && Schema::hasTable('tenant_user_roles')) {
            $permId = DB::table('tenant_permissions')
                ->where('tenant_id', $tenantId)
                ->where('code', 'identity.system_notification.receive')
                ->whereNull('deleted_at')
                ->value('tenant_permission_id');

            if ($permId) {
                $roleIds = DB::table('tenant_role_permissions')
                    ->where('tenant_id', $tenantId)
                    ->where('tenant_permission_id', $permId)
                    ->pluck('tenant_role_id')
                    ->all();

                if ($roleIds !== []) {
                    $ids = DB::table('tenant_user_roles as tur')
                        ->join('tenant_users as tu', 'tu.tenant_user_id', '=', 'tur.tenant_user_id')
                        ->where('tur.tenant_id', $tenantId)
                        ->whereIn('tur.tenant_role_id', $roleIds)
                        ->whereNull('tu.deleted_at')
                        ->where('tu.status', 1)
                        ->pluck('tu.user_id')
                        ->map(fn ($id) => (string) $id)
                        ->unique()
                        ->values()
                        ->all();
                }
            }
        }

        if ($ids === [] && Schema::hasTable('tenant_users')) {
            $ids = DB::table('tenant_users')
                ->where('tenant_id', $tenantId)
                ->where('is_owner', true)
                ->whereNull('deleted_at')
                ->where('status', 1)
                ->pluck('user_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $ids;
    }

    private function tenantId(): string
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        if (!$tenantId) {
            throw new HttpException(400, 'شناسه سازمان در درخواست موجود نیست.');
        }

        return (string) $tenantId;
    }
}
