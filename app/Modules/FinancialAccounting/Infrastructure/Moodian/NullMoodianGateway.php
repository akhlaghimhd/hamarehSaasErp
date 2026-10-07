<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Moodian;

use App\Modules\FinancialAccounting\Domain\Contracts\MoodianGatewayInterface;
use Illuminate\Support\Str;

/** Mock adapter — accepts all submits for local QA. */
class NullMoodianGateway implements MoodianGatewayInterface
{
    public function submit(array $invoicePayload): array
    {
        return [
            'external_ref' => 'MOCK-'.Str::upper(Str::random(12)),
            'status'       => 'SUBMITTED',
            'raw'          => ['mock' => true, 'payload' => $invoicePayload],
        ];
    }

    public function status(string $externalRef): array
    {
        return [
            'status' => 'ACCEPTED',
            'raw'    => ['mock' => true, 'external_ref' => $externalRef],
        ];
    }
}
