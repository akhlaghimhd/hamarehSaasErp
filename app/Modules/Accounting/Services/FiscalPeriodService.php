<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\FiscalPeriodDTO;
use App\Modules\Accounting\Models\FiscalPeriod;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FiscalPeriodService
{
    public function createFiscalPeriod(FiscalPeriodDTO $dto): FiscalPeriod
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            // منطق بیزینسی: بررسی عدم تداخل زمانی دوره‌های مالی
            $overlapExists = FiscalPeriod::where(function ($query) use ($dto) {
                $query->whereBetween('start_date', [$dto->startDate, $dto->endDate])
                      ->orWhereBetween('end_date', [$dto->startDate, $dto->endDate])
                      ->orWhere(function ($q) use ($dto) {
                          $q->where('start_date', '<=', $dto->startDate)
                            ->where('end_date', '>=', $dto->endDate);
                      });
            })->exists();

            if ($overlapExists) {
                throw new ConflictHttpException('The specified dates overlap with an existing fiscal period.');
            }

            $period = FiscalPeriod::create([
                'tenant_id'   => $tenantId,
                'name'        => $dto->name,
                'start_date'  => $dto->startDate,
                'end_date'    => $dto->endDate,
                'is_closed'   => $dto->isClosed ?? false,
                'created_by'  => $userId,
                'row_version' => 1,
            ]);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_fiscal_periods',
                'aggregate_id'   => $period->period_id,
                'event_type'     => 'accounting.fiscal_period.created.v1',
                'payload'        => json_encode([
                    'period_id'  => $period->period_id,
                    'name'       => $period->name,
                    'start_date' => $period->start_date->toDateString(),
                    'end_date'   => $period->end_date->toDateString(),
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $period;
        });
    }
}
