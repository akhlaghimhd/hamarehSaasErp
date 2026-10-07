<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Domain\Contracts;

/**
 * FIN-P2-04 — Swappable Moodian adapter (null/mock in P2; real HTTP later).
 */
interface MoodianGatewayInterface
{
    /**
     * @param  array<string, mixed>  $invoicePayload
     * @return array{external_ref: string, status: string, raw: mixed}
     */
    public function submit(array $invoicePayload): array;

    /**
     * @return array{status: string, raw: mixed}
     */
    public function status(string $externalRef): array;
}
