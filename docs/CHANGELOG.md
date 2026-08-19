# Changelog — SERMS

**Project:** Smart Expense & Reimbursement Management System (SERMS)
**Date:** 2026-08-17
**Version:** 1.3
**Owner:** SERMS Engineering Team
**Status:** Active
**Last reconciled:** 2026-08-17 (Added SBSI client details, Capstone multi-system ecosystem, Azure student infra, 1.5B LLM limits, and Redis/MongoDB override register)
**Related Docs:** [SERMS.md](SERMS.md) · [index.md](index.md) · [PRD.md](PRD.md) · [SAD.md](SAD.md) · [SDD.md](SDD.md) · [DSD.md](DSD.md) · [Build.md](Build.md) · [OPS.md](OPS.md) · [QAD.md](QAD.md)

---

All notable changes, architectural decisions, and documentation updates for the Smart Expense & Reimbursement Management System (SERMS) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Note for AI Subagents & Developers:** Always consult this file when you need chronological historical context, previous architectural pivots, or design rationale before modifying codebase features or documentation guides.

---

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
