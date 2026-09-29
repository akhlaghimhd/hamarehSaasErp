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

    public function restoreChannel(string $id): DistributionChannel
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = DistributionChannel::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('distribution_channel_id', $id)
            ->firstOrFail();

        if (DistributionChannel::where('tenant_id', $tenantId)->where('code', $row->code)->exists()) {
            throw new DomainException(
                'کد این کانال با یک رکورد فعال دیگر تداخل دارد.',
                'restore_code_conflict'
            );
        }

        $row->restore();
        $row->update([
            'is_active'   => false,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ]);

        return $row->fresh() ?? $row;
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

    public function restoreDivision(string $id): ProductDivision
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = ProductDivision::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('division_id', $id)
            ->firstOrFail();

        if (ProductDivision::where('tenant_id', $tenantId)->where('code', $row->code)->exists()) {
            throw new DomainException(
                'کد این دیویژن با یک رکورد فعال دیگر تداخل دارد.',
                'restore_code_conflict'
            );
        }

        $row->restore();
        $row->update([
            'is_active'   => false,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ]);

        return $row->fresh() ?? $row;
    }

    // ── Sales areas ────────────────────────────────────────────────────

    public function listSalesAreas(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $q = SalesArea::where('tenant_id', $tenantId)
            ->with(['salesOrganization', 'distributionChannel', 'productDivision'])
            ->orderBy('code');
        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        return $q->get();
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

    public function updateSalesArea(
        string $id,
        ?string $code = null,
        ?string $name = null,
        ?bool $isActive = null
    ): SalesArea {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = SalesArea::where('tenant_id', $tenantId)->where('sales_area_id', $id)->firstOrFail();

        $payload = [
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ];
        if ($code !== null) {
            $payload['code'] = $code;
        }
        if ($name !== null) {
            $payload['name'] = $name;
        }
        if ($isActive !== null) {
            $payload['is_active'] = $isActive;
        }
        $row->update($payload);

        return $row->fresh(['salesOrganization', 'distributionChannel', 'productDivision']) ?? $row;
    }

    public function softDeleteSalesArea(string $id): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        SalesArea::where('tenant_id', $tenantId)->where('sales_area_id', $id)->firstOrFail()->delete();
    }

    public function restoreSalesArea(string $id): SalesArea
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = SalesArea::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('sales_area_id', $id)
            ->firstOrFail();

        if (SalesArea::where('tenant_id', $tenantId)
            ->where('sales_org_id', $row->sales_org_id)
            ->where('distribution_channel_id', $row->distribution_channel_id)
            ->where('division_id', $row->division_id)
            ->exists()) {
            throw new DomainException(
                'این ترکیب ناحیه فروش از قبل به‌صورت فعال وجود دارد.',
                'restore_sales_area_conflict'
            );
        }

        $row->restore();
        $row->update([
            'is_active'   => false,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ]);

        return $row->fresh(['salesOrganization', 'distributionChannel', 'productDivision']) ?? $row;
    }

    // ── Sales offices / groups ─────────────────────────────────────────

    public function listOffices(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $q = SalesOffice::where('tenant_id', $tenantId)->with('groups')->orderBy('code');
        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        return $q->get();
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

    public function updateOffice(
        string $id,
        string $code,
        string $name,
        ?string $salesOrgId = null,
        ?bool $isActive = null
    ): SalesOffice {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = SalesOffice::where('tenant_id', $tenantId)->where('sales_office_id', $id)->firstOrFail();

        if ($row->code !== $code
            && SalesOffice::where('tenant_id', $tenantId)->where('code', $code)->where('sales_office_id', '!=', $id)->exists()) {
            throw new DomainException('کد دفتر فروش تکراری است.', 'duplicate_office_code');
        }
        if ($salesOrgId) {
            SalesOrganization::where('tenant_id', $tenantId)->where('sales_org_id', $salesOrgId)->firstOrFail();
        }

        $payload = [
            'code'         => $code,
            'name'         => $name,
            'sales_org_id' => $salesOrgId,
            'row_version'  => ((int) ($row->row_version ?? 1)) + 1,
        ];
        if ($isActive !== null) {
            $payload['is_active'] = $isActive;
        }
        $row->update($payload);

        return $row->fresh(['groups']) ?? $row;
    }

    public function softDeleteOffice(string $id): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $office = SalesOffice::where('tenant_id', $tenantId)
            ->where('sales_office_id', $id)
            ->firstOrFail();

        $activeGroups = SalesGroup::where('tenant_id', $tenantId)
            ->where('sales_office_id', $id)
            ->whereNull('deleted_at')
            ->count();
        if ($activeGroups > 0) {
            throw new DomainException(
                'این دفتر فروش دارای گروه فعال است و قابل حذف نیست. ابتدا گروه‌ها را حذف کنید.',
                'office_has_groups'
            );
        }

        $office->delete();
    }

    public function restoreOffice(string $id): SalesOffice
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = SalesOffice::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('sales_office_id', $id)
            ->firstOrFail();

        if (SalesOffice::where('tenant_id', $tenantId)->where('code', $row->code)->exists()) {
            throw new DomainException(
                'کد این دفتر فروش با یک رکورد فعال دیگر تداخل دارد.',
                'restore_code_conflict'
            );
        }

        $row->restore();
        $row->update([
            'is_active'   => false,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ]);

        return $row->fresh(['groups']) ?? $row;
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

    public function updateGroup(string $id, string $code, string $name, ?bool $isActive = null): SalesGroup
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = SalesGroup::where('tenant_id', $tenantId)->where('sales_group_id', $id)->firstOrFail();

        if ($row->code !== $code
            && SalesGroup::where('tenant_id', $tenantId)
                ->where('sales_office_id', $row->sales_office_id)
                ->where('code', $code)
                ->where('sales_group_id', '!=', $id)
                ->exists()) {
            throw new DomainException('کد گروه فروش در این دفتر تکراری است.', 'duplicate_group_code');
        }

        $payload = [
            'code'        => $code,
            'name'        => $name,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ];
        if ($isActive !== null) {
            $payload['is_active'] = $isActive;
        }
        $row->update($payload);

        return $row->fresh() ?? $row;
    }

    public function softDeleteGroup(string $id): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        SalesGroup::where('tenant_id', $tenantId)->where('sales_group_id', $id)->firstOrFail()->delete();
    }

    public function restoreGroup(string $id): SalesGroup
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = SalesGroup::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('sales_group_id', $id)
            ->firstOrFail();

        if (SalesGroup::where('tenant_id', $tenantId)
            ->where('sales_office_id', $row->sales_office_id)
            ->where('code', $row->code)
            ->exists()) {
            throw new DomainException(
                'کد این گروه فروش در همان دفتر با یک رکورد فعال تداخل دارد.',
                'restore_code_conflict'
            );
        }

        $row->restore();
        $row->update([
            'is_active'   => false,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ]);

        return $row->fresh() ?? $row;
    }
}
