<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\AccountDTO;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AccountService
{
    public function createAccount(AccountDTO $dto): Account
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            // بررسی یکتا بودن کد حساب در سطح مستأجر فعلی
            $exists = Account::where('code', $dto->code)->exists();
            if ($exists) {
                throw new ConflictHttpException("Account code '{$dto->code}' already exists in this tenant.");
            }

            // محاسبه سطح حساب (Level) در درخت کدینگ
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
}
