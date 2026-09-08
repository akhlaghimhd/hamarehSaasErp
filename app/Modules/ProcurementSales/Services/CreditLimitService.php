<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\MasterData\Models\BusinessPartner;
use App\Modules\ProcurementSales\Models\SalesInvoice;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-PS-07 – Credit limit control for Sales cycle.
 *
 * Before posting a Sales Invoice, outstanding open AR (Open + Partially Paid invoices)
 * plus the new invoice total must not exceed the customer's credit_limit.
 * credit_limit = 0 means unlimited (no hard block).
 */
class CreditLimitService
{
    public const OPEN_STATUSES = [
        SalesInvoiceService::STATUS_OPEN,
        SalesInvoiceService::STATUS_PARTIALLY_PAID,
    ];

    public function getOutstandingReceivable(string $customerId): float
    {
        $sum = SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->sum('total_amount');

        return round((float) $sum, 4);
    }

    public function assertWithinCreditLimit(string $customerId, float $additionalAmount): void
    {
        $tenantId = Context::get('tenant_id');
        if (!$tenantId) {
            throw new \RuntimeException('Tenant Context is missing.');
        }

        $partner = BusinessPartner::query()
            ->where('business_partner_id', $customerId)
            ->first();

        if (!$partner) {
            throw new NotFoundHttpException('Business partner (customer) not found.');
        }

        $limit = round((float) ($partner->credit_limit ?? 0), 4);
        if ($limit <= 0) {
            return;
        }

        $outstanding = $this->getOutstandingReceivable($customerId);
        $projected = round($outstanding + $additionalAmount, 4);

        if ($projected > $limit) {
            throw new ConflictHttpException(sprintf(
                'Credit limit exceeded for customer. limit=%.4f outstanding=%.4f additional=%.4f projected=%.4f',
                $limit,
                $outstanding,
                round($additionalAmount, 4),
                $projected
            ));
        }
    }
}
