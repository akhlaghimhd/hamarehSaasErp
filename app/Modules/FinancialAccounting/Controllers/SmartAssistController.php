<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\AccountSuggestionService;
use App\Modules\FinancialAccounting\Application\Services\AnomalyAmountService;
use App\Modules\FinancialAccounting\Application\Services\NaturalLanguageDraftService;
use App\Modules\FinancialAccounting\Application\Services\PlInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartAssistController extends Controller
{
    public function __construct(
        private readonly AccountSuggestionService $accounts,
        private readonly PlInsightService $plInsights,
        private readonly NaturalLanguageDraftService $nl,
        private readonly AnomalyAmountService $anomaly,
    ) {
    }

    /** K2 */
    public function suggestAccounts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'  => 'required|uuid',
            'description' => 'nullable|string|max:200',
            'side'        => 'nullable|integer|in:1,2',
        ]);

        $result = $this->accounts->suggest(
            $data['company_id'],
            $data['description'] ?? null,
            isset($data['side']) ? (int) $data['side'] : null,
            $request->user()?->id
        );

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    /** K2 decision audit */
    public function recordAccountDecision(Request $request): JsonResponse
    {
        $data = $request->validate([
            'decision'             => 'required|string|in:ACCEPTED,REJECTED,OVERRIDDEN',
            'suggested_account_id' => 'nullable|uuid',
            'chosen_account_id'    => 'nullable|uuid',
            'context'              => 'nullable|array',
        ]);

        $this->accounts->recordDecision(
            $data['decision'],
            $data['suggested_account_id'] ?? null,
            $data['chosen_account_id'] ?? null,
            $request->user()?->id,
            $data['context'] ?? null
        );

        return response()->json(['status' => 'success', 'message' => 'تصمیم ثبت شد.']);
    }

    /** K5 */
    public function plInsights(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'period_id'  => 'required|uuid',
        ]);

        $result = $this->plInsights->insights(
            $data['company_id'],
            $data['period_id'],
            $request->user()?->id
        );

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    /** K6 — draft only */
    public function nlDraft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'    => 'required|uuid',
            'ledger_id'     => 'required|uuid',
            'period_id'     => 'required|uuid',
            'command'       => 'required|string|max:500',
            'document_date' => 'nullable|date',
        ]);

        $data['actor_id'] = $request->user()?->id;
        $result = $this->nl->createDraftFromCommand($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'فقط پیش‌نویس ساخته شد؛ ثبت قطعی خودکار انجام نشد.',
            'data'    => $result,
        ], 201);
    }

    /** P6-04 */
    public function anomalyScan(string $journal, Request $request): JsonResponse
    {
        $alerts = $this->anomaly->scanJournal($journal, $request->user()?->id);

        return response()->json(['status' => 'success', 'data' => $alerts]);
    }
}
