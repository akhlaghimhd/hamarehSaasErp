<?php

namespace Database\Seeders;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\TaxRateConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * FIN-P0-18 — Idempotent Iran-minimal CoA + default VAT rate for all tenants.
 * Coding law: child = parent code + sequential digit (1→11→111).
 * Does not invent fiscal periods (Org remains SoT for calendar).
 */
class FinanceDemoCoaSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('fin_acc_accounts') || ! Schema::hasTable('tenants')) {
            return;
        }

        $tenantIds = DB::table('tenants')->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();

        foreach ($tenantIds as $tenantId) {
            TenantContext::getInstance()->setTenantId($tenantId);
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

            $this->seedCoa($tenantId);
            $this->seedDefaultVat($tenantId);
        }
    }

    protected function seedCoa(string $tenantId): void
    {
        // code => [name, type, level, postable, normal_balance, parent_code|null]
        $tree = [
            '1'   => ['دارایی‌ها', Account::TYPE_ASSET, 1, false, Account::BALANCE_DEBIT, null],
            '11'  => ['دارایی جاری', Account::TYPE_ASSET, 2, false, Account::BALANCE_DEBIT, '1'],
            '111' => ['موجودی نقد', Account::TYPE_ASSET, 3, true, Account::BALANCE_DEBIT, '11'],
            '112' => ['حساب‌های دریافتنی', Account::TYPE_ASSET, 3, true, Account::BALANCE_DEBIT, '11'],
            '12'  => ['دارایی غیرجاری', Account::TYPE_ASSET, 2, false, Account::BALANCE_DEBIT, '1'],
            '121' => ['دارایی ثابت مشهود', Account::TYPE_ASSET, 3, true, Account::BALANCE_DEBIT, '12'],
            '122' => ['استهلاک انباشته', Account::TYPE_ASSET, 3, true, Account::BALANCE_CREDIT, '12'],

            '2'   => ['بدهی‌ها', Account::TYPE_LIABILITY, 1, false, Account::BALANCE_CREDIT, null],
            '21'  => ['بدهی جاری', Account::TYPE_LIABILITY, 2, false, Account::BALANCE_CREDIT, '2'],
            '211' => ['حساب‌های پرداختنی', Account::TYPE_LIABILITY, 3, true, Account::BALANCE_CREDIT, '21'],
            '212' => ['مالیات بر ارزش افزوده پرداختنی', Account::TYPE_LIABILITY, 3, true, Account::BALANCE_CREDIT, '21'],

            '3'  => ['حقوق صاحبان سهام', Account::TYPE_EQUITY, 1, false, Account::BALANCE_CREDIT, null],
            '31' => ['سرمایه', Account::TYPE_EQUITY, 2, true, Account::BALANCE_CREDIT, '3'],
            '32' => ['سود (زیان) انباشته', Account::TYPE_EQUITY, 2, true, Account::BALANCE_CREDIT, '3'],

            '4'  => ['درآمدها', Account::TYPE_REVENUE, 1, false, Account::BALANCE_CREDIT, null],
            '41' => ['فروش کالا/خدمات', Account::TYPE_REVENUE, 2, true, Account::BALANCE_CREDIT, '4'],

            '5'  => ['هزینه‌ها', Account::TYPE_EXPENSE, 1, false, Account::BALANCE_DEBIT, null],
            '51' => ['بهای تمام‌شده / هزینه عملیاتی', Account::TYPE_EXPENSE, 2, true, Account::BALANCE_DEBIT, '5'],
            '52' => ['هزینه استهلاک', Account::TYPE_EXPENSE, 2, true, Account::BALANCE_DEBIT, '5'],
        ];

        $idByCode = [];

        foreach ($tree as $code => $meta) {
            [$name, $type, $level, $postable, $normal, $parentCode] = $meta;

            $existing = Account::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('account_code', $code)
                ->first();

            if ($existing) {
                $idByCode[$code] = (string) $existing->account_id;
                continue;
            }

            $parentId = $parentCode !== null ? ($idByCode[$parentCode] ?? null) : null;

            $row = Account::create([
                'account_id'          => (string) Str::uuid(),
                'tenant_id'           => $tenantId,
                'parent_account_id'   => $parentId,
                'account_code'        => $code,
                'name'                => $name,
                'account_type'        => $type,
                'account_level'       => $level,
                'normal_balance'      => $normal,
                'is_control_account'  => ! $postable,
                'is_postable'         => $postable,
                'status'              => 1,
                'row_version'         => 1,
            ]);

            $idByCode[$code] = (string) $row->account_id;
        }
    }

    protected function seedDefaultVat(string $tenantId): void
    {
        if (! Schema::hasTable('fin_acc_tax_rate_configs')) {
            return;
        }

        $exists = TaxRateConfig::query()
            ->where('tax_code', 'VAT_STD')
            ->where('valid_from', '2020-01-01')
            ->exists();

        if ($exists) {
            return;
        }

        TaxRateConfig::create([
            'tax_rate_config_id' => (string) Str::uuid(),
            'tenant_id'          => $tenantId,
            'tax_code'           => 'VAT_STD',
            'name'               => 'ارزش افزوده استاندارد',
            'rate_percent'       => 10,
            'valid_from'         => '2020-01-01',
            'valid_to'           => null,
            'is_default'         => true,
            'status'             => 1,
            'row_version'        => 1,
        ]);
    }
}
