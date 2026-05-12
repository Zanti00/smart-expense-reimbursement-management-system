# Front-End Design Document: SERMS 

## 1. Design Philosophy: "The Engineered Interface"
To align with a company selling clinical laboratory instruments, the UI is treated as a **High-Precision Tool**. It prioritizes clarity, data density, and mechanical reliability over decorative aesthetics.

- **Constraint-Based Presentation:** Minimize non-functional decorative elements. Visual weight is reserved for data-rich components and actionable controls.
- **Dimensional Precision:** All interface components are governed by a strict 4px layout grid to ensure mechanical alignment.
- **Clinical Aesthetic:** High-contrast typography on laboratory-neutral backgrounds, optimized for analytical readability.

---

## 2. Visual Identity

### 2.1 Clinical Neutral Palette (Tailwind Configuration)
The color system utilizes a corporate primary anchor supported by calibrated neutral surfaces.

| Name | Hex | Usage |
| :--- | :--- | :--- |
| **Primary (Brand)** | `#252578` | Navigation sidebars, primary action buttons, active tab indicators. |
| **Surface (Sterile)**| `#FFFFFF` | Primary workspace, data cards, modals. |
| **Backdrop** | `#F1F5F9` | System background (Slate-100). |
| **Grid Lines** | `#E2E8F0` | 1px borders, table dividers (Slate-200). |
| **Status: Success** | `#059669` | Approved/Paid/Verified (Emerald-600). |
| **Status: Alert** | `#D97706` | OCR <80%, approaching deadlines (Amber-600). |
| **Status: Error** | `#DC2626` | Rejected, Overdue, Validation failed (Red-600). |

### 2.2 Typography: "The Readout"
- **UI Text (Sans):** `Inter`. Optimized for legibility at small sizes (12px-14px).
- **Financial Data (Mono):** `JetBrains Mono`. Used for all currency values, dates, and reference IDs to ensure vertical alignment of digits.
- **Labels:** `text-[10px] font-bold uppercase tracking-widest text-slate-500`.

---

## 3. Structural Standards

### 3.1 The "Blueprint" Grid
- **Borders over Shadows:** Use `border-slate-200` for depth. Avoid `shadow-lg`. Only use `shadow-sm` for floating elements (modals).
- **Corner Radius:** Strict `rounded-sm` (2px) or `rounded-none`.
- **Information Density:** Use Tailwind `py-1.5 px-3` for table cells and form inputs to maximize visible data.

### 3.2 Interaction Response & Motion Logic
Animation systems are optimized for "hardware-style" feedback, ensuring low latency and deterministic transitions.
- **Transition Standards:** `150ms linear` or `ease-out`. Elastic or high-variance physics are prohibited.
- **Focus Indicators:** Sharp 2px offset rings (`ring-2 ring-[#252578] ring-offset-1`) for high-visibility navigation.
- **System Telemetry:** Utilize linear progress indicators for asynchronous state changes.

---

## 4. Simplified "Office-Staff" Vocabulary
While the back-end uses technical database keys, the UI uses simplified business terms:
- `PKID / REF_ID` -> **Ref #**
- `VAL_PHP / VAL_TOTAL` -> **Amount (PHP)**
- `TIMESTAMP / DEADLINE` -> **Date / Due Date**
- `Allocation / Disbursement` -> **Cash Issued**
- `Settlement / Reconciliation` -> **Liquidation**

---

## 5. UI Component Strategy

### 5.1 The "Instrument Card"
A standard container that looks like a digital readout.
- **Header:** Slate-50 background, 1px bottom border, Monospace ID in the corner.
- **Body:** White background, high-contrast text.

### 5.2 Status "Pills"
- **Design:** A neutral slate background with a high-intensity colored 6px circle (the signal) and uppercase text.
- **Example:** `[ ● APPROVED ]` where the circle is Emerald-500.

### 5.3 Liquidation Console (Reconciliation)
The synchronization module for outstanding liabilities requires specific telemetry:
- **Balance Variance**: A 3-tier visual system for reconciliation:
    - **MATCH (Emerald)**: ₱0.00 Variance.
    - **OVERPAYMENT (Amber)**: Positive Variance (Requires Debt Settlement).
    - **ABONO (Red)**: Negative Variance (Company Liability).
- **Aging Tracker**: A linear day-by-day countdown (1-7 days) that transitions into a red "PENALTY" ticker when overdue.

### 5.4 OCR-Enabled File Upload
- **Scanning State:** While processing, show a horizontal "scanning" line moving over the file preview.
- **Field Highlighting:** Low-confidence fields (OCR <80%) get a `bg-amber-50` background and a small `[?]` icon next to the value.

---

## 6. Implementation Checklist (Vue 3 / Tailwind)

### Tailwind Plugins/Utilities
- [ ] **Custom Grid:** Define a `grid-cols-[240px_1fr]` for the main sidebar-content layout.
- [ ] **Tabular Numbers:** Apply `font-variant-numeric: tabular-nums` to all table columns.

### Component Logic
- **`BaseInput.vue`**: Automatically toggles `font-mono` if the `type` is "number".
- **`DataTable.vue`**: Includes a `density` prop to switch between "Standard" and "Compact" (clinical) views.
- **`StatusBadge.vue`**: Uses a `switch` statement to map backend statuses to specific "Signal Light" colors.

---

## 7. UX Principles
- **Confirmations:** Destructive actions (e.g., Deleting a receipt) require a 2-second "Hold to Confirm" button interaction rather than a simple click.
- **Immediate Validation:** If a user enters an amount exceeding a category limit, the input border turns Red instantly with a small "OVER LIMIT" tag appearing above the field.