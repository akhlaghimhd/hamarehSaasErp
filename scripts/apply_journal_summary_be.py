#!/usr/bin/env python3
"""Add JournalEntryService::statusSummary + controller + route if missing."""
from pathlib import Path
import sys
root = Path(__file__).resolve().parents[1]

svc = root / "app/Modules/FinancialAccounting/Application/Services/JournalEntryService.php"
t = svc.read_text(encoding="utf-8")
if "function statusSummary" not in t:
    insert_after = """        return $q->limit($limit)->get();
    }

    public function post(string $journalEntryId, ?string $actorId = null): JournalEntry
"""
    method = """        return $q->limit($limit)->get();
    }

    /**
     * Lightweight status/deleted counts for list badges (no row payload).
     *
     * @param  array{company_id?: string|null, period_id?: string|null}  $filters
     * @return array{all: int, DRAFT: int, POSTED: int, REVERSED: int, DELETED: int}
     */
    public function statusSummary(array $filters = []): array
    {
        $q = JournalEntry::query();
        $companyFilter = isset($filters['company_id']) ? (string) $filters['company_id'] : null;
        $this->companyScope->applyToQuery($q, $companyFilter !== '' ? $companyFilter : null);

        if (! empty($filters['period_id'])) {
            $q->where('period_id', $filters['period_id']);
        }

        $byStatus = (clone $q)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $deleted = (clone $q)->onlyTrashed()->count();

        $draft = (int) ($byStatus['DRAFT'] ?? 0);
        $posted = (int) ($byStatus['POSTED'] ?? 0);
        $reversed = (int) ($byStatus['REVERSED'] ?? 0);

        return [
            'all' => $draft + $posted + $reversed,
            'DRAFT' => $draft,
            'POSTED' => $posted,
            'REVERSED' => $reversed,
            'DELETED' => (int) $deleted,
        ];
    }

    public function post(string $journalEntryId, ?string $actorId = null): JournalEntry
"""
    if insert_after not in t:
        print("ERROR: service anchor not found", file=sys.stderr); sys.exit(1)
    svc.write_text(t.replace(insert_after, method, 1), encoding="utf-8")
    print("service: added statusSummary")
else:
    print("service: statusSummary already present")

ctrl = root / "app/Modules/FinancialAccounting/Controllers/JournalEntryController.php"
ct = ctrl.read_text(encoding="utf-8")
if "function summary" not in ct:
    old = """    public function index(Request $request): JsonResponse
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
"""
    new = old + """
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
"""
    if old not in ct:
        print("ERROR: controller index not found", file=sys.stderr); sys.exit(1)
    ctrl.write_text(ct.replace(old, new, 1), encoding="utf-8")
    print("controller: added summary")
else:
    print("controller: summary already present")

routes = root / "app/Modules/FinancialAccounting/Routes/api.php"
rt = routes.read_text(encoding="utf-8")
if "journals/summary" not in rt:
    old_r = """    Route::get('/journals', [JournalEntryController::class, 'index'])
        ->middleware('permission:finance.journal.view');
    Route::post('/journals', [JournalEntryController::class, 'store'])
"""
    new_r = """    Route::get('/journals', [JournalEntryController::class, 'index'])
        ->middleware('permission:finance.journal.view');
    Route::get('/journals/summary', [JournalEntryController::class, 'summary'])
        ->middleware('permission:finance.journal.view');
    Route::post('/journals', [JournalEntryController::class, 'store'])
"""
    if old_r not in rt:
        print("ERROR: routes anchor missing", file=sys.stderr); sys.exit(1)
    routes.write_text(rt.replace(old_r, new_r, 1), encoding="utf-8")
    print("routes: added journals/summary")
else:
    print("routes: summary already present")
print("DONE")
