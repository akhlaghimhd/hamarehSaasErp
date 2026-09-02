<?php

namespace App\Modules\Accounting\Contracts;

/**
 * Service Contract for posting financial vouchers from other modules.
 * Owned by Accounting module.
 */
interface VoucherPostingContract
{
    /**
     * @param array $header  voucher_date, description, source_module, source_document_id, ...
     * @param array $lines   account_id, debit, credit, cost_center_id (optional)
     * @return string        created voucher_id (UUID)
     */
    public function postVoucher(array $header, array $lines): string;

    public function reverseVoucher(string $voucherId, string $reason): string;
}
