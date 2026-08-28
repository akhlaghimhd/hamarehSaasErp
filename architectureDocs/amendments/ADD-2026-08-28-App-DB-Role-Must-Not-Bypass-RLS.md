# Architecture Decision Record – Application DB Role Must Not Bypass RLS

- **ID:** ADD-2026-08-28-app-db-role-rls
- **Status:** Accepted
- **Date:** 2026-08-28
- **Related:** Tenant Isolation Architecture Standard §4, §10; F1 Identity/Security

## Context

PostgreSQL Row Level Security (RLS) is mandatory for all tenant_id tables.
Superusers and roles with `BYPASSRLS` always bypass RLS, even when
`FORCE ROW LEVEL SECURITY` is enabled.

F1 tests proved:
- With role `postgres` (superuser), RLS policies are ignored → Data Bleed possible if application scopes are bypassed.
- With role `app_user` (`NOSUPERUSER`, `NOBYPASSRLS`), RLS enforces tenant isolation at the database layer.

## Decision

1. The Laravel application **must not** connect as a PostgreSQL superuser.
2. Production and testing application connections **must** use a dedicated role, e.g. `app_user`, with:
   - `NOSUPERUSER`
   - `NOBYPASSRLS`
   - least-privilege grants on required schemas/tables/sequences
3. RLS pattern for every operational `tenant_id` table:
   - `ENABLE ROW LEVEL SECURITY`
   - `FORCE ROW LEVEL SECURITY`
   - Policy:
     ```sql
     tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid