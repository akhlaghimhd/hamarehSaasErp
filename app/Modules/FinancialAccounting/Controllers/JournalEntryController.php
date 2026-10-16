<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalEntryService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $rows = $this->service->list([
            'deleted'    => $request->boolean('deleted'),
            'company_id' => $request->filled('company_id') ? (string) $request->string('company_id') : null,
            'period_id'  => $request->filled('period_id') ? (string) $request->string('period_id') : null,
            'status'     => $request->filled('status') ? (string) $request->string('status') : null,
            'limit'      => (int) $request->input('limit', 100),
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }

    /** Badge counts only — no journal rows (list-page perf). */
    public function summary(Request $request): JsonResponse
    {
        $data = $this->service->statusSummary([
            'company_id' => $request->filled('company_id') ? (string) $request->string('company_id') : null,
            'period_id'  => $request->filled('period_id') ? (string) $request->string('period_id') : null,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    public function show(string $journal): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->find($journal),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ledger_id'              => 'nullable|uuid',
            'company_id'             => 'required|uuid',
            'branch_id'              => 'nullable|uuid',
            'period_id'              => 'required|uuid',
            'document_date'          => 'required|date',
            'description'            => 'nullable|string|max:500',
            'source_document_type'   => 'nullable|string|max:100',
            'source_document_id'     => 'nullable|uuid',
            'lines'                  => 'required|array|min:1',
            'lines.*.account_id'     => 'required|uuid',
            'lines.*.debit_amount'   => 'nullable|numeric|min:0',
            'lines.*.credit_amount'  => 'nullable|numeric|min:0',
            'lines.*.description'    => 'nullable|string|max:500',
            'lines.*.cost_center_id' => 'nullable|uuid',
            'lines.*.business_unit_id' => 'nullable|uuid',
        ]);

        $actorId = auth()->id() ? (string) auth()->id() : null;
        $row = $this->service->createDraft($data, $actorId);

        return response()->json([
            'status'  => 'success',
            'message' => 'پیش‌نویس سند ایجاد شد.',
            'data'    => $row,
        ], 201);
    }

    public function update(string $journal, Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_date'            => 'sometimes|date',
            'description'              => 'nullable|string|max:500',
            'period_id'                => 'sometimes|uuid',
            'lines'                    => 'sometimes|array|min:1',
            'lines.*.account_id'       => 'required_with:lines|uuid',
            'lines.*.debit_amount'     => 'nullable|numeric|min:0',
            'lines.*.credit_amount'    => 'nullable|numeric|min:0',
            'lines.*.description'      => 'nullable|string|max:500',
            'lines.*.cost_center_id'   => 'nullable|uuid',
            'lines.*.business_unit_id' => 'nullable|uuid',
        ]);

        $actorId = auth()->id() ? (string) auth()->id() : null;
        $row = $this->service->updateDraft($journal, $data, $actorId);

        return response()->json([
            'status'  => 'success',
            'message' => 'پیش‌نویس به‌روز شد.',
            'data'    => $row,
        ]);
    }

    public function destroy(string $journal): JsonResponse
    {
        $actorId = auth()->id() ? (string) auth()->id() : null;
        $this->service->deleteDraft($journal, $actorId);

        return response()->json([
            'status'  => 'success',
            'message' => 'پیش‌نویس به سطل حذف منتقل شد.',
        ]);
    }

    public function restore(string $journal): JsonResponse
    {
        $actorId = auth()->id() ? (string) auth()->id() : null;
        $row = $this->service->restoreDraft($journal, $actorId);

        return response()->json([
            'status'  => 'success',
            'message' => 'پیش‌نویس از سطل حذف بازیابی شد.',
            'data'    => $row,
        ]);
    }

    public function post(string $journal): JsonResponse
    {
        $row = $this->service->post($journal, auth()->id() ? (string) auth()->id() : null);

        return response()->json([
            'status'  => 'success',
            'message' => 'سند ثبت قطعی شد.',
            'data'    => $row,
        ]);
    }

    public function reverse(string $journal, Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'nullable|string|max:500',
        ]);

        $row = $this->service->reverse(
            $journal,
            auth()->id() ? (string) auth()->id() : null,
            $data['description'] ?? null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'سند برگشت زده شد.',
            'data'    => $row,
        ]);
    }
}
