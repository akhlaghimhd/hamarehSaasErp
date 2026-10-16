<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FinancialAccounting;

use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Application\Services\FinanceCompanyScope;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FIN-HOLD-W6 / ADR-FIN-003 L15 — denied operate must emit audit signal.
 */
class FinanceCompanyScopeAuditTest extends TestCase
{
    #[Test]
    public function operate_denied_logs_warning_and_throws(): void
    {
        Context::add('security_context', [
            'user_id' => '00000000-0000-4000-8000-000000000099',
            'tenant_id' => '00000000-0000-4000-8000-000000000001',
            'is_owner' => false,
        ]);

        Log::spy();

        $scope = new class extends FinanceCompanyScope {
            public function isGroupWideOperate(): bool
            {
                return false;
            }

            public function allowedCompanyIdsForOperate(): array
            {
                return ['company-a'];
            }
        };

        try {
            $scope->assertCanOperateCompany('company-sibling');
            $this->fail('expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('fin.company_scope.operate_denied', $e->errorCode);
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'finance.access.operate_denied'
                    && ($context['company_id'] ?? null) === 'company-sibling'
                    && ($context['error_code'] ?? null) === 'fin.company_scope.operate_denied';
            })
            ->once();
    }
}
