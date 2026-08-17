# Project Build Guide

**Project:** Smart Expense & Reimbursement Management System (SERMS)
**Date:** 2026-08-17
**Version:** 1.3
**Owner:** SERMS Engineering Team
**Status:** Draft
**Last reconciled:** 2026-08-17 (established SERMS.md as canonical single source of truth for all documents and requirements)
**Canonical:** [SERMS.md](SERMS.md) · **PRD:** [PRD.md](PRD.md) · **SDD:** [SDD.md](SDD.md) · **DSD:** [DSD.md](DSD.md) · **SAD:** [SAD.md](SAD.md) · **CHANGELOG:** [CHANGELOG.md](CHANGELOG.md)

---

> **What this is:** The operating manual for whoever **builds** SERMS — human or AI agent, on any IDE. The primary master specification is **[`docs/SERMS.md`](SERMS.md)**; this guide specifies *how we actually work in this repository*: step-by-step setup, running locally, pinned tech stacks, golden-path implementation patterns, and development guardrails. **It is the canonical build source; it materializes to the project-root [`AGENTS.md`](../AGENTS.md)**.

---

## 1. How to Build From These Docs

The documentation suite is governed by **[`docs/SERMS.md`](SERMS.md)** (the canonical source of truth). Read in this order before writing code:

1. **[SERMS Master Spec](SERMS.md)** — The primary canonical single source of truth for all requirements, architecture, design, and governance.
2. **[CHANGELOG](CHANGELOG.md)** — Historical context, previous architectural decisions, and recent design updates.
3. **[PRD](PRD.md)** — Core business rules, modules (reimbursement, cash advance, penalties), compliance guidelines, and flows.
4. **[SAD](SAD.md)** — Software architecture, layering, and AI execution model.
5. **[SDD](SDD.md)** — Architectural design (Modular Monolith layout, DB schema, auth proxy, external integration, queue jobs).
6. **[DSD](DSD.md)** — Visual primitives (clean card borders, font weights, status configurations, base component specs).
7. **[OPS](OPS.md)** — Operations runbook, SLOs, and incident response.
8. **[QAD](QAD.md)** — QA test plan, receipt testing fixtures dataset, and verification gates.
9. **This guide** — Developer setup, stack versions, local commands, and code patterns.

---

## 2. Dev Environment Setup & Commands

SERMS runs in a containerized environment via Docker Compose, exposing the backend API at `http://localhost:8000` and the web frontend at `http://localhost:5002`.

### Docker Commands

```powershell
# Start all containers in the background (ensures mysql, redis, php, api, api_queue, and web boot)
docker compose up -d

# View container logs
docker compose logs -f

# Run database migrations inside the PHP container
docker compose exec php php artisan migrate

# Seed the database
docker compose exec php php artisan db:seed

# Stop and remove all containers
docker compose down
```

### Local Dev (No Docker Option)

If you prefer to run services bare-metal on your host:

```powershell
# Backend (Laravel 13) - Starts PHP server, Queue listener, and log tailer concurrently
cd apps/api
composer run dev

# Frontend (Vue 3 / Vite)
cd apps/web
npm run dev
```

---

## 3. Pinned Stack & Dependency Rules

Do not rely on training memory for package capabilities or APIs. Below is the verified tech stack for SERMS:

### Pinned Stack

| Layer | Technology | Pinned Version | Configuration / Location |
|-------|------------|----------------|--------------------------|
| Backend Runtime | PHP | `^8.3` | `apps/api/composer.json` |
| Backend Framework | Laravel | `^13.7` | Modular Monolith layout |
| Frontend Runtime | Node / Vue 3 | `^3.4.0` | `apps/web/package.json` |
| Styling | Tailwind CSS | `^3.4.3` | `apps/web/tailwind.config.js` |
| Icons | lucide-vue-next | `^0.373.0` | Sourced dynamically in layouts |

### Code Execution Commands

- **Backend Linting:** `docker compose exec php ./vendor/bin/pint`
- **Backend Tests:** `docker compose exec php php artisan test`
- **Frontend Build:** `cd apps/web && npm run build`

---

## 4. Golden-Path Code Patterns

### Backend (Laravel Modules)
Every backend controller, service, request validator, and model must reside strictly inside `app/Modules/{ModuleName}`.

#### 1. Controller Pattern (Enforces RBAC + Sanitization)
```php
namespace App\Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expenses\Http\Requests\StoreReceiptRequest;
use App\Modules\Expenses\Services\ExpenseService;
use Illuminate\Http\JsonResponse;

class ReceiptController extends Controller
{
    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    public function store(StoreReceiptRequest $request): JsonResponse
    {
        // Enforce RBAC permission
        $this->authorize('serms.expenses.upload');

        $receipt = $this->expenseService->createReceipt(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'id' => $receipt->id,
            'status' => $receipt->status,
            'message' => 'Receipt uploaded successfully.'
        ], 201);
    }
}
```

#### 2. Service Pattern (Enforces DB Transactions + Auditing)
```php
namespace App\Modules\Expenses\Services;

use App\Modules\Expenses\Models\Receipt;
use App\Modules\AuditLogs\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use App\Modules\Users\Models\User;

class ExpenseService
{
    public function createReceipt(User $user, array $data): Receipt
    {
        return DB::transaction(function () use ($user, $data) {
            $receipt = Receipt::create([
                'uploaded_by' => $user->id,
                'file_path' => $data['file_path'],
                'file_hash' => $data['file_hash'],
                'status' => 'processed',
            ]);

            // IMMUTABLE AUDIT LOG ENTRY
            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: 'UPLOAD_RECEIPT',
                entityType: 'Receipt',
                entityId: $receipt->id,
                beforeState: null,
                afterState: $receipt->toArray(),
                ipAddress: request()->ip()
            );

            return $receipt;
        });
    }
}
```

---

### Frontend (Vue 3 / Pinia)

#### 1. Pinia Store Structure
```javascript
import { defineStore } from "pinia";
import { ref } from "vue";

export const useReceiptsStore = defineStore("receipts", () => {
  const receipts = ref([]);
  const isLoading = ref(false);

  async function fetchReceipts() {
    isLoading.value = true;
    try {
      const response = await fetch("/api/expenses", {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("serms_token")}`,
          Accept: "application/json",
        },
      });
      if (response.ok) {
        receipts.value = await response.json();
      }
    } finally {
      isLoading.value = false;
    }
  }

  return { receipts, isLoading, fetchReceipts };
});
```

#### 2. Vue Base Components Usage
Never write custom structural elements if a base component already exists in `src/components/base/`.
```vue
<script setup>
import { onMounted } from 'vue'
import { useReceiptsStore } from '@/stores/receipts'
import BaseTable from '@/components/base/BaseTable.vue'
import StatusBadge from '@/components/base/StatusBadge.vue'

const store = useReceiptsStore()

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'vendor_name', label: 'Vendor' },
  { key: 'status', label: 'Status' },
]

onMounted(() => {
  store.fetchReceipts()
})
</script>

<template>
  <div class="space-y-4">
    <BaseTable :columns="columns" :rows="store.receipts" :loading="store.isLoading">
      <template #cell-status="{ value }">
        <StatusBadge :status="value" />
      </template>
    </BaseTable>
  </div>
</template>
```

---

## 5. Development Guardrails

- **Canonical Source of Truth Rule (AI & Developer Rule):** [`docs/SERMS.md`](SERMS.md) is the primary, authoritative single source of truth for all requirements, architecture, design, and governance. All developers and AI agents must cross-reference `docs/SERMS.md` first before modifying code or downstream documentation. If requirements change, `docs/SERMS.md` must be updated first before any downstream document or code file.
- **Never create raw CSS files:** Add utility classes directly or append to `@layer components` in [`index.css`](../apps/web/src/assets/index.css).
- **Never bypass the Audit Log:** All service mutations (`create`, `update`, `delete`) must log their actions using `AuditLogService::log()`.
- **Never export without auditing:** All report downloads (XLSX, CSV, PDF) must be triggered via controller endpoints that write an audit log entry specifying the filters and user who performed the export.
- **Never perform high-cost in-memory math or loops for analytics:** Dashboard metrics and report summaries must leverage raw database aggregate functions (e.g. `SUM()`, `COUNT()`, `GROUP BY`) to ensure high-performance scannability.
- **Never transmit sensitive PII or financial mutations in plaintext:** Use client-side payload pre-encryption (AES-256-GCM + RSA wrapper) before sending sensitive forms (e.g., cash advance requests, policy changes) to the server.
- **Never hardcode paths:** For file references, use relative links or Supabase Bucket configurations.
- **Never run blocking loops on requests:** OCR service dispatching and penalty calculations must run inside Queue Jobs.
- **Robust Error Handling (AI & Developer Rule):** Implement explicit `try-catch` blocks at system execution & integration boundaries (Supabase storage uploads, Tesseract OCR queue tasks, external HTTP APIs, filesystem I/O, background jobs). Never use empty or silent `catch` blocks—always log actionable error context and re-throw, map to structured domain exceptions, or return formatted HTTP 4xx/5xx error responses. Never catch and swallow exceptions inside `DB::transaction()` blocks without rethrowing to guarantee atomic database rollbacks. Allow unhandled internal runtime exceptions to bubble up to Laravel's centralized Exception Handler or Vue's global error handler.
- **Never write or push to Git without explicit permission (AI Rule):** AI agents are encouraged to check and inspect Git (e.g., `git log`, `git diff`, `git status`) in read-only mode to gather context and info about the latest changes. However, AI agents must never write, commit, alter Git history, or push to Git without explicit user permission. Always ask the user before performing any mutating Git actions.
- **Always check historical context (AI Subagent Rule):** AI subagents and developers must check [`docs/CHANGELOG.md`](CHANGELOG.md) whenever they need background on previous architectural decisions, design evolutions, or feature histories before making changes.
- **Always gather context before execution (AI Rule):** If a user's prompt or task lacks sufficient context, details, or information, AI agents and developers must ask questions or interview the user via the `/grill-me` skill interface to deepen understanding before proceeding.
- **Reusability & Anti-Duplication Rule — Frontend (AI & Developer Rule):** Developers and subagents must thoroughly scan the codebase for pre-existing reusable components (in `src/components/base/`), composables (in `src/composables/`), utility helpers, and functions before creating new ones. If no existing reusable component or utility exists for a recurring UI/logic pattern, create a clean, reusable abstraction first rather than writing duplicated or inline one-off implementations.
- **Reusability & Anti-Duplication Rule — Backend (AI & Developer Rule):** Before implementing new API routes or controllers, check if an existing endpoint, module controller action, service method, or repository query already fulfills or can be extended to fulfill the requirement. Reuse existing endpoints and services rather than creating duplicate routes, controllers, or redundant database queries.
- **Cross-Project Access Rule for Sister Repositories (AI & Subagent Rule):** AI agents and subagents are permitted to access, inspect, and search sister repositories (`capstone-auth-module`, `capstone-azure-infra`, `ocr-pipeline`, `CMS`, `PRS`, `TS`) whenever they need additional architecture, schema, route contracts, or infrastructure context. However, agents are **strictly restricted to READ and SEARCH operations only** (e.g. `view_file`, `grep_search`, `list_dir`). AI agents must **NEVER modify, write to, delete, or commit/push changes to any other project** unless explicitly instructed and permitted by the user.
- **Always maintain the Documentation Change Log (AI & Developer Rule):** Always append a change log entry in [`docs/CHANGELOG.md`](CHANGELOG.md) whenever creating, modifying, or updating documentation guides or specifications in either `docs/` or `documentations/`.

---

## 6. Document Materialization

This guide serves as the source of truth for repository setup. 

| Source File | Destination File | Automation |
|-------------|------------------|------------|
| `docs/Build.md` | [`AGENTS.md`](../AGENTS.md) | Copy exact contents on save |

Re-materialize this guide whenever stack versions, directory structures, or base components change.
