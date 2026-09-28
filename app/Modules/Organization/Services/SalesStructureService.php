<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\DistributionChannel;
use App\Modules\Organization\Models\ProductDivision;
use App\Modules\Organization\Models\SalesArea;
use App\Modules\Organization\Models\SalesOrganization;
use App\Modules\Organization\Models\SalesOffice;
use App\Modules\Organization\Models\SalesGroup;
use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use Illuminate\Support\Str;

class SalesStructureService
{
    // ── Distribution channels ──────────────────────────────────────────

    public function listChannels(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $q = DistributionChannel::where('tenant_id', $tenantId)->orderBy('code');
        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        return $q->get();
    }

    public function createChannel(string $code, string $name, bool $isActive = true): DistributionChannel
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (DistributionChannel::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new DomainException('کد کانال توزیع تکراری است.', 'duplicate_channel_code');
        }

        return DistributionChannel::create([
            'distribution_channel_id' => (string) Str::uuid(),
            'tenant_id'               => $tenantId,
            'code'                    => $code,
            'name'                    => $name,
            'is_active'               => $isActive,
            'row_version'             => 1,
        ]);
    }

    public function updateChannel(string $id, string $code, string $name, ?bool $isActive = null): DistributionChannel
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = DistributionChannel::where('tenant_id', $tenantId)->where('distribution_channel_id', $id)->firstOrFail();

        if ($row->code !== $code
            && DistributionChannel::where('tenant_id', $tenantId)->where('code', $code)->where('distribution_channel_id', '!=', $id)->exists()) {
            throw new DomainException('کد کانال توزیع تکراری است.', 'duplicate_channel_code');
        }

        $payload = [
            'code' => $code,
            'name' => $name,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ];
        if ($isActive !== null) {
            $payload['is_active'] = $isActive;
        }
        $row->update($payload);

        return $row->fresh() ?? $row;
    }

    public function softDeleteChannel(string $id): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = DistributionChannel::where('tenant_id', $tenantId)
            ->where('distribution_channel_id', $id)
            ->firstOrFail();

        if (SalesArea::where('tenant_id', $tenantId)
            ->where('distribution_channel_id', $id)
            ->exists()) {
            throw new DomainException(
                'این کانال در یک یا چند ناحیه فروش استفاده شده و قابل حذف نیست. ابتدا ناحیه(ها) را حذف کنید.',
                'channel_in_use_by_sales_area'
            );
        }

        $row->delete();
    }

    // ── Product divisions ──────────────────────────────────────────────

    public function listDivisions(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $q = ProductDivision::where('tenant_id', $tenantId)->orderBy('code');
        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        return $q->get();
    }

    public function createDivision(string $code, string $name, bool $isActive = true): ProductDivision
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (ProductDivision::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new DomainException('کد دیویژن محصول تکراری است.', 'duplicate_division_code');
        }

        return ProductDivision::create([
            'division_id' => (string) Str::uuid(),
            'tenant_id'   => $tenantId,
            'code'        => $code,
            'name'        => $name,
            'is_active'   => $isActive,
            'row_version' => 1,
        ]);
    }

    public function updateDivision(string $id, string $code, string $name, ?bool $isActive = null): ProductDivision
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = ProductDivision::where('tenant_id', $tenantId)->where('division_id', $id)->firstOrFail();

        if ($row->code !== $code
            && ProductDivision::where('tenant_id', $tenantId)->where('code', $code)->where('division_id', '!=', $id)->exists()) {
            throw new DomainException('کد دیویژن محصول تکراری است.', 'duplicate_division_code');
        }

        $payload = [
            'code' => $code,
            'name' => $name,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ];
        if ($isActive !== null) {
            $payload['is_active'] = $isActive;
        }
        $row->update($payload);

        return $row->fresh() ?? $row;
    }

    public function softDeleteDivision(string $id): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = ProductDivision::where('tenant_id', $tenantId)
            ->where('division_id', $id)
            ->firstOrFail();

        if (SalesArea::where('tenant_id', $tenantId)
            ->where('division_id', $id)
            ->exists()) {
            throw new DomainException(
                'این دیویژن در یک یا چند ناحیه فروش استفاده شده و قابل حذف نیست. ابتدا ناحیه(ها) را حذف کنید.',
                'division_in_use_by_sales_area'
            );
        }

        $row->delete();
    }

    // ── Sales areas ────────────────────────────────────────────────────

    public function listSalesAreas()
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return SalesArea::where('tenant_id', $tenantId)
            ->with(['salesOrganization', 'distributionChannel', 'productDivision'])
            ->orderBy('code')
            ->get();
    }

    public function createSalesArea(
        string $salesOrgId,
        string $channelId,
        string $divisionId,
        ?string $code = null,
        ?string $name = null,
        bool $isActive = true
    ): SalesArea {
        $tenantId = TenantContext::getInstance()->getTenantId();

        SalesOrganization::where('tenant_id', $tenantId)->where('sales_org_id', $salesOrgId)->firstOrFail();
        DistributionChannel::where('tenant_id', $tenantId)->where('distribution_channel_id', $channelId)->firstOrFail();
        ProductDivision::where('tenant_id', $tenantId)->where('division_id', $divisionId)->firstOrFail();

        if (SalesArea::where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->where('distribution_channel_id', $channelId)
            ->where('division_id', $divisionId)
            ->exists()) {
            throw new DomainException(
                'این ترکیب ناحیه فروش (سازمان + کانال + دیویژن) از قبل وجود دارد.',
                'duplicate_sales_area'
            );
        }

        return SalesArea::create([
            'sales_area_id'            => (string) Str::uuid(),
            'tenant_id'                => $tenantId,
            'sales_org_id'             => $salesOrgId,
            'distribution_channel_id'  => $channelId,
            'division_id'              => $divisionId,
            'code'                     => $code,
            'name'                     => $name,
            'is_active'                => $isActive,
            'row_version'              => 1,
        ]);
    }

    public function softDeleteSalesArea(string $id): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        SalesArea::where('tenant_id', $tenantId)->where('sales_area_id', $id)->firstOrFail()->delete();
    }

    // ── Sales offices / groups ─────────────────────────────────────────

    public function listOffices()
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return SalesOffice::where('tenant_id', $tenantId)->with('groups')->orderBy('code')->get();
    }

    public function createOffice(string $code, string $name, ?string $salesOrgId = null, bool $isActive = true): SalesOffice
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (SalesOffice::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new DomainException('کد دفتر فروش تکراری است.', 'duplicate_office_code');
        }
        if ($salesOrgId) {
            SalesOrganization::where('tenant_id', $tenantId)->where('sales_org_id', $salesOrgId)->firstOrFail();
        }

        return SalesOffice::create([
            'sales_office_id' => (string) Str::uuid(),
            'tenant_id'       => $tenantId,
            'code'            => $code,
            'name'            => $name,
            'sales_org_id'    => $salesOrgId,
            'is_active'       => $isActive,
            'row_version'     => 1,
        ]);
    }

    public function softDeleteOffice(string $id): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        SalesOffice::where('tenant_id', $tenantId)->where('sales_office_id', $id)->firstOrFail()->delete();
    }

    public function createGroup(string $officeId, string $code, string $name, bool $isActive = true): SalesGroup
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        SalesOffice::where('tenant_id', $tenantId)->where('sales_office_id', $officeId)->firstOrFail();

        if (SalesGroup::where('tenant_id', $tenantId)->where('sales_office_id', $officeId)->where('code', $code)->exists()) {
            throw new DomainException('کد گروه فروش در این دفتر تکراری است.', 'duplicate_group_code');
        }

        return SalesGroup::create([
            'sales_group_id'  => (string) Str::uuid(),
            'tenant_id'       => $tenantId,
            'sales_office_id' => $officeId,
            'code'            => $code,
            'name'            => $name,
            'is_active'       => $isActive,
            'row_version'     => 1,
        ]);
    }

    public function softDeleteGroup(string $id): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        SalesGroup::where('tenant_id', $tenantId)->where('sales_group_id', $id)->firstOrFail()->delete();
    }
}
