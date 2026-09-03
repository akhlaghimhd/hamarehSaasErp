<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\FiscalPeriodDTO;
use App\Modules\Accounting\DTOs\UpdateFiscalPeriodDTO;
use App\Modules\Accounting\Models\FiscalPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FiscalPeriodService
{
    public function getAll(): Collection
    {
        return FiscalPeriod::orderBy('start_date')->get();
    }

    public function getById(string $id): FiscalPeriod
    {
        return FiscalPeriod::findOrFail($id);
    }

    public function createFiscalPeriod(FiscalPeriodDTO $dto): FiscalPeriod
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $this->assertNoOverlap($dto->startDate, $dto->endDate);

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

    public function updateFiscalPeriod(string $id, UpdateFiscalPeriodDTO $dto): FiscalPeriod
    {
        return DB::transaction(function () use ($id, $dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $period = FiscalPeriod::findOrFail($id);

            if ($period->is_closed) {
                throw new ConflictHttpException('Cannot update a closed fiscal period.');
            }

            $startDate = $dto->startDate ?? $period->start_date->toDateString();
            $endDate   = $dto->endDate ?? $period->end_date->toDateString();

            if ($dto->startDate !== null || $dto->endDate !== null) {
                $this->assertNoOverlap($startDate, $endDate, $id);
            }

            $updateData = array_filter([
                'name'       => $dto->name,
                'start_date' => $dto->startDate,
                'end_date'   => $dto->endDate,
                'updated_by' => $userId,
            ], fn ($value) => $value !== null);

            $updateData['row_version'] = ((int) ($period->row_version ?? 1)) + 1;

            $period->update($updateData);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_fiscal_periods',
                'aggregate_id'   => $period->period_id,
                'event_type'     => 'accounting.fiscal_period.updated.v1',
                'payload'        => json_encode([
                    'period_id'   => $period->period_id,
                    'name'        => $period->name,
                    'row_version' => $period->row_version,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $period->fresh();
        });
    }

    /**
     * Close a fiscal period (one-way). Domain rule from ADD-07:
     * closed periods must not accept new postings; update/delete blocked.
     */
    public function closePeriod(string $id): FiscalPeriod
    {
        return DB::transaction(function () use ($id) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $period = FiscalPeriod::findOrFail($id);

            if ($period->is_closed) {
                throw new ConflictHttpException('Fiscal period is already closed.');
            }

            $period->update([
                'is_closed'   => true,
                'updated_by'  => $userId,
                'row_version' => ((int) ($period->row_version ?? 1)) + 1,
            ]);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_fiscal_periods',
                'aggregate_id'   => $period->period_id,
                'event_type'     => 'accounting.fiscal_period.closed.v1',
                'payload'        => json_encode([
                    'period_id'  => $period->period_id,
                    'name'       => $period->name,
                    'start_date' => $period->start_date->toDateString(),
                    'end_date'   => $period->end_date->toDateString(),
                    'closed_by'  => $userId,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $period->fresh();
        });
    }

    public function deleteFiscalPeriod(string $id): void
    {
        DB::transaction(function () use ($id) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $period = FiscalPeriod::findOrFail($id);

            if ($period->is_closed) {
                throw new ConflictHttpException('Cannot delete a closed fiscal period.');
            }

            $period->update(['deleted_by' => $userId]);
            $period->delete();

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_fiscal_periods',
                'aggregate_id'   => $id,
                'event_type'     => 'accounting.fiscal_period.deleted.v1',
                'payload'        => json_encode([
                    'period_id' => $id,
                    'name'      => $period->name,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);
        });
    }

    protected function assertNoOverlap(string $startDate, string $endDate, ?string $excludeId = null): void
    {
        $query = FiscalPeriod::where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($inner) use ($startDate, $endDate) {
                  $inner->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
              });
        });

        if ($excludeId) {
            $query->where('period_id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new ConflictHttpException('The specified dates overlap with an existing fiscal period.');
        }
    }
}
