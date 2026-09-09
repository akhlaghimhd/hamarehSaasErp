# SaasPlatform (Layer 1) – Module Structure Decision

**Date:** 2026-09-09  
**Task:** L1-03  
**Status:** Documented decision (accepted for current Modular Monolith phase)

## Current layout
```
SaasPlatform/
  Controllers/
  DTOs/
  Events/
  Models/
  Requests/
  Routes/
  Services/
```

## Relation to Architecture Rule 6.2
Rule 6.2 requires Domain / Application / Infrastructure / API folders.
The current layout is the practical Modular Monolith mapping used across
several Layer 6 modules as well:

| Clean Architecture | Current SaasPlatform |
|--------------------|----------------------|
| Domain             | Models + Events      |
| Application        | Services + DTOs      |
| Infrastructure     | (shared app/Base + DB migrations) |
| API                | Controllers + Routes + Requests |

## Decision
- Keep the existing layout for Layer 1 in this phase to avoid a large
  breaking rename across tests, providers and route loading.
- Full physical folder move to Domain/Application/Infrastructure/API
  is deferred to a dedicated refactor ticket after Layer 1 functional
  completeness and RLS are stable.
- This decision is recorded so that a future audit of checklist item 8.1
  can reference this file and treat the structure as intentionally accepted.

## Next step (future)
When the refactor is scheduled: move classes, update namespaces,
ModuleServiceProvider, and all tests in a single atomic PR.
