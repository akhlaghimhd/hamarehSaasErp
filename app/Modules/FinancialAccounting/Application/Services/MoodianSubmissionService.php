<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Domain\Contracts\MoodianGatewayInterface;
use App\Modules\FinancialAccounting\Infrastructure\Models\MoodianSubmission;
use App\Modules\FinancialAccounting\Infrastructure\Moodian\NullMoodianGateway;
use Illuminate\Support\Str;

/** FIN-P2-05 — Submit/status/poll with persistent log. */
class MoodianSubmissionService
{
    public function __construct(
        protected ?MoodianGatewayInterface $gateway = null
    ) {
        $this->gateway = $gateway ?? new NullMoodianGateway();
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     */
    public function submit(array $meta, array $invoicePayload): MoodianSubmission
    {
        $tenantId = $this->requireTenantId();

        $row = MoodianSubmission::create([
            'moodian_submission_id' => (string) Str::uuid(),
            'tenant_id'             => $tenantId,
            'company_id'            => $meta['company_id'],
            'source_document_type'  => $meta['source_document_type'],
            'source_document_id'    => $meta['source_document_id'],
            'tax_transaction_id'    => $meta['tax_transaction_id'] ?? null,
            'status'                => MoodianSubmission::STATUS_PENDING,
            'request_payload'       => json_encode($invoicePayload, JSON_UNESCAPED_UNICODE),
            'row_version'           => 1,
        ]);

        try {
            $result = $this->gateway->submit($invoicePayload);
            $row->external_ref = $result['external_ref'] ?? null;
            $row->status = $result['status'] ?? MoodianSubmission::STATUS_SUBMITTED;
            $row->response_payload = json_encode($result['raw'] ?? $result, JSON_UNESCAPED_UNICODE);
            $row->submitted_at = now();
            $row->row_version = ((int) $row->row_version) + 1;
            $row->save();
        } catch (\Throwable $e) {
            $row->status = MoodianSubmission::STATUS_FAILED;
            $row->error_message = mb_substr($e->getMessage(), 0, 500);
            $row->row_version = ((int) $row->row_version) + 1;
            $row->save();
        }

        return $row->fresh();
    }

    public function poll(string $submissionId): MoodianSubmission
    {
        $row = MoodianSubmission::where('moodian_submission_id', $submissionId)->firstOrFail();

        if (! $row->external_ref) {
            throw new DomainException('شناسه خارجی مودیان موجود نیست.', 'fin.moodian.no_ref');
        }

        $result = $this->gateway->status((string) $row->external_ref);
        $row->status = $result['status'] ?? $row->status;
        $row->response_payload = json_encode($result['raw'] ?? $result, JSON_UNESCAPED_UNICODE);
        $row->last_polled_at = now();
        $row->row_version = ((int) $row->row_version) + 1;
        $row->save();

        return $row->fresh();
    }

    protected function requireTenantId(): string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId) {
            throw new DomainException('بافت مستأجر تنظیم نشده است.', 'fin.tenant_missing');
        }

        return $tenantId;
    }
}
