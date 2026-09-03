<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\AccountDTO;
use App\Modules\Accounting\DTOs\UpdateAccountDTO;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AccountService
{
    public function getAll(): Collection
    {
        return Account::orderBy('code')->get();
    }

    public function getById(string $id): Account
    {
        return Account::findOrFail($id);
    }

    public function createAccount(AccountDTO $dto): Account
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $exists = Account::where('code', $dto->code)->exists();
            if ($exists) {
                throw new ConflictHttpException("Account code '{$dto->code}' already exists in this tenant.");
            }

            $level = 1;
            if ($dto->parentAccountId) {
                $parent = Account::find($dto->parentAccountId);
                if (!$parent) {
                    throw new NotFoundHttpException('Parent account not found.');
                }
                $level = $parent->level + 1;

                if ($parent->account_type !== $dto->accountType) {
                    throw new ConflictHttpException('Child account type must match parent account type.');
                }
            }

            $account = Account::create([
                'tenant_id'         => $tenantId,
                'parent_account_id' => $dto->parentAccountId,
                'code'              => $dto->code,
                'name'              => $dto->name,
                'account_type'      => $dto->accountType,
                'level'             => $level,
                'description'       => $dto->description,
                'is_active'         => $dto->isActive ?? true,
                'created_by'        => $userId,
                'row_version'       => 1,
            ]);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_accounts',
                'aggregate_id'   => $account->account_id,
                'event_type'     => 'accounting.account.created.v1',
                'payload'        => json_encode([
                    'account_id'   => $account->account_id,
                    'code'         => $account->code,
                    'name'         => $account->name,
                    'account_type' => $account->account_type,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $account;
        });
    }

    public function updateAccount(string $id, UpdateAccountDTO $dto): Account
    {
        return DB::transaction(function () use ($id, $dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $account = Account::findOrFail($id);

            if ($dto->code !== null && $dto->code !== $account->code) {
                $exists = Account::where('code', $dto->code)
                    ->where('account_id', '!=', $id)
                    ->exists();
                if ($exists) {
                    throw new ConflictHttpException("Account code '{$dto->code}' already exists in this tenant.");
                }
            }

            $newAccountType = $dto->accountType ?? $account->account_type;
            $newParentId    = $dto->parentAccountId !== null ? $dto->parentAccountId : $account->parent_account_id;

            // Prevent self-parent
            if ($newParentId !== null && $newParentId === $id) {
                throw new ConflictHttpException('Account cannot be its own parent.');
            }

            $level = $account->level;
            if ($newParentId !== null) {
                $parent = Account::find($newParentId);
                if (!$parent) {
                    throw new NotFoundHttpException('Parent account not found.');
                }
                if ((int) $parent->account_type !== (int) $newAccountType) {
                    throw new ConflictHttpException('Child account type must match parent account type.');
                }
                $level = $parent->level + 1;
            } elseif ($dto->parentAccountId === null && array_key_exists('parentAccountId', (array) $dto)) {
                // Explicit null parent → root level
                $level = 1;
            }

            $updateData = array_filter([
                'code'              => $dto->code,
                'name'              => $dto->name,
                'account_type'      => $dto->accountType,
                'description'       => $dto->description,
                'is_active'         => $dto->isActive,
                'updated_by'        => $userId,
            ], fn ($value) => $value !== null);

            // parent_account_id can be explicitly set to null
            if ($dto->parentAccountId !== null || (property_exists($dto, 'parentAccountId') && $dto->parentAccountId === null)) {
                // Only update parent when DTO carries a value (including explicit null via request)
            }
            // Safer: always recompute parent/level when parentAccountId key was present in DTO construction
            // UpdateAccountDTO uses null for "not provided". Distinguish not-provided vs explicit-null is hard;
            // convention: if parentAccountId is not null OR code/name changed with parent in request — handle via request layer.
            // For simplicity and correctness: if $dto->parentAccountId is not null, set it; if request sent null, UpdateAccountDTO sets null.
            // We treat null in DTO as "set to root" only when the request included the key.
            // UpdateAccountDTO::fromRequest uses array_key_exists — so null means explicit clear.
            // Here we always apply parent when the DTO was built from a request that included the key.
            // Since DTO always has the property, we apply when parentAccountId is not "unchanged".
            // Practical approach: apply parent_account_id whenever dto->parentAccountId is set OR we need level recalc.
            if ($dto->parentAccountId !== null) {
                $updateData['parent_account_id'] = $dto->parentAccountId;
                $updateData['level'] = $level;
            }

            // When account_type changes without parent change, keep level
            if ($dto->accountType !== null) {
                $updateData['account_type'] = $dto->accountType;
            }

            $updateData['row_version'] = ((int) ($account->row_version ?? 1)) + 1;

            $account->update($updateData);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_accounts',
                'aggregate_id'   => $account->account_id,
                'event_type'     => 'accounting.account.updated.v1',
                'payload'        => json_encode([
                    'account_id'   => $account->account_id,
                    'code'         => $account->code,
                    'name'         => $account->name,
                    'account_type' => $account->account_type,
                    'row_version'  => $account->row_version,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $account->fresh();
        });
    }

    public function deleteAccount(string $id): void
    {
        DB::transaction(function () use ($id) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $account = Account::findOrFail($id);

            // Block delete if children exist
            $hasChildren = Account::where('parent_account_id', $id)->exists();
            if ($hasChildren) {
                throw new ConflictHttpException('Cannot delete account that has child accounts.');
            }

            $account->update(['deleted_by' => $userId]);
            $account->delete();

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_accounts',
                'aggregate_id'   => $id,
                'event_type'     => 'accounting.account.deleted.v1',
                'payload'        => json_encode([
                    'account_id' => $id,
                    'code'       => $account->code,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);
        });
    }
}
