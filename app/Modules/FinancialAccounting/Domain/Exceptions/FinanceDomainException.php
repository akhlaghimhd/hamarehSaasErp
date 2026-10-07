<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Domain\Exceptions;

use DomainException;

/**
 * Base domain exception for Financial Accounting (maps to HTTP 422 at API boundary).
 */
class FinanceDomainException extends DomainException
{
}
