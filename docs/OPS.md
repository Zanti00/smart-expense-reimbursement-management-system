# Operations & Observability Runbook (OPS): Smart Expense & Reimbursement Management System (SERMS)

**Project:** Smart Expense & Reimbursement Management System (SERMS)  
**Client / Partner:** Science Biotech Specialties Inc. (SBSI) — [https://sbsi.com.ph/about-us/](https://sbsi.com.ph/about-us/)  
**Academic Context:** 3rd & 4th Year Capstone Project (Capstone 1: Ended July 2026 · Capstone 2: September–December 2026)  
**Date:** 2026-09-01  
**Version:** 1.5.1  
**Owner:** SERMS Operations & Infrastructure Team  
**Status:** Active  
**Last reconciled:** 2026-09-01 (Documentation suite refactor & standardization of 'What this is' sections across all docs)  
**Canonical Spec:** [SERMS.md](SERMS.md) · **Related Docs:** [index.md](index.md) · [PRD.md](PRD.md) · [SAD.md](SAD.md) · [SDD.md](SDD.md) · [DSD.md](DSD.md) · [Build.md](Build.md) · [QAD.md](QAD.md) · [CHANGELOG.md](CHANGELOG.md) · [AGENTS.md](../AGENTS.md)

---

> **What this is:** The Operations & Observability Runbook (OPS) detailing the cloud hosting topology on Microsoft Azure (Azure for Students via `capstone-azure-infra`), SBSI server resource constraints, Service Level Objectives (SLOs), logging and telemetry architectures, alerting matrices, incident severity ladders, disaster recovery runbooks, and routine operational turnover checklists.

---

## 1. Cloud Infrastructure & Azure Deployment (`capstone-azure-infra`)

SERMS and its interconnected enterprise ecosystem are deployed via the **`capstone-azure-infra`** repository:

| Property | Details |
|---|---|
| **Cloud Provider** | Microsoft Azure |
| **Subscription Plan** | **Azure for Students** |
| **Annual Budget** | **$100 USD / Year** (Strict credit conservation) |
| **Infrastructure Repo** | `capstone-azure-infra` |
| **Deployed Workloads** | 1. `SERMS` (Laravel 13 API, Vue 3 SPA, MySQL, Redis worker)<br>2. `capstone-auth-module` (Central Authentication Service & Reverse Proxy)<br>3. `ocr-pipeline` (Asynchronous OCR AI extraction engine) |
| **Budget Controls** | Lightweight Alpine base images, auto-shutdown/scaling policies for non-production environments, and asynchronous queue batching to minimize compute hours. |

### 1.1 Client Server & Resource Constraints Register

SBSI granted dedicated server access for hosting and turnover with the following operational constraints:

| Component / Layer | Client Constraint / Guideline | Status | Operational Configuration |
|---|---|---|---|
| **AI / LLM Model** | Max 1.5B Parameters | **Enforced** | Local/edge inference in `ocr-pipeline` strictly uses <= 1.5B parameter models (e.g., Qwen 2.5 1.5B / SmolLM) + Tesseract OCR to prevent CPU/GPU overload. |
| **Primary Database** | MySQL Recommended | **Approved** | MySQL 8.0 instance optimized with indexing and pre-aggregated queries. |
| **Frontend Framework** | Vue Recommended | **Approved** | Vue 3 SPA utilizing Vite, Pinia, and Tailwind CSS. |
| **Cache Engine (Redis)** | No Permission Granted | **Overridden** | Containerized Redis runs in Docker for queue acceleration and cache storage. Fallback configured to MySQL database driver (`CACHE_STORE=database`, `QUEUE_CONNECTION=database`) for client turnover. |
| **NoSQL Store (MongoDB)** | No Permission Granted | **Overridden** | Isolated exclusively inside `ocr-pipeline` for storing raw OCR bounding boxes and extraction schemas. Core SERMS contains zero MongoDB dependencies. |

---

## 2. SLOs & SLIs

| Metric                   | SLI Definition                                                                                  | Target (SLO)                        |
| ------------------------ | ----------------------------------------------------------------------------------------------- | ----------------------------------- |
| **Uptime**               | Successful API responses and frontend asset loads divided by total request count.               | **99.5%** uptime monthly            |
| **API Latency (p95)**    | Round-trip duration of HTTP responses for transaction, listing, and policy modification routes. | **< 300ms**                         |
| **API Error Rate**       | Count of responses returning `5xx` status codes divided by total requests.                      | **< 0.1%**                          |
| **OCR Processing Speed** | Time elapsed between receipt file submission and callback receipt webhook confirmation.         | **< 10 seconds** for 90% of uploads |

---

## 3. Observability — Logs, Metrics, Traces

| Signal      | Tool / Location                                        | Notes                                                                                                       |
| ----------- | ------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| **Logs**    | Laravel Logs (`storage/logs/laravel.log`)              | Contains application warnings, error traces, Sanctum auth rejections, and audit exceptions.                 |
| **Metrics** | MySQL Aggregates & Redis Metrics                       | DB-level pre-aggregations are stored as indexes for dashboard counters; Redis monitor checks active queues. |
| **Traces**  | Database Queue Monitor (`jobs` / `failed_jobs` tables) | Monitors execution latency of asynchronous OCR jobs and daily penalty calculations.                         |

---

## 4. Alerting & On-Call

| Alert                    | Condition                                                                          | Severity      | Notification Route     |
| ------------------------ | ---------------------------------------------------------------------------------- | ------------- | ---------------------- |
| **API High Latency**     | p95 API response times exceed 1000ms for 5 consecutive minutes.                    | P1 — High     | Teams / Slack hook     |
| **Queue Backlog**        | Database queue has more than 50 jobs waiting in pending state.                     | P2 — Medium   | In-App dashboard alert |
| **OCR Job Failure**      | Webhook callback retry count exhausted for a receipt upload in `ocr-pipeline`.     | P1 — High     | Teams / Admin alert    |
| **Audit Integrity Fail** | Detection of unauthorized attempts to alter `audit_logs` table (DB trigger alert). | P0 — Critical | Security channel       |

---

## 5. Incident Response

### Severity Ladder

| Level             | Definition                                                                                                 | Response Time         |
| ----------------- | ---------------------------------------------------------------------------------------------------------- | --------------------- |
| **P0 — Critical** | System-wide failure (main API down, authentication service unreachable, audit verification failing).       | Immediate (< 15 mins) |
| **P1 — High**     | Core modules degraded (OCR queue halted, Supabase storage unreachable, encryption key validation failing). | < 1 hour              |
| **P2 — Medium**   | Non-critical service issues (in-app notifications delayed, dashboard charts rendering errors).             | < 4 hours             |
| **P3 — Low**      | Minor cosmetic layout bugs or reporting export format alignment issues.                                    | Next business day     |

### Mitigation & Rollback Steps

- **Supabase Storage Interruption:** Temporarily route upload requests to a fallback server-side buffer directory in the workspace container until the Supabase API recovers.
- **OCR Webhook Backlog (`ocr-pipeline`):** If the external AI service rate-limits requests, configure retry delay backoffs in the queue workers.
- **Rollback Procedure:** If a deploy breaks financial/compliance logic, run standard container rollbacks via:
  ```bash
  docker compose down
  # Pull stable image tag / checkout main branch
  docker compose up -d
  ```

### Postmortem Requirements

- Mandatory for all P0 and P1 incidents.
- Must document root cause, timeline of events, system metrics during failure, mitigation actions, and long-term action items to prevent recurrence.
- Record the outcome in `docs/CHANGELOG.md` §3 (Incident Log).

---

## 6. Routine Operations & Turnover Checklist

| Task                        | Frequency | Owner            | Notes                                                             |
| --------------------------- | --------- | ---------------- | ----------------------------------------------------------------- |
| **Azure Budget Audit**      | Weekly    | DevOps Lead      | Track consumption against the $100/year student credit allocation.|
| **DB Index Maintenance**    | Monthly   | DB Administrator | Rebuild index metrics on audit logs and transaction date columns. |
| **Penalty Rule Audit**      | Quarterly | Accounting Admin | Verify effective date mappings of `penalty_rules` table.          |
| **Encryption Key Rotation** | Annually  | Security Lead    | Rotate server RSA private/public keys and redeploy client config. |
| **Client Turnover Audit**   | Capstone 2| Lead Architect   | Verify client server deployment and MySQL/Vue environment at SBSI.|
