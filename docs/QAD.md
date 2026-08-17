# QA & Test Plan (QAD): Smart Expense & Reimbursement Management System (SERMS)

**Project:** Smart Expense & Reimbursement Management System (SERMS)
**Date:** 2026-08-17
**Version:** 1.1
**Owner:** SERMS Engineering Team
**Status:** Draft
**Last reconciled:** 2026-08-17 (added sample receipt upload testing method and dataset guide for AI subagents & QA)
**Related Docs:** [index.md](index.md) · [PRD.md](PRD.md) · [SAD.md](SAD.md) · [SDD.md](SDD.md) · [DSD.md](DSD.md) · [Build.md](Build.md) · [OPS.md](OPS.md) · [CHANGELOG.md](CHANGELOG.md)

---

## 1. Testing Strategy & Scope

| Level                     | In Scope? | Tooling                                    | Coverage Target                                                                                                 |
| ------------------------- | --------- | ------------------------------------------ | --------------------------------------------------------------------------------------------------------------- |
| **Unit Tests (Backend)**  | Yes       | PHPUnit                                    | **80% overall**, **95%+** on critical compliance math (penalties, VAT validation, duplicate checks, audit logs) |
| **Unit Tests (Frontend)** | Yes       | Vitest + Vue Test Utils                    | Core component rendering and Pinia store state logic                                                            |
| **Integration Tests**     | Yes       | PHPUnit (API endpoint tests)               | Validates controller request boundaries, authorization checks, and webhook responses                            |
| **End-to-End Tests**      | Yes       | Cypress / Playwright                       | Selected critical-path E2E flows (reimbursement submission, approval lifecycle, cash advance liquidation)       |
| **Security Tests**        | Yes       | Larastan (static analysis) + Custom Script | Validates payload pre-encryption integrity and session timeout enforcement                                      |

## 2. Test Environments & Data

| Environment    | URL / Location                                                | Purpose                                                                       |
| -------------- | ------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| **Local**      | `http://localhost:5002` (web) / `http://localhost:8000` (API) | Main developer development, unit test execution, and sandbox debugging        |
| **Staging**    | `https://staging-serms.capstone.net`                          | Pre-release QA, manual validation of role flows, and integration verification |
| **Production** | `https://serms.capstone.net`                                  | Live system serving accounting, engineers, and administrators                 |

### 2.1 Sample Receipts Testing Dataset (For Subagents & Developers)

A curated set of real-world receipt image fixtures is located in **[`docs/receipts/`](receipts/)** for manual and automated upload validation:

| Fixture File | Location | Format / Size | Test Focus / Use Case |
| ------------ | -------- | ------------- | --------------------- |
| **Receipt 1** | [`docs/receipts/receipt 1.jpg`](receipts/receipt%201.jpg) | JPEG (~104 KB) | Standard commercial VAT official receipt (vendor name, TIN, date, VAT breakdown). |
| **Receipt 2** | [`docs/receipts/receipt 2.jpg`](receipts/receipt%202.jpg) | JPEG (~65 KB)  | Thermal paper receipt / slanted angle (tests OCR tolerance & pre-processing filters). |
| **Receipt 3** | [`docs/receipts/receipt 3.jpg`](receipts/receipt%203.jpg) | JPEG (~204 KB) | High-resolution itemized expense slip (tests multi-line extraction and confidence scoring). |

#### Testing Procedures for AI Subagents:

When testing receipt uploads, OCR parsing, or liquidation attachments:
1. **Automated Backend Testing (PHPUnit):** Reference these fixtures using `Illuminate\Http\UploadedFile` pointing to `base_path('docs/receipts/receipt 1.jpg')` to simulate real multipart form uploads to `/api/reimbursements/upload-receipt`.
2. **Browser Subagent / Manual E2E Validation:** Use absolute paths to `docs/receipts/*.jpg` when executing file upload interactions on the Expense Submission or Liquidation views.
3. **Validation Assertions:**
   - **File Storage & Hashing:** Verify receipt is stored in Supabase Bucket and unique SHA-256 `file_hash` is recorded.
   - **Queue Job Dispatch:** Verify `ProcessReceiptOcrJob` is queued and extracts vendor, date, amount, VAT, TIN, and invoice numbers.
   - **Confidence Scoring:** Verify `ocr_confidence_score` is computed. If `< 0.80`, assert `ocr_flagged = true` and that low confidence UI labels appear.
   - **Duplicate Detection:** Re-uploading the same fixture within 90 days must trigger duplicate flags and prompt for override justification (minimum 20 characters).

## 3. Core Test Scenarios

### Happy Paths

- **Receipt Processing Flow:** User uploads a valid PNG receipt -> asynchronous OCR extracts fields -> VAT is classified correctly -> user verifies and submits without error.
- **Advance & Liquidation:** Employee requests cash advance -> approver signs off -> finance records disbursement -> employee liquidates via valid expense receipt -> variance calculates correctly -> settlement closes.
- **Payload Encryption:** Client encrypts sensitive form values -> server receives ciphertext -> decrypts successfully via RSA/AES-GCM -> processes request.

### Sad Paths

- **Duplicate Upload Block:** Uploading a receipt that matches an existing entry (same vendor, amount, date, and invoice number) within a 90-day window triggers `409 Conflict` and requires manual justification.
- **Self-Approval Block:** An approver attempts to approve their own reimbursement claim -> system blocks and returns `403 Forbidden`.
- **Expired Session:** User attempts to make an API request after 90 minutes of inactivity -> system returns `401 Unauthorized` and redirects to capstone login.

### Adversarial / Edge Cases

- **Bypassing Encryption:** Plaintext submissions to sensitive endpoints are intercepted at the server middleware and immediately rejected with `422 Unprocessable Entity`.
- **Audit Mutation Bypass:** Directly attempting to update or delete records in `audit_logs` or `penalties` database tables throws a SQL error (prevented by strict DB trigger configurations).
- **Overdue Penalty Accrual:** Scheduled command fires exactly 7 days after the expected liquidation date -> daily penalty is applied at exactly PHP 50.00/day.

## 4. Automation vs. Manual Testing

| Type                     | Approach                                                                          | CI Gate?                                 |
| ------------------------ | --------------------------------------------------------------------------------- | ---------------------------------------- |
| **Automated Suite**      | All PHPUnit tests and Vitest checks must execute and pass.                        | **Yes** — blocks merges to `main` branch |
| **Static Code Analysis** | Check syntax, types, and PHP PSR standards.                                       | **Yes** — runs on every Pull Request     |
| **Manual Verification**  | Verify final UI rendering, chart responsiveness, and hold-to-confirm interaction. | No — performed ad-hoc on staging         |

## 5. Bug Triage Protocol

| Severity     | Definition                                                                                                                    | SLA for Fix  |
| ------------ | ----------------------------------------------------------------------------------------------------------------------------- | ------------ |
| **Critical** | Major functional blocks broken (e.g., OCR webhook failing, login SSO down, payload decryption failing).                       | < 24 Hours   |
| **High**     | Core functionality degraded but has a workaround (e.g., penalty calculations failing to increment, duplicate checks offline). | < 3 Days     |
| **Medium**   | Minor functional bugs (e.g., UI charts rendering incorrect scales, export formatting missing columns).                        | < 7 Days     |
| **Low**      | Aesthetic tweaks, spelling errors, or missing minor dashboard tooltip highlights.                                             | Next Release |

## 6. Release Criteria (Definition of Done)

- [ ] Code passes all automated PHPUnit and Vitest test suites.
- [ ] Backend test coverage meets the 80% overall and 95% compliance logic targets.
- [ ] Pre-encryption of payloads and audit log integration are manually tested and verified.
- [ ] All exported reports contain correct database-aggregated fields and log actions in `audit_logs`.
