## Problem Statement

Organizations with field engineering operations require strict tracking of distributed financial transactions, cash advances, and liquidations. The current manual workflow creates systemic challenges for both field staff and finance teams: field engineers spend excessive time manually entering receipt data, leading to errors and delays. On the back end, finance and audit teams struggle to effectively detect duplicate receipts, properly classify VAT transactions according to BIR standards, quickly reconcile cash advances with actual spending, and enforce fiscal boundaries such as active cutoff periods or overdue liquidation penalties. This lack of automated guardrails results in missing documentation, financial discrepancies, and major compliance risks during audits.

## Solution

The Smart Expense & Reimbursement Management System (SERMS) provides an automated, clinical-grade financial management platform tailored for precise reconciliation of field expenses. By incorporating AI-powered OCR technology, SERMS automatically extracts vendor details, amounts, and tax information from uploaded receipts, reducing data entry friction for field representatives. The system acts as an automated enforcer of company fiscal policy by strictly evaluating VAT classification, blocking duplicate submissions, managing multi-stage approval workflows via role-based access control, and automatically calculating daily penalties for overdue liquidations. Every financial mutation is secured with an immutable, append-only audit log, ensuring complete compliance and readiness for financial audits.

## User Stories

1. As a field engineer, I want to request a cash advance detailing the purpose and expected disbursement date, so that I have the necessary funds to conduct field operations.
2. As a field engineer, I want to upload supporting receipt images (JPEG, PNG, or PDF, up to 2MB) for my expenses, so that I have digital proof for my reimbursement claims.
3. As a field engineer, I want the system to run OCR on my uploaded receipts to extract Vendor Name, Date, Total Amount, VAT Amount, TIN, and Invoice Number, so that I am saved from tedious manual data entry.
4. As a field engineer, I want the system to suggest expense categories based on my receipt contents (labeled as `[AI-Suggested]`), so that I can classify my expenses faster.
5. As a field engineer, I want to be flagged to manually verify OCR results if the system's confidence score falls below 0.80, so that I can correct any inaccuracies before submission.
6. As a field engineer, I want to consolidate my approved expenses into a liquidation report linked to a specific cash advance, so that I can settle my pending balance with the finance department.
7. As a field engineer, I want to clearly see the calculated variance (Overpayment or Reimbursement) between my cash advance and actual approved expenses, so that I know exactly how much I owe or am owed.
8. As a field engineer, I want the ability to resubmit a rejected reimbursement within 15 days or a rejected liquidation within 10 days, so that I don't lose my valid claims.
9. As an approver, I want cash advance requests and expense reports routed to me based on active approval thresholds, so that I only evaluate requests within my explicit authorization limits.
10. As an approver, I want to reject incomplete or non-compliant submissions and provide mandatory comments, so that the submitter knows exactly what to correct.
11. As a finance officer, I want the system to automatically classify receipts as VAT or NON-VAT based on strict BIR validation logic, so that our tax reporting remains fully compliant.
12. As a finance officer, I want the system to automatically detect duplicate receipt submissions within a 90-day window using vendor, date, amount, and invoice number, so that the organization prevents double payments.
13. As a finance officer, I want any duplicate overrides to require a minimum 20-character justification from the submitter, so that we have an audited explanation for exceptions.
14. As an admin, I want to configure the system to automatically apply a daily penalty (e.g., PHP 55.00/day) to unliquidated funds following a 7-day grace period, so that field staff are incentivized to settle accounts promptly.
15. As an admin, I want all system state transitions, including status changes and duplicate overrides, to be recorded in an immutable, append-only audit log containing the actor ID, action type, IP address, and state changes, so that there is undeniable evidence for external auditors.
16. As an admin, I want to configure and manage corporate policies, tax classifications, and role-based access rules, so that the system behavior strictly aligns with organizational updates.
17. As an admin, I want to track every time a role-scoped report is exported from the system (XLSX, CSV, PDF), so that we maintain oversight of sensitive financial data exfiltration.
18. As a user, I want the system to time out my session after 90 minutes of inactivity (60 minutes for admins), so that my account and financial data are secured if I leave my terminal.
19. As a user, I want to receive automated notifications when my submissions are received, approved, or rejected, or when my liquidation is overdue, so that I can take prompt action.
20. As a user, I want all destructive actions (like deleting an unsubmitted draft) to require a deliberate hold-to-confirm interaction, so that I don't accidentally lose my data.

## Implementation Decisions

- **Architecture**: The application follows a Modular Monolith architecture, splitting responsibilities between a Vue 3 SPA frontend and a Laravel 13 backend.
- **Data & Storage**: Relational data will be stored in PostgreSQL/MySQL, while receipt images (up to 2MB) will be strictly saved in a Supabase Bucket prior to any processing.
- **Asynchronous Processing**: Heavy computational tasks—specifically Tesseract OCR extraction and daily penalty calculations—will be strictly offloaded to Laravel Queues to avoid blocking the main API thread.
- **System Constraints & Guardrails**:
  - Missing active cutoff periods will completely block new expense submissions.
  - Active approval thresholds and penalty rules will be determined strictly by the latest `effective_date`.
  - Concurrent user sessions are strictly prohibited.
- **Data Integrity**: The `audit_logs` table will be entirely append-only. Calculated values such as `days_overdue` and incurred penalties will become immutable once written to the database.
- **Security & Authorization**:
  - Laravel Sanctum will handle token-based authentication.
  - All operations will be strictly gated by RBAC (Role-Based Access Control).
- **AI/ML Interactions**: AI will act strictly in a consultative manner. Any AI-derived categorization will be explicitly labeled as `[AI-Suggested]` and will remain fully editable by the user.

## Testing Decisions

- **Testing Philosophy**: Good tests in this system verify external behavior, strict state transitions, and compliance guardrails rather than implementation specifics. We will avoid testing internal framework details and focus on API contracts, queue job execution side-effects, and audit log generation.
- **Modules to be Tested**:
  - **Reimbursement Module**: Verify VAT classification logic against BIR rules and simulate duplicate receipt detection over the 90-day lookup window.
  - **Cash Advance & Liquidation Module**: Test the accuracy of variance calculations and ensure the daily penalty job accurately computes and appends penalty records post-grace period.
  - **Approval Routing**: Ensure edge cases where approval thresholds conflict or are missing correctly halt routing.
  - **Worker Services**: Test the OCR queue job to ensure sub-0.80 confidence scores correctly toggle the `ocr_flagged` state requiring manual review.
- **Prior Art**: Testing patterns will follow existing PHPUnit test structures for backend business logic and Vitest suites for the frontend. Given the system's nature, tests will rely heavily on database factories mimicking various receipt/liquidation states.

## Out of Scope

- General HR workflows (e.g., timesheets, leave management, performance reviews).
- Comprehensive corporate accounting functionalities (e.g., generating balance sheets or general ledgers)—SERMS acts strictly as a specialized expense reconciliation sub-ledger.
- Direct automated integrations with external banking APIs for automatic fund disbursement or direct deposit generation.
- Real-time synchronous OCR processing (all processing must remain asynchronous via queues).

## Further Notes

- The engineering team must resolve currently identified requirement gaps: specific OTP behaviors, lockout thresholds, concurrent session mechanism specifics, dashboard refresh cadences, and rate-limit thresholds.
- Any UI components developed for this feature must be cross-checked against the `reuse before you write` axiom in the Antigravity agent rulebook before implementation.
- This PRD should be published to the project issue tracker and tagged with the `ready-for-agent` triage label for further breakdown and execution.
