# FinancialAccounting module

Target path per ADR-FIN-001: `App\Modules\FinancialAccounting`.

Wave tracking: FIN Technical Work Breakdown v1.0 (hamareh-erp-docs).
Ownership: FIN_P0_01_Ownership_Decision_v1.0.md.

Structure (aligned with Clean Architecture + existing module conventions):

- Domain — entities, value objects, domain exceptions (pure)
- Application — services, DTOs, use-cases
- Infrastructure — Eloquent models, repositories, persistence
- API — Controllers, FormRequests, Routes

P0 focus: General Ledger foundation only.
