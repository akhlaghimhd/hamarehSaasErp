# Emergency restore JournalEntryService.php

If the service file was truncated, restore the last known-good version then apply list perf:

```powershell
cd E:\myProject\hamarehSaasErp
git fetch origin
git checkout d264f700224b1f49b4e0c691be96bd30bc502714 -- app/Modules/FinancialAccounting/Application/Services/JournalEntryService.php
```

Then replace the list() query line:

FROM:
```php
$q = JournalEntry::query()->with('items')->orderByDesc('created_at');
```

TO:
```php
$q = JournalEntry::query()
    ->withSum('items as items_debit_sum', 'debit_amount')
    ->withSum('items as items_credit_sum', 'credit_amount')
    ->orderByDesc('created_at');
```

```powershell
git add app/Modules/FinancialAccounting/Application/Services/JournalEntryService.php
git commit -m "fix(fin): restore JournalEntryService + list withSum perf"
git push origin develop
```
