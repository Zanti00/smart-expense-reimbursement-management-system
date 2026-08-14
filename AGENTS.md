# AGENTS.md — Smart Expense & Reimbursement Management System (SERMS)

> **Materialized from `docs/Build.md` (the canonical Build Guide). Edit there, then re-run `!doc-creator` to update — do not hand-edit this file as the sole source of truth.**

SERMS is a high-precision, Modular Monolith financial compliance application built in Laravel 13 and Vue 3, utilizing automated OCR queues, BIR-compliant VAT logic, and strict append-only audit tracking.

## Read Order (Every Session)

1. `docs/index.md` → 2. `docs/PRD.md` → 3. `docs/SAD.md` → 4. `docs/SDD.md` → 5. `docs/DSD.md` → 6. `docs/Build.md` → 7. `AGENTS.md`

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

- **Module Structure:** Every backend component must live in `app/Modules/{ModuleName}`. Cross-module imports are prohibited except via the `Shared` module or models.
- **Immutability:** Never run updates or deletes on the `audit_logs` or `penalties` tables.
- **Errors:** All authorization violations must return `403 Forbidden`, authentication failures must return `401 Unauthorized`, and duplicate conflicts must return `409 Conflict`.
- **Git Operations (AI Rule):** AI agents and subagents must never push to Git or alter Git history without explicit user permission, even if technically capable. Always ask the user before pushing unless permission was granted on the spot.
- **Context Gathering (AI Rule):** If you think a user's prompt or task lacks sufficient context, details, or information, AI agents and subagents must ask questions or interview the user via the `/grill-me` skill interface about the given task/prompt to deepen understanding and avoid hallucinations before proceeding.
- **Reusability Check (AI Rule):** AI agents and subagents must search for pre-existing reusable components, composables, utils, functions, etc. before creating new ones. If an equivalent or near-equivalent implementation already exists, it must be reused or extended rather than duplicated.

## Definition of Done

- [ ] Code conforms to `A-09` reusability constraints (no duplicate components or utility helpers).
- [ ] Every database mutation has a corresponding `AuditLogService::log()` call in the same database transaction.
- [ ] Sensitive inputs are encrypted on the client side and verified/decrypted server-side.
- [ ] Export actions for reports are written to the audit logs with filters used.
- [ ] Pre-aggregated database aggregation is used for dashboard visual components.
- [ ] All unit and integration tests (PHPUnit / Vitest) run without failure.
