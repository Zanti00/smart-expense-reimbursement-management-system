# AGENTS.md — Smart Expense & Reimbursement Management System (SERMS)

> **Materialized from `docs/Build.md` (the canonical Build Guide). Edit there, then re-run `!doc-creator` to update — do not hand-edit this file as the sole source of truth.**

SERMS is a high-precision, Modular Monolith financial compliance application built in Laravel 13 and Vue 3, utilizing automated OCR queues, BIR-compliant VAT logic, and strict append-only audit tracking.

## Read Order (Every Session)

1. `docs/SERMS.md` (Canonical Master Source of Truth) → 2. `docs/CHANGELOG.md` (for historical context) → 3. `docs/PRD.md` → 4. `docs/SAD.md` → 5. `docs/SDD.md` → 6. `docs/DSD.md` → 7. `docs/OPS.md` → 8. `docs/QAD.md` → 9. `docs/Build.md` → 10. `AGENTS.md`

## Pinned Stack

| Layer             | Technology      | Version    | Location / Reference           |
| ----------------- | --------------- | ---------- | ------------------------------ |
| Backend Runtime   | PHP             | `^8.3`     | `apps/api/composer.json`       |
| Backend Framework | Laravel         | `^13.7`    | Monolith App Core              |
| Frontend Runtime  | Node / Vue 3    | `^3.4.0`   | `apps/web/package.json`        |
| Styling           | Tailwind CSS    | `^3.4.3`   | `apps/web/tailwind.config.js`  |
| Icons             | Lucide Vue Next | `^0.373.0` | Sourced dynamically in layouts |

## Deprecations Register — Stale Forms NOT to Use

| Deprecated Form                                   | Correct Form                                           | Reason                                                            |
| ------------------------------------------------- | ------------------------------------------------------ | ----------------------------------------------------------------- |
| In-memory collection filtering/math for analytics | SQL aggregate queries (`SUM`, `COUNT`, `GROUP BY`)     | Performance optimization; avoids loading huge tables into memory  |
| Plaintext sensitive input submission              | Client-side pre-encryption (AES-256-GCM + RSA wrapper) | Prevent shoulder theft / network snooping of credentials and PII  |
| Inline raw HTML button elements                   | `BaseButton.vue` components or `.btn` utility class    | UI/UX consistency, hover transitions, and hold-to-confirm support |
| Raw custom status indicator markup                | `StatusBadge.vue` badge helper component               | Standard status color map and label compliance                    |

## Conventions

- **Canonical Source of Truth (AI & Developer Rule):** [`docs/SERMS.md`](docs/SERMS.md) is the primary, authoritative single source of truth for all requirements, architecture, design, and governance. All developers and AI agents must cross-reference `docs/SERMS.md` first before modifying code or downstream documentation. If requirements change, `docs/SERMS.md` must be updated first before any downstream document or code file.
- **Module Structure:** Every backend component must live in `app/Modules/{ModuleName}`. Cross-module imports are prohibited except via the `Shared` module or models.
- **Immutability:** Never run updates or deletes on the `audit_logs` or `penalties` tables.
- **Errors:** All authorization violations must return `403 Forbidden`, authentication failures must return `401 Unauthorized`, and duplicate conflicts must return `409 Conflict`.
- **Historical Context (AI Subagent Rule):** AI subagents and developers must check [`docs/CHANGELOG.md`](docs/CHANGELOG.md) whenever historical context, past design decisions, or background on previous modifications is needed before making changes.
- **Error Handling (AI Rule):** Implement explicit `try-catch` blocks at system execution & integration boundaries (Supabase storage, OCR queue tasks, external HTTP APIs, filesystem I/O, background jobs). Never use empty or silent `catch` blocks—log actionable context and re-throw, map to domain exceptions, or return formatted HTTP 4xx/5xx responses. Never catch and swallow exceptions inside `DB::transaction()` blocks without rethrowing to guarantee atomic database rollbacks. Allow unhandled internal runtime errors to bubble up to Laravel's centralized Exception Handler or Vue's global error handler.
- **Git Operations (AI Rule):** AI agents are encouraged to check and inspect Git (e.g., `git log`, `git diff`, `git status`) in read-only mode to gather context and information about the latest changes. However, AI agents must never write, commit, alter Git history, or push to Git without explicit user permission. Always ask the user before performing any mutating Git actions.
- **Context Gathering (AI Rule):** If you think a user's prompt or task lacks sufficient context, details, or information, AI agents and developers must ask questions or interview the user via the `/grill-me` skill interface about the given task/prompt to deepen understanding and avoid hallucinations before proceeding.
- **Reusability & Anti-Duplication Rule — Frontend (AI & Developer Rule):** Developers and subagents must thoroughly scan the codebase for pre-existing reusable components (in `src/components/base/`), composables (in `src/composables/`), utility helpers, and functions before creating new ones. If no existing reusable component or utility exists for a recurring UI/logic pattern, create a clean, reusable abstraction first rather than writing duplicated or inline one-off implementations.
- **Reusability & Anti-Duplication Rule — Backend (AI & Developer Rule):** Before implementing new API routes or controllers, check if an existing endpoint, module controller action, service method, or repository query already fulfills or can be extended to fulfill the requirement. Reuse existing endpoints and services rather than creating duplicate routes, controllers, or redundant database queries.
- **Cross-Project Access Rule for Sister Repositories (AI & Subagent Rule):** AI agents and subagents are permitted to access, inspect, and search sister repositories (`capstone-auth-module`, `capstone-azure-infra`, `ocr-pipeline`, `CMS`, `PRS`, `TS`) whenever they need additional architecture, schema, route contracts, or infrastructure context. However, agents are **strictly restricted to READ and SEARCH operations only** (e.g. `view_file`, `grep_search`, `list_dir`). AI agents must **NEVER modify, write to, delete, or commit/push changes to any other project** unless explicitly instructed and permitted by the user.
- **Documentation Change Log (AI Rule):** Always add an entry to the Changelog in [`docs/CHANGELOG.md`](docs/CHANGELOG.md) whenever changes, additions, or updates are made to any documentation guide or specification in either `docs/` or `documentations/`.

## Definition of Done

- [ ] Code conforms to `A-09` reusability constraints (no duplicate components or utility helpers).
- [ ] Every database mutation has a corresponding `AuditLogService::log()` call in the same database transaction.
- [ ] System boundary operations feature explicit `try-catch` blocks with logging/rethrowing and zero silent error swallowing.
- [ ] Sensitive inputs are encrypted on the client side and verified/decrypted server-side.
- [ ] Export actions for reports are written to the audit logs with filters used.
- [ ] Pre-aggregated database aggregation is used for dashboard visual components.
- [ ] Documentation changes in `docs/` or `documentations/` are recorded in the Changelog in `docs/CHANGELOG.md`.
- [ ] All unit and integration tests (PHPUnit / Vitest) run without failure.
