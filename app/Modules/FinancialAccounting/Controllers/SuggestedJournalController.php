<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\AccountDeterminationService;
use App\Modules\FinancialAccounting\Application\Services\SuggestedJournalService;
use App\Modules\FinancialAccounting\Infrastructure\Models\AccountDeterminationRule;
use App\Modules\FinancialAccounting\Infrastructure\Models\SuggestedJournal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestedJournalController extends Controller
{
    public function __construct(
        private readonly SuggestedJournalService $suggestions,
        private readonly AccountDeterminationService $determination
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');
        $status = $request->query('status', 'PENDING');

        $q = SuggestedJournal::with('lines')->orderByDesc('created_at');
        if ($companyId) {
            $q->where('company_id', $companyId);
        }
        if ($status && $status !== 'ALL') {
            $q->where('status', $status);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $q->limit(100)->get(),
        ]);
    }

    public function show(string $suggested): JsonResponse
    {
        $row = SuggestedJournal::with('lines')
            ->where('suggested_journal_id', $suggested)
            ->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $row]);
    }

    /** Manual / integration entry: build suggestion from amounts + roles. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'           => 'required|uuid',
            'ledger_id'            => 'required|uuid',
            'period_id'            => 'required|uuid',
            'source_event_type'    => 'required|string|max:80',
            'source_document_id'   => 'required|uuid',
            'description'          => 'nullable|string|max:500',
            'amounts'              => 'required|array|min:1',
            'amounts.*'            => 'numeric|min:0',
        ]);

        $row = $this->suggestions->suggestFromOperational($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'پیشنهاد سند ساخته شد (بدون ثبت قطعی).',
            'data'    => $row,
        ], 201);
    }

    public function accept(string $suggested, Request $request): JsonResponse
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $row = $this->suggestions->accept(
            $suggested,
            $request->user()?->id,
            $data['note'] ?? null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'پذیرفته شد؛ پیش‌نویس سند ساخته شد (هنوز ثبت قطعی نشده).',
            'data'    => $row,
        ]);
    }

    public function reject(string $suggested, Request $request): JsonResponse
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $row = $this->suggestions->reject(
            $suggested,
            $request->user()?->id,
            $data['note'] ?? null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'پیشنهاد رد شد.',
            'data'    => $row,
        ]);
    }

    public function rules(): JsonResponse
    {
        $rows = AccountDeterminationRule::query()
            ->orderBy('event_type')
            ->orderBy('priority')
            ->limit(200)
            ->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type'  => 'required|string|max:80',
            'line_role'   => 'required|string|max:40',
            'account_id'  => 'required|uuid',
            'company_id'  => 'nullable|uuid',
            'priority'    => 'sometimes|integer|min:1|max:9999',
            'description' => 'nullable|string|max:300',
            'is_active'   => 'sometimes|boolean',
        ]);

        $row = $this->determination->upsertRule($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'قاعده تعیین حساب ثبت شد.',
            'data'    => $row,
        ], 201);
    }
}
