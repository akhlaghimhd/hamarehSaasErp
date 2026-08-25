<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\SaasAdmin\Models\PlatformInvoice;
use App\Modules\SaasAdmin\Models\PlatformInvoiceItem;
use App\Modules\SaasAdmin\Models\PlatformTransaction;
use App\Modules\SaasAdmin\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $invoiceService;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoiceService = app(InvoiceService::class);
        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'INV_TEST',
            'status'      => 1,
        ]);
    }

    public function test_create_invoice_with_items(): void
    {
        $items = [
            [
                'item_type'   => 'subscription',
                'description' => 'Basic Plan - Monthly',
                'amount'      => 99.9900,
            ],
            [
                'item_type'   => 'addon',
                'description' => 'Extra Storage',
                'amount'      => 15.5000,
            ],
        ];

        $invoice = $this->invoiceService->createInvoice(
            $this->tenant->tenant_id,
            $items,
            5.0000, // discount
            10.0000 // tax
        );

        $this->assertDatabaseHas('platform_invoices', [
            'invoice_id'      => $invoice->invoice_id,
            'tenant_id'       => $this->tenant->tenant_id,
            'total_amount'    => 115.4900,
            'discount_amount' => 5.0000,
            'tax_amount'      => 10.0000,
            'final_amount'    => 120.4900,
            'status'          => InvoiceService::STATUS_ISSUED,
        ]);

        $this->assertCount(2, $invoice->items);
    }

    public function test_record_payment_marks_invoice_paid(): void
    {
        $items = [
            ['item_type' => 'subscription', 'description' => 'Plan', 'amount' => 100.0000],
        ];

        $invoice = $this->invoiceService->createInvoice($this->tenant->tenant_id, $items);

        $tx = $this->invoiceService->recordPayment(
            $invoice->invoice_id,
            100.0000,
            'manual',
            'TX-12345'
        );

        $this->assertDatabaseHas('platform_transactions', [
            'transaction_id' => $tx->transaction_id,
            'invoice_id'     => $invoice->invoice_id,
            'amount'         => 100.0000,
            'status'         => 1,
        ]);

        $this->assertEquals(InvoiceService::STATUS_PAID, $invoice->fresh()->status);
    }
}