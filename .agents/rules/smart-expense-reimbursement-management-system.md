---
trigger: always_on
---

# SERMS — Antigravity AI Governance & Prompt Engineering Specification

**System:** Smart Expense & Reimbursement Management System (SERMS)
**Target AI Engine:** Antigravity
**Version:** 1.0

---

# 1. Core Rules

### System Scope

Antigravity operates only within SERMS workflows:

- Expense reimbursement
- Cash advance and liquidation
- Approval workflows
- Notifications
- Audit logging
- Authentication and role access

It must not respond outside these domains.

### Compliance & Security

- BIR compliance is mandatory.
- Audit logs are immutable and retained for 10 years.
- All actions must follow RBAC.
- Concurrent sessions are prohibited.
- Session timeout:
  - Standard users: 90 minutes
  - Admin/IT roles: 60 minutes

### Data Integrity

Antigravity must never fabricate:

- Financial values
- Approval statuses
- Penalty amounts
- Permissions
- OCR outputs

Missing data must trigger clarification requests instead of assumptions.

### Response Standards

- Currency: `PHP 1,250.00`
- Date: Medium Date format (e.g. `Sept 1, 2026` / `MMM D, YYYY` for all human-facing presentation, UI components, notifications, and AI responses; standard ISO `YYYY-MM-DD` for internal DB storage)
- Timestamp: ISO 8601 with timezone
- Use system enum values only.

### Approval Boundaries

Antigravity may:

- Route requests
- Validate permissions
- Notify approvers

It must never auto-approve or reject requests.

### Validation

Validation errors must include:

- Failed field
- Reason
- Suggested correction

---

# 2. Functional Rules

## Reimbursement Module

### File Upload Rules

- Allowed formats: JPEG, PNG, PDF
- Max file size: 2MB

### OCR Workflow

Antigravity must:

1. Run Tesseract OCR
2. Extract:
   - Vendor Name
   - Date
   - Total Amount
   - VAT Amount
   - TIN
   - Invoice Number

3. Store `ocr_confidence_score`

If score `< 0.80`:

- Set `ocr_flagged = true`
- Require manual confirmation

### VAT Classification

Receipts must be classified as:

- VAT
- NON-VAT

Classification must rely on BIR validation logic, not user input.

### AI Expense Categorization

AI-generated categories:

- Are suggestions only
- Must be editable
- Must be labeled `[AI-Suggested]`

### Cutoff Validation

Reimbursement submission requires an active cutoff period.

### Duplicate Detection

Check duplicates within 90 days using:

- Vendor
- Date
- Amount
- Invoice number

If duplicate:

- Flag record
- Require override justification
- Log override action

### Submission Constraints

- At least one supporting report document is required.
- Rejected submissions may be resubmitted within 15 days.
- Self-approval is prohibited.
- Rejections require comments.

---

## OCR & AI Processing

- Files must first be stored in Supabase Bucket.
- OCR and AI processing must be asynchronous via Laravel Queues.
- AI-generated fields must be labeled `[AI-Suggested]`.
- Uploaded receipts must store a `file_hash`.

---

## Cash Advance Module

Required fields:

- Purpose
- Requested amount
- Expected disbursement date
- Expected liquidation date

Approval routing must use active `approval_thresholds`.

All disbursements require:

- Date
- Channel
- Reference number

Status transitions must be logged.

---

## Liquidation Module

Daily penalty computation must:

1. Run via Laravel Queues
2. Use active `penalty_rules`
3. Store immutable penalty records

Additional rules:

- Penalty records are append-only
- Shortfalls require explanation
- At least one receipt attachment is mandatory
- Rejected liquidations may be resubmitted within 10 days

---

## Approval Workflow

- Approval chains cannot be bypassed.
- Approval routing must use live threshold data.
- Every approval action must create an audit log.

---

## Analytics & Reporting

- Reports are role-scoped.
- Export actions must be audit logged.
- Supported formats:
  - XLSX
  - CSV
  - PDF

---

## Notification Service

Notifications must:

- Use templates only
- Log delivery attempts
- Retry failed deliveries
- Query organization hierarchy dynamically

---

## Audit Logs

Audit logs must:

- Be immutable
- Use append-only behavior
- Include:
  - actor_id
  - actor_role
  - action_type
  - entity_type
  - entity_id
  - before_state
  - after_state
  - ip_address
  - created_at

---

# 3. AI Behavior Rules

## Context Retention

Within a session, Antigravity must retain workflow context.

Across sessions, only persisted database state may be reused.

## Instruction Hierarchy

Priority order:

1. Compliance/legal constraints
2. SERMS business rules
3. RBAC
4. Functional rules
5. User instructions

Conflicting lower-priority instructions must be rejected.

## Hallucination Prevention

Antigravity must never infer:

- Financial amounts
- Approval states
- Permissions
- OCR values

Missing data must return structured “data not found” responses.

## Confidence Labels

- OCR score `< 0.80` → `[Low Confidence — Please Verify]`
- AI-generated categories → `[AI-Suggested]`
- User-derived computations → `[Unverified — Based on User Input]`

## Clarification Rules

If required data is missing:

1. Identify missing fields
2. Explain necessity
3. Request only missing values

---

# 4. Technical Rules

## Architecture

SERMS follows a Modular Monolith architecture.

Antigravity must:

- Respect module boundaries
- Use Laravel Queues for async jobs
- Store files in Supabase Bucket only

## Security

- All APIs require authentication
- Unauthorized access:
  - `401 Unauthorized`
  - `403 Forbidden`

- Passwords and OTP hashes must never be exposed.
- IP addresses must come from request context.
- Token-based actions must enforce expiration.

## Data Integrity

- Status histories are append-only.
- `days_overdue` must remain immutable once written.
- Rule selection uses the latest valid `effective_date`.

## DRY & Maintainability

Validation, notification, and audit logic must be centralized and reusable.

## Error Handling & Exception Management

- **Boundary Try-Catching:** Explicit `try-catch` blocks must be used at all system execution & integration boundaries (Supabase object storage, Tesseract OCR queue tasks, external HTTP API calls, filesystem I/O, background jobs).
- **Zero Error Swallowing:** Catch blocks must never be left empty or silently swallow exceptions. Caught errors must log actionable context and rethrow, map to structured domain exceptions, or return formatted HTTP 4xx/5xx responses.
- **Transaction Integrity:** Never catch and swallow exceptions inside `DB::transaction()` blocks without rethrowing to guarantee atomic database rollbacks.
- **Centralized Exception Delegation:** Uncaught internal runtime exceptions must bubble up to Laravel's centralized Exception Handler (`bootstrap/app.php`) or Vue's global error handler.

## Performance

- Avoid full-table scans on `audit_logs`
- OCR/AI processing must run in queues
- Dashboard metrics should use pre-aggregated data [Inference]

---

# 5. UX Rules

## Interaction Standards

Actions modifying state require explicit confirmation.

Dashboards must only expose role-relevant data.

Status labels must map to human-readable values.

## Feedback

- OCR processing requires loading indicators.
- Validation errors must appear inline.
- Success responses must include:
  - Record ID
  - Action
  - Timestamp
  - Next workflow step

## Notifications

Required events:

- Submission received
- Approved/rejected
- Cash advance disbursed
- Liquidation overdue
- Penalty incurred
- Password reset requested

Sensitive financial data must remain role-scoped.

## Accessibility

[Inference]

- Status indicators must not rely on color alone.
- Errors must support screen readers.

---

# 6. Edge Cases & Safeguards

## Invalid Inputs

Reject:

- Unsupported file types
- Files >2MB
- Negative or invalid monetary values
- Invalid date relationships

## Missing Data

If:

- No active cutoff exists → block submissions
- No active penalty rule exists → skip computation and alert admins
- OCR misses required fields → require manual entry

## Duplicate Conflicts

Duplicate overrides require:

- Matching receipt visibility
- Minimum 20-character justification

Conflicting approval thresholds must halt routing until resolved.

## Failure Recovery

Failed jobs:

- Retry automatically
- Log failures
- Alert admins after retry exhaustion

Uploads must fail if Supabase storage is unavailable.

## Abuse Prevention

[Inference]

- Authentication endpoints must be rate-limited.
- OTP attempts must enforce lockout thresholds.
- Export actions should be rate-limited.
- Approval actions must validate assigned approvers only.

---

# 7. Gaps & Clarifications

| ID   | Gap                                       | Type        |
| ---- | ----------------------------------------- | ----------- |
| G-01 | OTP behavior not fully defined            | [Inference] |
| G-02 | Concurrent session mechanism unspecified  | [GAP]       |
| G-03 | OTP lockout threshold missing             | [GAP]       |
| G-04 | Dashboard refresh strategy unspecified    | [Inference] |
| G-05 | Approval routing matrix incomplete        | [GAP]       |
| G-06 | Rate-limit thresholds undefined           | [GAP]       |
| G-07 | No liquidation status history table       | [GAP]       |
| G-08 | Refund tracking entity missing            | [GAP]       |
| G-09 | Dashboard refresh cadence unspecified     | [GAP]       |
| G-10 | Multi-role support unclear                | [GAP]       |
| G-11 | Accessibility compliance target undefined | [GAP]       |
| G-12 | Threshold versioning behavior inferred    | [Inference] |

---

_End of SERMS Antigravity AI Governance & Prompt Engineering Specification v1.0_
