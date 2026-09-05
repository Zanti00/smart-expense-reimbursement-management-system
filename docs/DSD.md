# Design System Document (DSD)

**Project:** Smart Expense & Reimbursement Management System (SERMS)  
**System Name:** SERMS Clinical Visual System  
**Client / Partner:** Science Biotech Specialties Inc. (SBSI) — [https://sbsi.com.ph/about-us/](https://sbsi.com.ph/about-us/)  
**Academic Context:** 3rd & 4th Year Capstone Project (Capstone 1: Ended July 2026 · Capstone 2: September–December 2026)  
**Date:** 2026-09-01  
**Version:** 1.5.1  
**Owner:** SERMS Engineering Team  
**Status:** Active  
**Last reconciled:** 2026-09-01 (Documentation suite refactor & standardization of 'What this is' sections across all docs)  
**Canonical Spec:** [SERMS.md](SERMS.md) · **Related Docs:** [index.md](index.md) · [PRD.md](PRD.md) · [SAD.md](SAD.md) · [SDD.md](SDD.md) · [Build.md](Build.md) · [OPS.md](OPS.md) · [QAD.md](QAD.md) · [CHANGELOG.md](CHANGELOG.md) · [AGENTS.md](../AGENTS.md)

---

> **What this is:** The Design System Document (DSD) establishing the clinical visual design language, brand primitives, palette tokens, typography stack, component border and surface cleanliness constraints, and technical specifications for reusable UI components (`BaseButton`, `BaseTable`, `BaseModal`, `StatusBadge`, `OCRField`, `BaseKpiGrid`, `.card`, `.input`) in the Vue 3 frontend.

---

## 1. Design Philosophy & Vision

**Core aesthetic:** Clinical precision and institutional trust. SERMS is a financial compliance system — the UI must communicate authority, accuracy, and calm structure. No decorative noise, no playful gradients.

**Emotional intent:** Inspire confidence in financial data. Field engineers and finance officers must trust the numbers they see at a glance. Every UI element should reinforce that SERMS is a rigorous, professional tool.

**Aesthetic references:** Bloomberg Terminal structure, enterprise SaaS dashboards (Rippling, Ramp), clinical data platforms — dense but organized, with a deep indigo authority palette.

**What this system explicitly avoids:**
- Bright consumer color palettes that undermine the sense of financial gravity.
- Heavy shadows or skeuomorphic depth — flatness is functional here.
- Decorative motion that distracts from data — only micro-transitions that aid state comprehension.
- Colored backgrounds on primary content — white and `clinical` (#F7F9FD) surfaces keep data scannable.
- Colored top borders (`border-t-*`), top accent bars, or colored edges on cards, containers, and panels unless explicitly requested by the user.

---

## 2. Brand Primitives

### Colors

All color tokens are registered in [`tailwind.config.js`](../apps/web/tailwind.config.js) and consumed via Tailwind utility classes throughout the codebase.

#### Palette

| Name | Token (Tailwind) | HEX | Primary Usage |
|------|-----------------|-----|---------------|
| **Primary 500** | `primary` / `text-primary` | `#252578` | All headings, sidebar background, primary button fill, active states |
| **Primary 600** | `secondary` | `#2F2F7E` | Primary button hover state |
| **Accent 500** | `accent` | `#2E85D8` | CTA buttons, focus rings, interactive highlights, section labels, links |
| **Accent 50** | `accent-50` | `#EFF7FF` | Hover backgrounds on secondary elements, table row highlights |
| **Clinical** | `bg-clinical` | `#F7F9FD` | Main app background, page canvas |
| **Sterile** | `sterile` | `#FFFFFF` | Card surfaces, modal backgrounds, input fields |
| **Success** | `success` | `#059669` | Approved status, positive indicators, online dot |
| **Warning** | `warning` | `#D97706` | Overdue, incomplete, OCR confidence alert background |
| **Danger** | `danger` | `#DC2626` | Rejected status, error states, destructive action triggers |
| **OCR Alert** | `ocr.alert` | `#FEF9C3` | Background tint for low-confidence OCR fields |
| **Slate 700** | `slate-700` | `#1E293B` | Default body text, neutral content |
| **Slate 500** | `slate-500` | `#475569` | Secondary text, label hints, muted metadata |
| **Slate 300** | `slate-300` | `#94A3B8` | Placeholder text, disabled states, icon neutral |

> **Note:** The `slate` scale in SERMS is shifted one stop darker than Tailwind's defaults to improve readability on the light clinical background.

#### Status Color Map

Used exclusively in [`StatusBadge.vue`](../apps/web/src/components/base/StatusBadge.vue). All status badge colors and labels are defined there — do not define status colors inline elsewhere.

| Status | Color Class | Human Label |
|--------|-------------|-------------|
| `approved` | `bg-emerald-600` | Approved |
| `granted` | `bg-emerald-600` | Granted |
| `paid` | `bg-emerald-600` | Paid |
| `liquidated` | `bg-emerald-600` | Liquidated |
| `settled` | `bg-emerald-600` | Settled |
| `disbursed` | `bg-blue-600` | Disbursed |
| `signed` | `bg-blue-600` | Signed |
| `processing` | `bg-blue-600` | Processing |
| `processed` | `bg-primary` | Processed |
| `under-review` | `bg-violet-600` | Under Review |
| `pending` | `bg-amber-500` | Pending |
| `incomplete` | `bg-amber-500` | Incomplete |
| `unliquidated` | `bg-amber-500` | Unliquidated |
| `overpayment` | `bg-blue-500` | Overpayment |
| `overdue` | `bg-red-600` | Overdue |
| `revise` | `bg-orange-500` | Needs Revision |
| `rejected` | `bg-red-600` | Rejected |
| `reject` | `bg-red-600` | Reject |
| `automatic-rejected` | `bg-red-600` | Auto Rejected |
| `final-rejected` | `bg-red-700` | Final Rejected |
| `pending-admin-re-review` | `bg-accent` | Pending Re-review |
| `draft` | `bg-slate-500` | Draft |
| `flagged` | `bg-red-600` | Flagged |
| *(fallback)* | `bg-slate-400` | *(raw status)* |

---

### Typography

All fonts are loaded via Google Fonts API in [`index.css`](../apps/web/src/assets/index.css) at startup.

```css
@import url("https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Montserrat:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap");
```

#### Typography Stack & CSS Variables

The typography hierarchy is centralized in `:root` and mapped across Tailwind font families:

```css
:root {
  --font-primary: "Poppins", "Montserrat", "Open Sans", sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, monospace;
}
```

- **`font-sans`** (`['Poppins', 'Montserrat', 'Open Sans', ...]`) — Default application font family applied to `html`, `body`, forms, and general UI.
- **`font-heading`** (`['Poppins', 'Montserrat', ...]`) — Headings, modal titles, section labels, and card headers.
- **`font-display`** (`['Montserrat', 'Poppins', ...]`) — Large display figures and high-impact hero metrics.
- **`font-mono`** (`['JetBrains Mono', ...]`) — Numeric financial values, TINs, invoice IDs, OCR fields, and hashes.

#### Type Scale

| Role | Font Family | Tailwind Token | Weight | Size | Usage |
|------|-------------|---------------|--------|------|-------|
| **Body / Base UI** | Poppins, Montserrat, Open Sans | `font-sans` | 400–600 | `text-sm` (14px) | Default UI text, table cells, descriptions, form inputs |
| **Headings** | Poppins, Montserrat | `font-heading` | 600–700 | `text-sm`–`text-2xl` | Page titles, card section headings, modal titles |
| **Display / KPIs** | Montserrat, Poppins | `font-display` | 700–800 | `text-2xl`+ | Large KPI metric values, hero counters |
| **Monospace** | JetBrains Mono | `font-mono` | 400–600 | `text-xs`–`text-sm` | Financial amounts, TIN numbers, invoice IDs, pagination counters |
| **Labels & Badges** | Poppins, Montserrat | `font-heading` / `--font-primary` | 500–700 | `text-[10px]`–`text-[11px]` | Input labels, table headers, badge text, section eyebrow labels |

#### Centralized Text Utility Classes

Defined in `index.css` under `@layer components` to prevent ad-hoc inline font declarations:

| Utility Class | Typography & Tailwind Rules | Usage |
|---------------|-----------------------------|-------|
| `.text-page-title` | `font-family: var(--font-primary); text-2xl font-bold leading-tight text-slate-800` | Top-level view headers |
| `.text-section-title` | `font-family: var(--font-primary); text-sm font-semibold text-slate-700` | Card & panel sub-headers |
| `.text-field-label` | `font-family: var(--font-primary); text-xs font-medium text-slate-500` | Form field descriptions & secondary labels |
| `.text-body` | `font-family: var(--font-primary); text-sm text-slate-600` | Standard body copy |
| `.text-caption` | `font-family: var(--font-primary); text-xs text-slate-400` | Helper text, muted timestamps, footnotes |
| `.text-value` | `font-family: var(--font-primary); text-base font-semibold text-primary` | Prominent numeric & monetary amounts |
| `.text-value-lg` | `font-family: var(--font-primary); text-xl font-bold text-primary` | Large summary financial amounts |

#### Typography Rules

- **Input labels** use `--font-primary` at `11px` (`text-[11px]`), `font-semibold`, `uppercase`, `tracking-[0.02em]`, `text-slate-500`. Class: `.input-label`
- **Table headers** use `--font-primary` at `11px` (`text-[11px]`), `font-semibold`, `uppercase`, `tracking-[0.02em]`, `text-slate-500`. Class: `.table-base th`
- **Section eyebrow labels** use `--font-primary` at `11px` (`text-[11px]`), `font-semibold`, `uppercase`, `tracking-[0.02em]`, `text-accent`. Class: `.section-label`
- **KPI labels** use `--font-primary` at `11px` (`text-[11px]`), `font-medium`, `uppercase`, `tracking-[0.02em]`, `text-slate-500`. Class: `.kpi-label`
- **KPI values** use `--font-primary` at `text-2xl`, `font-bold`, `text-primary`. Class: `.kpi-value`
- **Financial amounts** in table cells and OCR cards always combine `font-mono` with the `tabular-nums` utility (`font-variant-numeric: tabular-nums`) to ensure vertical decimal alignment.
- **Date Display Standards:** All human-facing dates rendered across tables, cards, metadata rows, notifications, and status timestamps must strictly use the **Medium Date Format** (e.g. `Sept 1, 2026`, `Oct 14, 2026`, `Jan 15, 2026`) instead of numerical or raw formats (e.g., `YYYY-MM-DD` or `MM/DD/YYYY`).

---

### Elevation & Depth

SERMS uses minimal elevation. Shadows are barely perceptible — the system communicates depth through border and background contrast, not heavy shadows.

| Token | Value | Usage |
|-------|-------|-------|
| `shadow-sm` | `0 1px 2px 0 rgba(0,0,0,0.05)` | Cards, inputs, secondary buttons |
| `shadow-xl-soft` | `0 24px 72px -48px rgba(37,37,120,0.5)` | Elevated modals, focused panels |
| Card border | `border border-black/5` | Subtle card boundary — never a heavy rule |
| Focus ring | `0 0 0 2px #fff, 0 0 0 4px rgba(46,133,216,0.3)` | Keyboard focus on all interactive elements |

---

### Border Radius

| Token | Value | Usage |
|-------|-------|-------|
| `rounded-card` | `0.5rem` (8px) | All card containers, modal panels |
| `rounded-md` (sm) | `0.375rem` (6px) | Inputs, buttons, badges, small widgets |
| `rounded-full` | `9999px` | Status indicator dots, avatar placeholders, unread notification badge |

---

### Border & Surface Cleanliness Rules

- **Zero Top-Border Color (`border-t` prohibition):** Never add color to `border-t` or top borders of any component (e.g., `border-t-primary`, `border-t-accent`, `border-t-emerald-500`, top accent strips) unless the user explicitly prompts for it.
- **Clean & Plain Card Aesthetic:** Components such as cards, containers, modals, and data panels must always look clean, plain, and restrained. Do not overload components with background colors or colored border trims. Maintain clean, neutral surfaces (`bg-white/95`, `bg-clinical`) with subtle, uniform low-contrast borders (`border-black/5` or `border-slate-200`).
- **Functional Color Only:** Color in the UI must be reserved strictly for functional feedback (e.g., status badges, error validation states, active navigation indicators, explicit CTA buttons) rather than decorative frame borders or background fills.

---

## 3. Layout & Spatial System

**Base unit:** `4px` (Tailwind's default spacing scale). All margin, padding, and gaps use Tailwind's standard scale (`p-4` = 16px, `p-6` = 24px, `gap-4` = 16px).

### App Shell Layout

The application uses a fixed sidebar + scrollable main area layout defined in [`AppLayout.vue`](../apps/web/src/layouts/AppLayout.vue).

```
┌─────────────────────────────────────────────────────────┐
│  SIDEBAR (240px expanded / 64px collapsed)  │  HEADER   │
│  bg-primary (#252578)                       │  70px min │
│  ─────────────────────────────────────────  │  ─────────│
│  Logo / Brand Mark                          │  MAIN     │
│  ─────────────────────────────────────────  │  CONTENT  │
│  Nav links (sidebar-link)                   │  bg-clin- │
│  ─────────────────────────────────────────  │  ical     │
│  User status + Logout                       │  p-6      │
└─────────────────────────────────────────────────────────┘
```

| Zone | Dimensions | Background |
|------|------------|------------|
| Sidebar (expanded) | 240px fixed width | `bg-primary` (#252578) |
| Sidebar (collapsed) | 64px fixed width | `bg-primary` (#252578) |
| Console Header | Full width, `min-h-[70px]` | `bg-white/85` + `backdrop-blur-md` |
| Main Content | `flex-1`, `p-6`, scrollable | `bg-clinical` (#F7F9FD) |

### Page Content Width

Content within views uses a maximum width container: `max-w-5xl` for form views, fluid for dashboard and table views.

### Breakpoints

| Breakpoint | Tailwind Prefix | Width | Behavior |
|------------|----------------|-------|----------|
| Mobile | (base) | <1024px | Sidebar hidden behind mobile overlay; hamburger menu visible |
| Desktop | `lg:` | ≥1024px | Sidebar visible, collapsible |

---

## 4. Core Component Specs

All components live in [`apps/web/src/components/base/`](../apps/web/src/components/base/).

---

### Buttons — `BaseButton.vue`

Defined in [BaseButton.vue](../apps/web/src/components/base/BaseButton.vue). All interactive actions must use `BaseButton` or the `.btn` CSS class. Never write raw `<button>` styling.

| Variant | CSS Class | Background | Text | Border | Usage |
|---------|-----------|-----------|------|--------|-------|
| `primary` | `.btn-primary` | `#252578` | White | None | Standard action (Save, Submit) |
| `cta` | `.btn-cta` | `accent` (#2E85D8) | White | `border-white/10` | Primary submit in forms |
| `secondary` | `.btn-secondary` | `white/90` | `primary` | `border-black/5` | Secondary actions (Cancel, Filter) |
| `ghost` | `.btn-ghost` | Transparent | `slate-500` | None | Tertiary, icon-adjacent actions |
| `danger` | `.btn-danger` | `danger` (#DC2626) | White | None | Destructive actions |

**Sizes:** `sm` (`px-2.5 py-1.5 text-[10px]`), `md` (default, `px-5 py-2.5`), `icon` (`p-2`)

**Hold-to-Confirm pattern:** `BaseButton` supports `requireHold` prop for destructive actions. The button fills with a black/20 overlay over `holdDuration` (default 2000ms) before emitting `click`. A `(HOLD)` hint is shown to the user.

**Hover behavior (all variants):** `scale-[1.01]` + enhanced shadow on hover; `scale-[0.99]` on active. Transition: `all 200ms ease-out`.

**Focus ring:** `0 0 0 2px white, 0 0 0 4px rgba(46,133,216,0.3)` — always visible on keyboard navigation.

**Accounting Department Exclusivity & Role Scoping:** Interactive action triggers for accepting or rejecting requests (e.g. Approve/Reject `BaseButton` controls) and Admin modal actions are strictly scoped to users under the **Accounting Department** (`v-if="isAccountingUser"`). Non-Accounting users will not be rendered accept/reject action controls.

---

### Cards — `.card`

```css
.card {
  background: white/95;
  border: 1px solid rgba(0,0,0,0.05);
  border-radius: 0.5rem;   /* rounded-card */
  box-shadow: shadow-sm;
  backdrop-filter: blur(4px);
}
```

> [!IMPORTANT]
> **Component Cleanliness Rule:** Cards and panels must always look clean and plain by default. Never add colored top borders (`border-t`), multi-colored border outlines, or saturated background colors to card components unless the user explicitly prompts for it.

**Hover variant:** `.card-hover` adds `hover:border-accent/20 hover:shadow-xl hover:scale-[1.01]` for interactive list items.

---

### Inputs — `.input` + `.input-label`

Defined as CSS layer components in [`index.css`](../apps/web/src/assets/index.css).

```
[input-label]   10px · Poppins · BOLD · UPPERCASE · tracking-[0.04em] · text-slate-600
[input]         border-black/10 · rounded-md · px-3.5 py-2.5 · text-sm · bg-white/90
[input:focus]   border-color: #2E85D8 · box-shadow: 0 0 0 2px rgba(46,133,216,0.2)
[input-error]   border-danger/40 · bg-danger/5
```

**Wrapper:** Always use `.input-wrapper` (`flex flex-col gap-1`) to pair label + input.

---

### OCR Field — `OCRField.vue`

Defined in [OCRField.vue](../apps/web/src/components/base/OCRField.vue). Used inside `OCRExtractedFields.vue` for every OCR-extracted receipt field.

- **Normal state:** `bg-white`, `border-slate-300`, `rounded-none` (clinical, form-like)
- **Low confidence state** (`confidence < 80`): background shifts to `bg-amber-50/50`; a `[ Verify Accuracy ]` amber label appears inline
- **Financial / TIN fields:** rendered with `font-mono tabular-nums` for numeric alignment
- **Error state:** `border-danger`, `bg-danger/5`, error message in `text-[9px] text-danger uppercase`

---

### Status Badge — `StatusBadge.vue`

Defined in [StatusBadge.vue](../apps/web/src/components/base/StatusBadge.vue).

```
[badge]  .badge class → Poppins · 10px · BOLD · UPPERCASE · tracking-[0.04em]
         inline-flex · items-center · gap-1.5 · px-2.5 py-1
         bg-slate-50 · border border-black/5 · rounded-md

[dot]    1.5×1.5 rounded-full — color defined by status map (see §2 Status Color Map)
```

Never display raw API status strings. Always pass through `StatusBadge`.

---

### Data Table — `BaseTable.vue`

Defined in [BaseTable.vue](../apps/web/src/components/base/BaseTable.vue). All data grids must use `BaseTable`.

- **Container:** `.card overflow-hidden` with `overflow-x-auto max-h-[600px] scrollbar-thin`
- **Table root:** `.table-base` — `border-collapse`, `text-xs`, `text-left`
- **Headers `<th>`:** `--font-primary`, 11px (`text-[11px]`), font-semibold, uppercase, `tracking-[0.02em]`, `text-slate-500`, `bg-slate-50/90`, `sticky top-0`
- **Cells `<td>`:** `--font-primary`, `text-sm`, `text-slate-600`, `font-mono tabular-nums` for financial columns, `align-middle`
- **Row hover:** `bg-accent-50/50` — subtle accent tint
- **Active row:** `bg-accent/5` + `border-l-2 border-l-accent` — left accent stripe
- **Density:** supports `standard` (default) and `compact` (`py-1.5 px-3`)
- **Built-in:** sort (click header), client-side search filter, pagination

---

### Modal — `BaseModal.vue`

Defined in [BaseModal.vue](../apps/web/src/components/base/BaseModal.vue).

- **Backdrop:** `bg-slate-950/35 backdrop-blur-[1px]` — frosted glass dark overlay
- **Panel:** `rounded-xl border border-slate-200 bg-white shadow-2xl`
- **Enter animation:** `modal-pop` — `scale(0.95) translateY(8px)` → `scale(1) translateY(0)` over 200ms with `cubic-bezier(0.34, 1.56, 0.64, 1)` (slight spring overshoot)
- **Exit animation:** `opacity 0.15s ease-in`
- **Keyboard:** `Escape` key closes the modal
- **Click outside:** `@click.self` on backdrop closes the modal

---

### KPI Card — `.kpi-card` + `BaseKpiGrid.vue`

Defined in [BaseKpiGrid.vue](../apps/web/src/components/base/BaseKpiGrid.vue) + CSS in [`index.css`](../apps/web/src/assets/index.css).

```
[kpi-card]   .card · p-5 · flex-col · gap-0.5 · relative overflow-hidden
[kpi-value]  Poppins · text-2xl · font-bold · text-primary
[kpi-label]  Poppins · 11px · font-medium · uppercase · tracking-[0.02em] · text-slate-500
[skeleton]   BaseKpiCardSkeleton renders shimmer placeholders while loading
```

> **Note:** In alignment with the clean border rule, KPI cards must maintain a clean and neutral top edge without colored `border-t` accent strips unless explicitly requested by the user.

---

### Sidebar Navigation — `.sidebar-link`

```
[sidebar-link]       Poppins · text-sm · font-semibold · text-white/70
                     px-3 py-3 · mx-2 · rounded-md · border border-transparent
                     hover: bg-white/10 · text-white · border-white/10 · scale-[1.01]

[sidebar-link.active] bg-white/10 · text-white · border-white/10 · shadow-sm

[section header]      text-[10px] · font-heading · font-bold · uppercase · text-white/45
                      with a 6×6px accent dot (bg-accent opacity-70)

[admin navigation]    Admin sidebar routes (Audit Logs, Policy Management, Scoped Reports) are conditionally rendered exclusively for users in the **Accounting Department**.
```

---

### Secure Payload Indicator — `.secure-indicator`

Used on forms processing sensitive inputs (e.g., cash advance requests, accounting policy updates) to reassure the user that client-side payload encryption is active.

```
[secure-badge]   inline-flex · items-center · gap-1 · px-2 py-0.5
                 bg-emerald-50 · text-emerald-700 · border border-emerald-200/50
                 rounded-md · font-heading · text-[9px] · font-bold · uppercase
[icon]           Lucide ShieldCheck or Lock (size: 10px)
```

**Form Integration:** Renders in the top-right corner of card forms or inline next to submit actions. When the submit button is focused or pressed, the indicator flashes with a subtle border-pulse transition to signal transmission security.

---

## 5. Motion & Micro-interactions

**Global transition baseline:** All elements carry `transition-colors duration-200 ease-out` (applied globally in `@layer base`). This covers hover color changes system-wide without per-component declarations.

**Scale interactions** (applied on interactive elements):
- Hover: `scale-[1.01]`
- Active / Pressed: `scale-[0.99]`

| Interaction | Duration | Easing | Notes |
|-------------|----------|--------|-------|
| Button hover / active | `200ms ease-out` | — | Scale + shadow change |
| Hold-to-Confirm fill | Progressive via `requestAnimationFrame` | Linear | 2000ms default fill; cancelable |
| Modal enter | `200ms cubic-bezier(0.34, 1.56, 0.64, 1)` | Spring (slight overshoot) | `modal-pop` animation |
| Modal exit | `150ms ease-in` | — | Fade out |
| Page transition | `150ms linear` | — | Opacity fade on route change |
| Sidebar collapse/expand | `300ms ease-out` | — | Width transition on sidebar |
| Sidebar text fade | `150ms ease` + `300ms ease` (width) | — | Text fades in/out on collapse |
| OCR flagged field | `animate-pulse` | Tailwind default | Applied via `.ocr-flag` class |

**Reduced motion:** The system does not yet implement `prefers-reduced-motion` overrides. This is a known gap (see Self-Check).

---

## 6. Accessibility (a11y)

| Rule | Implementation |
|------|---------------|
| **Keyboard focus ring** | All `.btn` elements show `box-shadow: 0 0 0 2px white, 0 0 0 4px rgba(46,133,216,0.3)` on `:focus` |
| **Escape to close** | `BaseModal.vue` listens for `Escape` keydown event |
| **Inline validation** | Errors surface below failing fields via `.input-error` + error message — never toast-only |
| **Status indicators** | `StatusBadge` uses both a color dot AND a text label — never color alone |
| **ARIA / screen readers** | Buttons include `title` attributes on icon-only controls (e.g., sidebar links when collapsed) |
| **OCR low-confidence** | `OCRField` renders a visible `[ Verify Accuracy ]` text label — not color alone |
| **Touch targets** | Minimum button height achieved via `py-2.5` on standard `.btn` (~40px) |

**Known gap:** WCAG AA contrast for `text-white/70` over `bg-primary` (#252578) should be verified. The sidebar uses this combination for inactive nav text.

---

## 7. Taste-Skill Settings

```
DESIGN_VARIANCE:    2   — institutional precision; minimal deviation from established patterns
MOTION_INTENSITY:   2   — micro-transitions only; no decorative animation
VISUAL_DENSITY:     4   — data-dense tables and dashboards; compact but readable
```

---

## Self-Check

- [x] All color tokens sourced directly from `tailwind.config.js`
- [x] Typography scale verified against `index.css` `@layer base` and `@layer components`
- [x] Component specs verified against actual `.vue` source files in `components/base/`
- [x] Status badge color map sourced from `StatusBadge.vue` config object
- [x] Motion specs sourced from `AppLayout.vue`, `BaseModal.vue`, and `BaseButton.vue`
- [x] Layout dimensions sourced from `AppLayout.vue` sidebar classes
- [ ] `prefers-reduced-motion` support — **not yet implemented** (gap)
- [ ] WCAG AA contrast verification for `text-white/70` on `bg-primary` — **not yet verified** (gap)
