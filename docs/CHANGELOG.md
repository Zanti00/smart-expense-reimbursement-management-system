# Changelog — SERMS

**Project:** Smart Expense & Reimbursement Management System (SERMS)  
**Client / Partner:** Science Biotech Specialties Inc. (SBSI) — [https://sbsi.com.ph/about-us/](https://sbsi.com.ph/about-us/)  
**Academic Context:** 3rd & 4th Year Capstone Project (Capstone 1: Ended July 2026 · Capstone 2: September–December 2026)  
**Date:** 2026-09-01  
**Version:** 1.5.1  
**Owner:** SERMS Engineering Team  
**Status:** Active  
**Last reconciled:** 2026-09-01 (Documentation suite refactor & standardization of 'What this is' sections across all docs)  
**Canonical Spec:** [SERMS.md](SERMS.md) · **Related Docs:** [index.md](index.md) · [PRD.md](PRD.md) · [SAD.md](SAD.md) · [SDD.md](SDD.md) · [DSD.md](DSD.md) · [Build.md](Build.md) · [OPS.md](OPS.md) · [QAD.md](QAD.md) · [AGENTS.md](../AGENTS.md)

---

> **What this is:** The chronological ledger and historical archive of all notable feature additions, architectural decision records (ADRs), security mitigations, database migrations, breaking changes, and documentation revisions across the SERMS lifecycle, structured in accordance with Keep a Changelog and Semantic Versioning.

---

All notable changes, architectural decisions, and documentation updates for the Smart Expense & Reimbursement Management System (SERMS) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Note for AI Subagents & Developers:** Always consult this file when you need chronological historical context, previous architectural pivots, or design rationale before modifying codebase features or documentation guides.

---

## [1.5.1] - 2026-09-01

### Changed
- **Documentation Suite Refactor & Standardization:** Added prominent, standardized `> **What this is:**` sections across all 10 core documentation files in `docs/` (`index.md`, `SERMS.md`, `PRD.md`, `SAD.md`, `SDD.md`, `DSD.md`, `Build.md`, `OPS.md`, `QAD.md`, `CHANGELOG.md`), detailing the exact role, purpose, audience, and canonical hierarchy of each document.
- **Header Metadata Alignment:** Standardized metadata blocks across all documentation files with uniform Project, Client/Partner (SBSI), Academic Context (Capstone 1 & 2), Date, Version (1.5.1), Status, Canonical Spec links, and Related Docs cross-references.
- **Index & Link Integrity:** Corrected relative links in `docs/index.md` (e.g., `PR_GUIDE.md` -> `../documentations/PR_GUIDE.md`), updated document suite tables with latest version numbers and last reconciled dates.
- **Schema & Component Specifications:** Reconciled `revision_count` column and asynchronous OCR callback services in `docs/SDD.md` and updated 23-status badge mapping in `docs/SERMS.md` and `docs/DSD.md`.
- **Root AGENTS Materialization:** Re-materialized `../AGENTS.md` from `docs/Build.md` to guarantee perfect synchronization across developer and AI agent entrypoints.
- **Medium Date Formatting Standard (AI & Subagent Rule):** Added mandatory instruction across `docs/Build.md`, `AGENTS.md`, `docs/SERMS.md`, `docs/DSD.md`, and `.agents/rules/` requiring AI subagents and developers to format and display human-readable dates in the **Medium Date Format** (e.g. `Sept 1, 2026`).

---

## [1.5.0] - 2026-08-26

### Added
- **Liquidation OCR Pipeline Integration:** Liquidation receipt uploads now call the real `ocr-pipeline` (`AsyncOcrEngineInterface` / `AiServiceOcrEngine`) instead of `TesseractOcrEngine` placeholder (`Fake Vendor (Mocked AI)` / 1250.00). Added `LiquidationController::scan` async dispatch (Supabase store → `Receipt(status=processing)` → `Bus::dispatch(DispatchReceiptToAiService)` → `POST /api/ocr/process`), plus `GET /liquidations/receipts/{id}` (polling) and `POST /liquidations/receipts/{id}/retry-ocr`. Added `LiquidationOcrCallbackController` + `POST /liquidations/receipts/{id}/ocr-callback` (`auth.ai-service-api`) reusing `OcrCallbackService` for vendor/tin/total/VAT/items hydration. Frontend `FileUpload.vue` `simulateOCR` now handles `processing` → 3s polling (via `GET /reimbursements/receipts/{id}` with liquidation fallback), surfaces `rejected`/`failed` with `Retry OCR`, blocks submit while `ocrStatus === 'processing'` (`LiquidationsView::isReceiptOcrProcessing` → `hasIncompleteReceiptFields`), and displays confidence badge.

### Changed
- **LiquidationController:** Removed sync `OcrEngineInterface::extractReceiptData(tempPaths)` fake; now uses `ValidatesReceiptDuplicates` guard + `AsyncOcrEngine` dispatch with audit `RECEIPT_CREATED` / `RECEIPT_OCR_RETRY`. Duplicate uploads clean up Supabase objects before 422.
- **FileUpload.vue:** Rewrote `simulateOCR` to `hydrateEntry`/`startPolling`/`retryOcr` lifecycle, 422 duplicate vs quality branching, `pollTimers` map, `onBeforeUnmount` cleanup, `Retry OCR` button, processing/rejected/failed UI states.
- **LiquidationsView.vue:** Added `isReceiptOcrProcessing` computed to gate liquidation submit during OCR.

### Fixed
- **LiquidationsServiceProvider:** Now aliases `auth.ai-service-api` so liquidation callback route is resolvable regardless of provider boot order.

## [1.4.2] - 2026-08-24

### Fixed
- **Cash Advance Admin Password Bypass (Security):** Fixed critical authorization bypass where `POST /{id}/approve`, `POST /{id}/reject` (action=revise/reject), and `POST /{id}/disburse` proceeded without password verification, incorrectly incrementing `revision_count` and flipping status to `revise`/`rejected`/`approved`/`disbursed` even with wrong/empty password. Root cause was missing `PasswordVerificationService::verify()` in `CashAdvanceController`/`CashAdvanceService` and missing `password` validation in `Approve/Reject/DisburseCashAdvanceRequest` (so `validated()` stripped it), plus frontend `cashAdvance.js`/`CashAdvanceDetailsModal.vue` dropping `adminPassword`. Enforced strict `password: required|string` validation, added service-level guard `PasswordVerificationService::verify()` at top of `DB::transaction` BEFORE any `revision_count` increment/status update (abort with 422 `errors.password` preserving rollback), controller 422/403 handling, and frontend wiring `adminPassword.value` through `DecisionConfirmationModal` → store (with 422 `errors.password` toast, modal stays open). Behavior now matches `ReimbursementService`/`LiquidationController::audit` and `CashAdvanceService::deleteAdvance` correct pattern.

### Changed
- **Requests:** `ApproveCashAdvanceRequest`, `RejectCashAdvanceRequest`, `DisburseCashAdvanceRequest` now require `password`.
- **Frontend:** `stores/cashAdvance.js` signatures `approveRequest(id,comment,password)`, `rejectRequest(id,comment,action,password)`, `disburseRequest(id,payload,password)` and `CashAdvanceDetailsModal.vue` `confirmAdminDecision()` now require and forward password, surfacing 422 password errors.

## [1.4.1] - 2026-08-24

### Fixed
- **Cash Advance Revision 500 (ENUM truncation):** Fixed `SQLSTATE[01000]: Data truncated for column 'action'` when requesting revision (`POST /{id}/reject` action=`revise`). Root cause was `cash_advance_approval_actions.action` ENUM limited to `['approved','rejected']` while `CashAdvanceService::rejectAdvance()` correctly inserted `'revised'` (past tense consistent with `approved`/`rejected`) for counts 1-3. Added migration `2026_08_24_000003` to extend ENUM to `['approved','rejected','revised']`. Hardened `CashAdvanceService` with defensive `allowedActions` guard (422) and boundary try-catch with `Log::error` that converts enum violations to 422 while preserving `DB::transaction` rollback. Terminal 4th strike still forces `rejected`.

### Changed
- **CashAdvanceApprovalAction:** Added docblock for allowed actions and migration note.

## [1.4.0] - 2026-08-24

### Added
- **Revise / 3-Strike Rejection Workflow:** Admins now choose `Revise` (needs revision) or `Reject` via dropdown for reimbursements, cash advances, and liquidations. Both actions set status to `revise` and increment `revision_count`; when `revision_count > 3` the system auto-transitions to terminal `rejected`. Added `revise` status badge (`bg-orange-500`, "Needs Revision"). Updated `StatusBadge.vue`, `DecisionConfirmationModal`, Pinia stores, and audit logs (`CLAIM_REVISED` / `CLAIM_REJECTED`).
- **DB Migrations:** `revision_count` integer column added to `reimbursements`, `cash_advances`, and `liquidations` tables with threshold enforcement in services/controllers.

### Changed
- **Rejection Logic:** Former direct `status='rejected'` on admin action now maps to `status='revise'` until threshold exceeded. Employee self-edit now allowed on `pending` and `revise` (previously `pending`/`rejected`). `rejected` is system-derived only (terminal).
- **Docs:** `SERMS.md` §7.3 badge map and `DSD.md` §2 status table updated to 23-status map including `revise`.

## [1.3.1] - 2026-08-19

### Added
- **Browser Testing & Interactive Debugging Rule (Gemini & AI Agents):** Added explicit rule to `AGENTS.md` and `docs/Build.md` requiring Gemini and AI agents to prioritize browser testing (`browser_subagent`, DevTools, interactive DOM/console inspection, and screenshot verification) when debugging frontend issues, responsive layouts, and UI flows whenever applicable.
- **Seeded Test Accounts & Debugging Credentials Register:** Added comprehensive reference table to `AGENTS.md` and `docs/Build.md` documenting seeded accounts (`employee@example.com`, `sum@sbsi.com`, `admin@example.com`, `approver@example.com`, `finance-admin@example.com`, `finance@example.com`, `manager@example.com`, `sales@example.com`, `superadmin@sbsi.com`, `employee.accounting@sbsi.com`, `manager.finance@sbsi.com`, `manager.operations@sbsi.com`, `supervisor.sales@sbsi.com`, `employee.it@sbsi.com`) across roles and departments to assist subagents in role-based debugging and authentication flows.

---

## [1.3.0] - 2026-08-17

### Added
- **Client Profile & Background (SBSI):** Documented client organization — Science Biotech Specialties Inc. (SBSI, `https://sbsi.com.ph/about-us/`) — and integrated operational requirements for laboratory and field engineers into `SERMS.md`, `PRD.md`, `SAD.md`, and `SDD.md`.
- **Capstone Academic Context & Turnover Schedule:** Documented 3rd & 4th year Capstone project requirements across all documentation suites. Recorded timeline: Capstone 1 concluded July 2026; Capstone 2 runs September–December 2026 focusing on multi-system integration, Azure deployment, and final client turnover.
- **Multi-System Enterprise Ecosystem:** Documented the 4 integrated sub-systems (CMS - Customer Management System, SERMS - Smart Expense Reimbursement Management System, PRS - Productivity Report System, TS - Ticketing System) and the central `capstone-auth-module` reverse proxy / SSO gateway.
- **Cloud Infrastructure (`capstone-azure-infra`):** Documented the deployment architecture in `docs/OPS.md` and `docs/SERMS.md` deployed on Microsoft Azure using Azure for Students ($100 USD/year credit limit) orchestrating SERMS, `capstone-auth-module`, and `ocr-pipeline`.
- **Lightweight AI Model Ceiling (1.5B Parameters):** Recorded SBSI client server constraint strictly capping AI models to <= 1.5B parameters (e.g. Qwen 2.5 1.5B / SmolLM + Tesseract OCR in `ocr-pipeline`).
- **Client Server Tech Decisions & Override Register:**
  - **MySQL 8.0 & Vue 3:** Recommended and approved by SBSI.
  - **Redis (Status: Overridden):** Containerized in Docker for caching & queue processing; configured with transparent fallback to MySQL database driver for client turnover compliance.
  - **MongoDB (Status: Overridden):** Isolated exclusively inside the external `ocr-pipeline` AI service project; zero direct MongoDB dependencies in SERMS.
- **Cross-Project Access Rule for Sister Repositories:** Added explicit instructions to `AGENTS.md`, `docs/Build.md`, and `docs/SERMS.md` granting AI subagents permission to inspect and search sister repositories (`capstone-auth-module`, `capstone-azure-infra`, `ocr-pipeline`, `CMS`, `PRS`, `TS`) in read and search mode only (`view_file`, `grep_search`, `list_dir`) for additional context, while strictly prohibiting write, edit, delete, or commit actions unless explicitly permitted by the user.

---

## [1.2.0] - 2026-08-17

### Added
- **SERMS Master Specification (`docs/SERMS.md`):** Authored the unified canonical single source of truth document for the entire SERMS ecosystem. Consolidates product vision & BIR rules (PRD), software architecture & AI model (SAD), modular system design, crypto & OCR sequence (SDD), design system & 22-status badge map (DSD), build setup & non-negotiable guardrails (Build/AGENTS), operations & SLOs (OPS), and QA test plans & receipt datasets (QAD). Established strict governance requiring all agents and developers to cross-reference `docs/SERMS.md` first before modifying code or downstream docs.
- **Fullstack Reusability & Anti-Duplication Rules:** Added explicit rules in `docs/Build.md` and `AGENTS.md` requiring developers and subagents to inspect existing components, composables, utils, and backend endpoints/services before writing new code, and to extract modular abstractions first rather than introducing duplicated inline code or redundant endpoints.
- **Real Receipt Upload Testing Guide:** Added testing procedures and sample receipt fixtures dataset documentation to `docs/QAD.md` utilizing images in `docs/receipts/` (`receipt 1.jpg`, `receipt 2.jpg`, `receipt 3.jpg`) for OCR queue verification, BIR VAT checks, and duplicate detection testing by subagents.
- **Clean Component & Border Rules:** Added explicit rules to `docs/DSD.md` prohibiting colored top borders (`border-t`), colored top accent bars, and heavy background/border trims on cards and panels unless specifically requested by the user.
- **Documentation Change Log Governance:** Added rules across `AGENTS.md` and `docs/Build.md` requiring every documentation change in `docs/` and `documentations/` to be logged in `docs/CHANGELOG.md`.
- **Read-Only Git Inspection Rule:** Added clear convention to `AGENTS.md` and `docs/Build.md` encouraging AI agents to inspect `git status`, `git log`, and `git diff` in read-only mode for richer context while strictly forbidding write/commit/push mutations without explicit user consent.
- **SAD.md Development Guardrails Linkage:** Linked `docs/SAD.md` directly to canonical `docs/Build.md` (§5 Development Guardrails) and summarized fullstack reusability, immutable auditing, boundary error handling, payload encryption, and git inspection rules.
- **Dedicated Changelog:** Extracted historical change log from `docs/index.md` into this standalone `docs/CHANGELOG.md`.

### Changed
- **SDD.md — Module Roster & Cross-Cutting Services:** Updated the Module Roster in `docs/SDD.md` with complete namespace mappings, route prefixes, controllers, services, and queue workers for all 9 modules (`CashAdvances`, `Reimbursements`, `Expenses`, `Liquidations`, `AuditLogs`, `Notifications`, `Ai`, `Users`, and `Shared`). Expanded the Cross-Cutting Services table with `AiServiceOcrEngine` and `TesseractOcrEngine`.
- **SDD.md — Architecture & OCR Pipeline Flow:** Revised High-Level Architecture Mermaid diagram in `docs/SDD.md` to explicitly illustrate the External AI OCR Service within External Cloud & AI Services, along with bidirectional OCR pipeline flows (Worker dispatching `POST /api/ocr` and AI Service calling back to `POST /api/serms/ai/ocr-callback`). Added External AI OCR Service entry to External Integrations catalog table.
- **DSD.md — Status Badge Map:** Fully synchronized status color table with `StatusBadge.vue` config, including all 22 statuses (`granted`, `paid`, `settled`, `unliquidated`, `overpayment`, `reject`, `automatic-rejected`, `pending-admin-re-review`, `draft`, `flagged`, and fallback). Corrected discrepancies for `pending` (`bg-amber-500`), `disbursed` (`bg-blue-600`), and `under-review` (`bg-violet-600`).
- **DSD.md — Typography System:** Updated Google Fonts `@import` weights in `DSD.md` and documented centralized typography CSS variables (`--font-primary`, `--font-mono`), Tailwind font tokens, centralized text utility classes (`.text-page-title`, `.text-section-title`, `.text-field-label`, `.text-body`, `.text-caption`, `.text-value`, `.text-value-lg`), and component label tracking (`11px`, `tracking-[0.02em]`).

---

## [1.1.0] - 2026-08-15

### Added
- **Boundary Error Handling Rule:** Implemented strict error handling rules in `docs/Build.md`, `AGENTS.md`, `docs/SAD.md`, and AI governance specifications requiring explicit `try-catch` blocks at system execution boundaries (Supabase storage, OCR queue tasks, external HTTP APIs, filesystem I/O, background jobs) with zero silent swallowing.

### Removed
- Cleaned up obsolete subagent references from `docs/SAD.md` and `docs/index.md`.

---

## [1.0.0] - 2026-07-12

### Added
- **Security & Analytics Optimization:** Smart-merged `docs/PRD.md`, `docs/SAD.md`, `docs/SDD.md`, `docs/DSD.md`, `docs/Build.md`, and `docs/index.md` to reflect client-side pre-encryption (AES-256-GCM + RSA wrapper) for sensitive mutations and database-driven SQL aggregation (`SUM`, `COUNT`, `GROUP BY`) for dashboard/report analytics.
- **Operational Guides:** Generated `AGENTS.md`, `docs/OPS.md`, and `docs/QAD.md`.

---

## [0.1.0] - 2026-07-05

### Added
- **Foundation Documentation:** Initialized project workspace with core architectural and design specifications:
  - `docs/PRD.md`: Business requirements, OCR workflow, BIR VAT compliance, cash advances, and liquidations.
  - `docs/SAD.md`: Modular monolith software architecture, security layers, and AI governance.
  - `docs/SDD.md`: Domain-driven module design, schema, and API integrations.
  - `docs/DSD.md`: Visual design primitives, clinical color tokens, typography, and base component specs.
  - `docs/Build.md`: Canonical developer operating manual and setup guide, materialized to root `AGENTS.md`.
  - `docs/index.md`: Master documentation index.
