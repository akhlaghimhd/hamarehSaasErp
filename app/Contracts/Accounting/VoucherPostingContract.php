<?php

namespace App\Contracts\Accounting;

/**
 * Service Contract for posting financial vouchers from other modules.
 *
 * Used by ProcurementSales, Manufacturing, HrManagement, etc.
 * when they need to generate accounting entries.
 * Implementation lives in Accounting module.
 *
 * Events remain the preferred long-term integration path;
 * this contract is for synchronous in-process needs in Modular Monolith phase.
 */
interface VoucherPostingContract
{
    /**
     * Create a draft or posted voucher from an external module.
     *
     * @param array $header  Required keys: voucher_date, description, source_module, source_document_id
     * @param array $lines   Array of lines with account_id, debit, credit, cost_center_id (optional)
     * @return string        The created voucher_id (UUID)
     */
    public function postVoucher(array $header, array $lines): string;

    /**
     * Reverse a previously posted voucher (creates a reversing entry).
     */
    public function reverseVoucher(string $voucherId, string $reason): string;
}
