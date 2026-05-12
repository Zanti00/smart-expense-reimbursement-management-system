# SERMS System Architecture & Technical Specification

## 1. Technological Stack

| Component | Logic | Version/Standard |
| :--- | :--- | :--- |
| **Framework** | Vue 3 | Composition API |
| **Build Tool** | Vite | Standard Module Resolution |
| **Styling** | Tailwind CSS v3 | Custom Grid & Color Definitions |
| **State Engine** | Pinia | Modular Service Stores |
| **Routing** | Vue Router 4 | Navigation Guards |
| **Iconography** | Lucide-Vue-Next | Scalable Vector Standards |
| **Visualization** | Chart.js | spend trend telemetry |

---

## 2. Infrastructure & Layout Architecture

### 2.1 Base Layout Configurations
- **`AppLayout.vue`**: Primary administrative console with collapsible navigation, search modules, and system alert trimmings.
- **`AuthLayout.vue`**: Sterile, centered orientation for secure system access.

### 2.2 Component Primitives (`src/components/base/`)
- **`BaseButton.vue`**: Atomic control with primary, secondary, and ghost variants.
- **`BaseTable.vue`**: High-density tabular data visualization with column sorting and filtration.
- **`StatusBadge.vue`**: Unified signal-light statuses (Approved, Pending, Rejected, Paid, Overdue, Liquidated).
- **`FileUpload.vue`**: specialized OCR scanning interface with confidence thresholds.

---

## 3. Data & State Management

### 3.1 Modular Reactive Stores
- **`useAuthStore`**: Identity management and administrative role verification.
- **`useReimbursementStore`**: Claim lifecycle management and OCR form state.
- **`useCashAdvanceStore`**: Financial issuance and disbursement tracking.
- **`useNotificationStore`**: Global system alerts and asynchronous telemetries.

### 3.2 Routing & Navigation Standards
| Route Pattern | Operational Module |
| :--- | :--- |
| `/dashboard` | KPI visualization and spend telemetry |
| `/reimbursements` | Expense claim management console |
| `/cash-advances` | disbursement request tracking |
| `/liquidations` | Advance Reconciliation & Settlement module |
| `/admin/*` | System governance and policy controls |

---

## 5. Settlement & Liquidation Workflow

### 5.1 Reconciliation Lifecycle (Settlement)
The system enforces a strict reconciliation sequence:
1. **User Submission**: Selection of parent Cash Advance + Itemized Expense Entry.
2.- **`UNLIQUIDATED`**: Initial state of an approved disbursement. Active debt. Triggers ₱55/day penalty after Day 7.
- **`PENDING`**: Settlement submitted by user. Under active Admin audit. **Penalties are suppressed** in this state.
- **`LIQUIDATED`**: Audit complete. Balance is zero. Account cleared.
- **`OVERDUE`**: Auto-triggered for `UNLIQUIDATED` items past Day 7.
    - **Reimbursement (Abono)**: Balance < 0 (Company owes User).
3. **Audit Verification**: Admin Review of receipts vs. entries.
4. **Final Closure**: Status transition to `LIQUIDATED`.

### 5.2 Aging & Late Penalty Policy
To maintain high fiscal velocity, the system tracks the "Age" of every Cash Advance:
- **Grace Period**: 7 operational days from Date of Issuance.
- **Overdue Breach**: At Day 8, status transitions to `OVERDUE`.
- **Late Penalty**: A daily assessment of **₱55.00 ($1.00)** is applied for every 24-hour period past the deadline until the report is `PENDING` review.

---

## 4. UI Operational Logic

- **Immediate Validation**: Real-time input boundary enforcement with visual limit alerting.
- **Deterministic Confirmations**: Hold-to-confirm interaction standards for destructive state mutations.
- **Scanning Lifecycle**: Progressive horizontal scanning animation during OCR processing to confirm active system effort.
