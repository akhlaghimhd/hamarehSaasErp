# Layer 2 – SaaS Admin – Alignment Notes

**Official name:** SaaS Admin  
**Code module:** `App\Modules\SaasAdmin`  
**SSOT:** `hamareh-erp-docs` → `04_SaaS_Core_Platform_Layers/Layer_2_SaaS_Admin/`

## Completed in this pass (2026-09-09)

- L2-M01: RLS enabled on `notifications` and `support_tickets`
- L2-M02: `TenantScoped` trait applied to Notification & SupportTicket models
- L2-M03: `Layer2TenantIsolationTest` added
- L2-M05: API routes now under `/api/v1/{module}` (global change in ModuleServiceProvider)
- L2-M06: Full audit columns (`created_by` / `updated_by` / `deleted_by`) added to `notification_templates`
- L2-M07: Sample versioned Domain Events (`AdminUserCreatedV1`, `SupportTicketCreatedV1`)
- L2-M08: Example Service Contract (`AdminUserServiceContract`)
- L2-D03: `AdminPermissionSeeder` created under `database/seeders/SaasAdmin/`

## Remaining / Intentional decisions

### L2-M04 – Module folder structure (Domain / Application / Infrastructure / API)
Current structure (Controllers, Models, Services, Routes, Events, Contracts) is consistent with other platform modules.  
Full physical restructure to Domain/Application/Infrastructure/API is deferred to a dedicated architecture cleanup task to avoid high-risk mass moves and namespace breakage in one commit.

### L2-D01 – event_types type
Document specified `VARCHAR(50)[]`. Implementation uses `jsonb` + model cast to `array`.  
jsonb is preferred for flexibility and is kept intentionally.

### L2-D02 – Index name cosmetic differences
Minor naming differences (e.g. plural vs singular) do not affect behaviour. Left as-is.

### L2-D04 – Dedicated ModuleServiceProvider inside SaasAdmin
Registration is handled centrally by `App\Base\Providers\ModuleServiceProvider` (route loading + migrations). No per-module provider is required.

### L2-D05 / L2-D06 – row_version usage & DTOs
row_version columns exist and are cast. Optimistic locking helpers can be added later if concurrent update conflicts become an issue.  
DTOs/Requests can be introduced incrementally per endpoint when needed.

## How to run related tests

```bash
docker compose exec app php artisan test --filter=Layer2
docker compose exec app php artisan test tests/Feature/Modules/SaasAdmin
```

## Seeder usage

```bash
docker compose exec app php artisan db:seed --class=Database\\Seeders\\SaasAdmin\\AdminPermissionSeeder
```
