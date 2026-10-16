<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal number format LOCKED:
 * {DR|JE}-{REF3}-{YY}-{#####}
 * REF from company.ref_code (no branch) or branch.ref_code (with branch).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('erp_companies') && ! Schema::hasColumn('erp_companies', 'ref_code')) {
            Schema::table('erp_companies', function (Blueprint $table) {
                $table->string('ref_code', 3)->nullable()->after('code');
            });
            // unique per tenant when set
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_erp_companies_tenant_ref_code ON erp_companies (tenant_id, ref_code) WHERE ref_code IS NOT NULL AND deleted_at IS NULL');
        }

        if (Schema::hasTable('erp_branches') && ! Schema::hasColumn('erp_branches', 'ref_code')) {
            Schema::table('erp_branches', function (Blueprint $table) {
                $table->string('ref_code', 3)->nullable()->after('code');
            });
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_erp_branches_tenant_ref_code ON erp_branches (tenant_id, ref_code) WHERE ref_code IS NOT NULL AND deleted_at IS NULL');
        }

        $this->backfillCompanies();
        $this->backfillBranches();
    }

    public function down(): void
    {
        if (Schema::hasTable('erp_companies') && Schema::hasColumn('erp_companies', 'ref_code')) {
            DB::statement('DROP INDEX IF EXISTS uq_erp_companies_tenant_ref_code');
            Schema::table('erp_companies', function (Blueprint $table) {
                $table->dropColumn('ref_code');
            });
        }
        if (Schema::hasTable('erp_branches') && Schema::hasColumn('erp_branches', 'ref_code')) {
            DB::statement('DROP INDEX IF EXISTS uq_erp_branches_tenant_ref_code');
            Schema::table('erp_branches', function (Blueprint $table) {
                $table->dropColumn('ref_code');
            });
        }
    }

    private function backfillCompanies(): void
    {
        $rows = DB::table('erp_companies')->whereNull('deleted_at')->orderBy('created_at')->get();
        $used = [];
        foreach ($rows as $row) {
            $tid = (string) $row->tenant_id;
            if (! isset($used[$tid])) {
                $used[$tid] = [];
            }
            if (! empty($row->ref_code)) {
                $used[$tid][strtoupper((string) $row->ref_code)] = true;
                continue;
            }
            $code = $this->makeRefCode((string) ($row->code ?? ''), (string) ($row->name ?? ''), $used[$tid], 'C');
            DB::table('erp_companies')->where('company_id', $row->company_id)->update(['ref_code' => $code]);
            $used[$tid][$code] = true;
        }
    }

    private function backfillBranches(): void
    {
        $rows = DB::table('erp_branches')->whereNull('deleted_at')->orderBy('created_at')->get();
        $used = [];
        // load company codes so branch codes don't collide within tenant
        foreach (DB::table('erp_companies')->whereNull('deleted_at')->get(['tenant_id', 'ref_code']) as $c) {
            $tid = (string) $c->tenant_id;
            if (! empty($c->ref_code)) {
                $used[$tid][strtoupper((string) $c->ref_code)] = true;
            }
        }
        foreach ($rows as $row) {
            $tid = (string) $row->tenant_id;
            if (! isset($used[$tid])) {
                $used[$tid] = [];
            }
            if (! empty($row->ref_code)) {
                $used[$tid][strtoupper((string) $row->ref_code)] = true;
                continue;
            }
            $code = $this->makeRefCode((string) ($row->code ?? ''), (string) ($row->name ?? ''), $used[$tid], 'B');
            DB::table('erp_branches')->where('branch_id', $row->branch_id)->update(['ref_code' => $code]);
            $used[$tid][$code] = true;
        }
    }

    /**
     * @param  array<string,bool>  $used
     */
    private function makeRefCode(string $code, string $name, array &$used, string $fallbackPrefix): string
    {
        $fromCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($fromCode) >= 3) {
            $cand = substr($fromCode, 0, 3);
            if (! isset($used[$cand])) {
                return $cand;
            }
        }
        if (strlen($fromCode) > 0 && strlen($fromCode) < 3) {
            $cand = str_pad($fromCode, 3, '0');
            if (! isset($used[$cand])) {
                return $cand;
            }
        }
        for ($i = 1; $i < 1000; $i++) {
            $cand = $fallbackPrefix.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if (! isset($used[$cand])) {
                return $cand;
            }
        }

        return $fallbackPrefix.strtoupper(substr(md5($name.$code.microtime()), 0, 2));
    }
};
