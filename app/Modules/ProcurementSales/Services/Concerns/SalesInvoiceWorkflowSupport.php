<?php

namespace App\Modules\ProcurementSales\Services\Concerns;

use App\Modules\ProcurementSales\Models\SalesInvoice;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-PS-10 – Workflow approval for Sales Invoices before post.
 * Draft → Pending Approval → Approved (then post) / Draft (reject).
 */
trait SalesInvoiceWorkflowSupport
{
    public const STATUS_PENDING_APPROVAL = 6;
    public const STATUS_APPROVED = 7;

    public function submitForApproval(string $id, string $definitionCode = 'SALES_INVOICE_APPROVAL_V1'): SalesInvoice
    {
        try {
            return DB::transaction(function () use ($id, $definitionCode) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $invoice = SalesInvoice::query()->lockForUpdate()->with('items')->find($id);
                if (!$invoice) {
                    throw new NotFoundHttpException('Sales invoice not found.');
                }
                if ((int) $invoice->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft sales invoices can be submitted for approval.');
                }
                if ($invoice->items->isEmpty()) {
                    throw new ConflictHttpException('Cannot submit a sales invoice with no lines.');
                }

                app(WorkflowEngineService::class)->startInstance(
                    definitionCode: $definitionCode,
                    targetAggregateType: 'sales_invoices',
                    targetAggregateId: $invoice->sales_invoice_id,
                    contextSnapshot: [
                        'invoice_number' => $invoice->invoice_number,
                        'total_amount' => (string) $invoice->total_amount,
                        'customer_id' => $invoice->customer_id,
                    ],
                );

                $invoice->update([
                    'status' => self::STATUS_PENDING_APPROVAL,
                    'updated_by' => $userId,
                    'row_version' => ((int) ($invoice->row_version ?? 1)) + 1,
                ]);

                return $invoice->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to submit SalesInvoice for approval: ' . $e->getMessage());
            throw $e;
        }
    }

    public function approveFromWorkflow(string $id): SalesInvoice
    {
        $invoice = SalesInvoice::query()->lockForUpdate()->find($id);
        if (!$invoice) {
            throw new NotFoundHttpException('Sales invoice not found.');
        }
        if ((int) $invoice->status !== self::STATUS_PENDING_APPROVAL) {
            throw new ConflictHttpException('Only pending-approval sales invoices can be approved from workflow.');
        }
        $invoice->update([
            'status' => self::STATUS_APPROVED,
            'updated_by' => Context::get('user_id'),
            'row_version' => ((int) ($invoice->row_version ?? 1)) + 1,
        ]);
        return $invoice->fresh(['items']);
    }

    public function rejectFromWorkflow(string $id): SalesInvoice
    {
        $invoice = SalesInvoice::query()->lockForUpdate()->find($id);
        if (!$invoice) {
            throw new NotFoundHttpException('Sales invoice not found.');
        }
        if ((int) $invoice->status !== self::STATUS_PENDING_APPROVAL) {
            throw new ConflictHttpException('Only pending-approval sales invoices can be rejected from workflow.');
        }
        $invoice->update([
            'status' => self::STATUS_DRAFT,
            'updated_by' => Context::get('user_id'),
            'row_version' => ((int) ($invoice->row_version ?? 1)) + 1,
        ]);
        return $invoice->fresh(['items']);
    }
}
