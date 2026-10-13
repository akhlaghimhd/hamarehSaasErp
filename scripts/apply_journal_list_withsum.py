#!/usr/bin/env python3
"""Replace list() with('items') by withSum aggregates for list payload perf."""
from pathlib import Path
import sys

path = (
    Path(__file__).resolve().parents[1]
    / "app/Modules/FinancialAccounting/Application/Services/JournalEntryService.php"
)
t = path.read_text(encoding="utf-8")
if "withSum('items as items_debit_sum'" in t or 'withSum("items as items_debit_sum"' in t:
    print("already has withSum")
    sys.exit(0)
old = "        $q = JournalEntry::query()->with('items')->orderByDesc('created_at');"
new = """        // List: aggregate line totals only — avoid N×items payload (FIN list perf).
        $q = JournalEntry::query()
            ->withSum('items as items_debit_sum', 'debit_amount')
            ->withSum('items as items_credit_sum', 'credit_amount')
            ->orderByDesc('created_at');"""
if old not in t:
    print("ERROR: expected list() with('items') line not found", file=sys.stderr)
    # show nearby
    idx = t.find("public function list")
    print(t[idx:idx+400] if idx >= 0 else "no list()", file=sys.stderr)
    sys.exit(1)
path.write_text(t.replace(old, new, 1), encoding="utf-8")
print(f"OK applied withSum to {path}")
