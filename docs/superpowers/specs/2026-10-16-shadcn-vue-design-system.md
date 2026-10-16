# ONE UI Component Design System Refactoring Specification
## Migration from Custom Vanilla CSS to shadcn-vue (Radix Vue) Layered Architecture

> **Document Identifier:** `docs/superpowers/specs/2026-10-16-shadcn-vue-design-system.md`  
> **Date:** October 16, 2026  
> **Status:** Approved Architecture Specification  
> **Primary Governance:** [`docs/DESIGN.md`](../../DESIGN.md) & [`AGENTS.md`](../../../AGENTS.md)  
> **Target Subsystem:** Smart Expense Reimbursement Management System (`apps/web`)  
> **Orchestrating Agent:** `@agent-organizer`

---

## 1. Executive Summary & Architectural Motivation

The frontend application (`apps/web`) currently utilizes custom vanilla CSS classes defined in `apps/web/src/assets/index.css` (e.g. `.btn`, `.btn-primary`, `.card`, `.input`, `.table-base`) combined with custom Tailwind color extensions. While functional, this setup lacks standardized accessible primitives, relies on manual DOM focus management, and mixes ad-hoc styling with component structure.

This specification defines the architectural migration of the component design system to **`shadcn-vue`** (powered by headless **`radix-vue`** primitives and Tailwind CSS 3.4).

### Key Architectural Decisions (from `/grill-me` Design Tree)
1. **Layered Adapter Pattern:** Scaffolds headless, unstyled primitives under `@/components/ui/` and refactors existing `@/components/base/` components as enterprise adapter wrappers. This preserves 100% backward compatibility for all 40+ views while upgrading the core engine to Radix Vue.
2. **Canonical ONE UI CSS Variables:** Exposes the exact hex codes from `docs/DESIGN.md Section III` as semantic CSS variables (`--primary: #252578`, `--secondary: #2E85D8`, `--destructive: #EF4444`, etc.) wired into `tailwind.config.js` with `tailwindcss-animate`.
3. **4-Phase Incremental Rollout:** Sequential execution gated by Vitest test suites (183 passing tests baseline) to ensure zero regressions.
4. **Staged Multi-Agent Pipeline:** Clear delegation across `@agent-organizer`, `@ui-designer`, `@frontend-developer`, `@qa-expert`, and `@code-reviewer`.
5. **Raw Button Modernization:** Systematically modernizes raw `<button class="btn ...">` elements across modals and views to `<BaseButton>` while retaining an interim CSS bridge.

---

## 2. Pinned Technology Stack & Package Directives

| Technology / Library | Version Pinned | Purpose & Architectural Boundaries |
| :--- | :--- | :--- |
| **Vue 3** | `^3.4.0` | Core SPA Framework |
| **radix-vue** | `^1.9.0` | Headless, accessible primitives (Dialog, DropdownMenu, Select, Tooltip, Collapsible) |
| **Tailwind CSS** | `^3.4.3` | **Strictly pinned to v3.4.** DO NOT introduce Tailwind v4 syntax or bump to v4. |
| **tailwindcss-animate**| `^1.0.7` | Keyframe and entrance/exit animations for Radix modals, dropdowns, and toasts |
| **clsx & tailwind-merge**| Latest | Utility `cn()` helper for dynamic class composition without specificity collisions |
| **class-variance-authority** (cva)| `^0.7.0` | Variant management for Buttons, Badges, and Cards |
| **lucide-vue-next** | `^0.373.0` | **Sole canonical icon source.** Raw SVGs or other icon fonts are strictly prohibited. |

---

## 3. Directory & Component Architecture

The project adopts the **Layered Adapter Architecture**:

```
apps/web/src/
├── utils/
│   └── cn.js                          <-- Class merging helper (clsx + tailwind-merge)
├── assets/
│   └── index.css                      <-- Canonical ONE UI CSS variables & font imports
├── components/
│   ├── ui/                            <-- Tier 1: Headless shadcn-vue Primitives
│   │   ├── button/
│   │   │   ├── Button.vue             <-- CVA Button primitive
│   │   │   └── index.js
│   │   ├── dialog/
│   │   │   ├── Dialog.vue             <-- Radix DialogRoot
│   │   │   ├── DialogTrigger.vue
│   │   │   ├── DialogContent.vue      <-- Level 4 elevation, backdrop blur, rounded-2xl
│   │   │   ├── DialogHeader.vue
│   │   │   ├── DialogFooter.vue
│   │   │   ├── DialogTitle.vue
│   │   │   ├── DialogDescription.vue
│   │   │   └── index.js
│   │   ├── dropdown-menu/
│   │   │   ├── DropdownMenu.vue       <-- Radix DropdownMenuRoot
│   │   │   ├── DropdownMenuContent.vue<-- Level 2 elevation, shadow-md
│   │   │   ├── DropdownMenuItem.vue
│   │   │   ├── DropdownMenuSeparator.vue
│   │   │   └── index.js
│   │   ├── select/
│   │   │   ├── Select.vue             <-- Radix SelectRoot
│   │   │   ├── SelectTrigger.vue
│   │   │   ├── SelectContent.vue
│   │   │   ├── SelectItem.vue         <-- Active highlight: Brand Accent #2E85D8
│   │   │   └── index.js
│   │   ├── badge/
│   │   │   ├── Badge.vue              <-- CVA Pill Badge (High, Medium, Low tiers)
│   │   │   └── index.js
│   │   ├── card/
│   │   │   ├── Card.vue               <-- Level 1 elevation, rounded-[14px], hover lift
│   │   │   ├── CardHeader.vue
│   │   │   ├── CardTitle.vue
│   │   │   ├── CardContent.vue
│   │   │   └── index.js
│   │   ├── skeleton/
│   │   │   ├── Skeleton.vue           <-- Pulse shimmer for layout loading
│   │   │   └── index.js
│   │   └── toast/
│   │       ├── Toast.vue              <-- Top-right fixed, 4px left strip, hover pause
│   │       └── index.js
│   │
│   └── base/                          <-- Tier 2: Canonical ONE UI Domain Adapters
│       ├── BaseButton.vue             <-- Wraps ui/button; hold-to-confirm, Loader2 spinner
│       ├── BaseInput.vue              <-- Wraps ui input pattern; uppercase eyebrow, ring-2
│       ├── StatusBadge.vue            <-- Wraps ui/badge; enforces Title Case & semantic map
│       ├── BaseModal.vue              <-- Wraps ui/dialog; centered header, backdrop blur
│       ├── ConfirmModal.vue           <-- Wraps ui/dialog; semantic badges, centered actions
│       ├── DecisionConfirmationModal.vue <-- Wraps ui/dialog; rejection reason & security auth
│       ├── ActionDropdownMenu.vue     <-- Wraps ui/dropdown-menu; 3-dot row action trigger
│       ├── BaseTable.vue              <-- Wraps ui/table; 56px rows, sort carets, two-line cells
│       └── ToastNotification.vue      <-- Global toast manager pinned at top-right
```

---

## 4. ONE UI Design Token & Styling Specification

### 4.1 Canonical Color Palette (`docs/DESIGN.md Section III`)
The application defines the following CSS custom properties in `apps/web/src/assets/index.css`:

```css
:root {
  --background: #F8F9FA;
  --foreground: #1E293B;
  
  --card: #FFFFFF;
  --card-foreground: #1E293B;
  
  --popover: #FFFFFF;
  --popover-foreground: #1E293B;
  
  --primary: #252578;
  --primary-foreground: #FFFFFF;
  
  --secondary: #2E85D8;
  --secondary-foreground: #FFFFFF;
  
  --brand-deep: #3A3A85;
  --brand-deep-border: #4E4E91;
  
  --muted: #F1F5F9;
  --muted-foreground: #8D8B8F;
  
  --accent: #EFF6FF;
  --accent-foreground: #252578;
  
  --destructive: #EF4444;
  --destructive-foreground: #FFFFFF;
  
  --success: #10B981;
  --success-foreground: #FFFFFF;
  
  --warning: #F59E0B;
  --warning-foreground: #FFFFFF;
  
  --border: #CBD5E1;
  --input: #E2E8F0;
  --ring: #2E85D8;
  
  --radius: 0.5rem; /* 8px base standard */
}
```

### 4.2 Typography Hierarchy (`docs/DESIGN.md Section II`)
- **Primary Font:** `Poppins` (`font-sans`), weights 400 (Regular), 600 (SemiBold), 700 (Bold). Applied to all UI headings, button labels, badge text, table headers, sidebar navigation, form labels.
- **Secondary Font:** `Montserrat`, weights 400, 500, 600. Restricted to secondary numerical readouts, timestamps, and peripheral copy.

### 4.3 8px Spacing Grid (`docs/DESIGN.md Section IV`)
Strict 8px grid hierarchy:
- `4px` (`p-1`): Compact badge/tag padding only.
- `8px` (`m-2` / `p-2`): Compact controls, button gaps, icon offsets.
- `16px` (`m-4` / `p-4`): Form gutters, mobile padding, compact cards.
- `24px` (`m-6` / `p-6`): Primary vertical rhythm between cards, standard dialog padding.
- `32px` (`m-8` / `p-8`): Desktop canvas boundary margins.
- `48px` (`m-12`): Major section separators.
- **Guardrail:** Arbitrary whitespace (e.g. `p-[13px]`, `h-[47px]`) is strictly prohibited.

### 4.4 Elevation Levels (`docs/DESIGN.md Section V`)
- **Level 1 (`shadow-sm`):** Metric Cards, Badges, Table Rows.
- **Level 2 (`shadow-md`):** Dropdown Panels, Hover Cards, Popovers, Action Menus.
- **Level 3 (`shadow-lg`):** Toast Notifications, Drawers, Side Panels.
- **Level 4 (`shadow-2xl`):** Modals, Confirmation Dialogs, Critical Overlays.

### 4.5 Bifurcated Loading Pattern (`docs/DESIGN.md Section VIII`)
- **Skeleton Shimmers:** Mandatory for data fetching and layout rendering (`SkeletonLoader.vue`, `ui/skeleton`).
- **Spinner Graphics:** Strictly Lucide `Loader2` with `animate-spin`, reserved exclusively for user actions, CTA button submissions, and file uploads.

---

## 5. Component Functional Specifications

### 5.1 BaseButton (`src/components/base/BaseButton.vue`)
- **Underlying Primitive:** `ui/button/Button.vue`.
- **Variants:** `primary` (Solid `#252578`), `secondary` (White/Slate `#CBD5E1`), `destructive` (Solid `#EF4444`), `outline`, `ghost`, `icon`, `cta`.
- **Micro-Interactions:** Hover 10% shift, active press compression `transform: scale(0.98)`, focus ring `ring-2 ring-[#2E85D8]`.
- **Hold-to-Confirm Support:** When `requireHold: true` is passed, holds mouse/touch for `holdDuration` (default 2000ms) with a linear progress bar overlay before emitting `click`.
- **Bifurcated Loading:** Renders Lucide `Loader2` spinning icon while preserving the exact bounding box dimensions.

### 5.2 StatusBadge (`src/components/base/StatusBadge.vue`)
- **Underlying Primitive:** `ui/badge/Badge.vue`.
- **Geometry:** Fully rounded pill (`rounded-full`), height 24px–28px, padding `px-3 py-1`.
- **Title Case Guardrail:** All status outputs are transformed to Title Case (e.g. `Approved`, `Pending`, `Under Review`, `Rejected`, `Liquidated`, `Settled`). ALL-CAPS is forbidden.
- **Emphasis Tiers:** Supports High (solid vibrant), Medium (pastel background with 1px border), and Low (tinted wash).

### 5.3 BaseModal & Confirmation Dialogs
- **Underlying Primitives:** `ui/dialog/Dialog*.vue`.
- **Backdrop:** `bg-slate-900/50 backdrop-blur-sm`.
- **Surface:** Borderless pure white, `rounded-2xl`, Level 4 elevation (`shadow-2xl`).
- **Header:** Centered circular status badge icon (rose for delete/reject, mint for approve), bold Poppins title (`text-xl font-bold text-slate-800`), muted description.
- **Footer:** Centered action footer with neutral light gray Cancel button alongside color-coded Primary Action button (`#EF4444` destructive, `#10B981` approve, `#252578` general).

### 5.4 ToastNotification (`src/components/ToastNotification.vue`)
- **Position:** Fixed viewport top-right (`top: 24px; right: 24px; z-index: 1200;`).
- **Structure:** `12px–14px` rounded corners, Level 3 elevation (`shadow-lg`), solid **4px vertical accent strip** on left edge (`#10B981` success, `#EF4444` error, `#F59E0B` warning).
- **Behavior:** 4-second auto-dismiss countdown that **automatically pauses** when the cursor hovers over the toast card. Standardized copy strings per Section VI.8.

### 5.5 AppLayout Navigation & Chrome (`src/layouts/AppLayout.vue`)
- **Sidebar:**
  - Solid Brand Primary (`#252578`).
  - System logo lockup with `sbsi-logo-white.png` and Poppins typography.
  - Category eyebrows in `rgba(255, 255, 255, 0.6)` uppercase.
  - Active links styled with solid Brand Deep (`#3A3A85`) pill, Brand Deep Border (`#4E4E91`), `rounded-lg`, and white text.
  - Collapsed mode centers icons and completely hides wordmarks/labels.
  - Pinned footer with subtle divider and Logout action.
- **Top Header:**
  - Pure white surface with `shadow-sm` and `backdrop-blur-md`.
  - Left: Lucide `PanelLeft` toggle button, 1px separator, Page Title (Poppins Bold `#1E293B`).
  - Right: Bell icon button with circular `#2E85D8` unread badge, 1px separator, interactive User Profile Card (`#A7A7C8` circular avatar, display name, role) with Radix Dropdown Menu.

---

## 6. Multi-Agent Team Responsibilities & Orchestration

| Subagent Role | Target Domain & Specific Mandate | Invocations & Phase Triggers |
| :--- | :--- | :--- |
| **`@agent-organizer`** | Team coordinator, task breakdown, status tracking in `task.md`, stage transitions. | Throughout all phases. |
| **`@ui-designer`** | Reviews token definitions in `index.css`/`tailwind.config.js`, verifies contrast ratios, audits Figma alignment. | Phase 1 (Tokens) & Phase 3 (Widget Aesthetics). |
| **`@frontend-developer`** | Authors primitives in `@/components/ui`, refactors `@/components/base`, updates `AppLayout.vue` and modals. | Phases 1, 2, 3, and 4 implementation tasks. |
| **`@qa-expert`** | Authors and runs Vitest suites, verifies regression prevention across all 33 test files. | After every phase completion gate. |
| **`@code-reviewer`** | Audits codebase against the 11-point Compliance Checklist in `docs/DESIGN.md Section X`. | Phase 4 completion before PR / final sign-off. |

---

## 7. Phased Implementation Roadmap

### Phase 1: Foundation, Styling Tokens & Core Primitives
- **1.1:** `@frontend-developer` installs `radix-vue`, `clsx`, `tailwind-merge`, `class-variance-authority`, `tailwindcss-animate` in `apps/web`.
- **1.2:** `@ui-designer` reviews and validates CSS variables in `apps/web/src/assets/index.css` and `apps/web/tailwind.config.js` against Section III tokens.
- **1.3:** `@frontend-developer` creates `apps/web/src/utils/cn.js` and `apps/web/components.json`.
- **1.4:** `@frontend-developer` scaffolds headless primitives under `apps/web/src/components/ui/` (`button`, `dialog`, `dropdown-menu`, `select`, `badge`, `card`, `skeleton`, `toast`).
- **1.5:** `@frontend-developer` refactors base components (`BaseButton.vue`, `BaseInput.vue`, `StatusBadge.vue`, `BaseModal.vue`, `ConfirmModal.vue`, `ToastNotification.vue`).
- **1.6:** `@qa-expert` runs Vitest base test suites (`BaseButton`, `BaseInput`, `StatusBadge`, `ToastNotification`, `BaseModal`). **Gate: 100% pass.**

### Phase 2: Navigation Chrome & Layout
- **2.1:** `@frontend-developer` refactors `apps/web/src/layouts/AppLayout.vue` to implement expanded/collapsed Sidebar, brand lockup, category eyebrows, Brand Deep active pills, and Top Header with Lucide `PanelLeft` and User Profile dropdown.
- **2.2:** `@qa-expert` verifies responsive layout toggling, router navigation, and logout triggers.

### Phase 3: Complex Widgets & Modal Modernization
- **3.1:** `@frontend-developer` refactors `BaseTable.vue`, `ActionDropdownMenu.vue`, `DecisionConfirmationModal.vue`, and `FileUpload.vue`.
- **3.2:** `@frontend-developer` modernizes raw inline `<button class="btn ...">` usages across modals and views to `<BaseButton>`.
- **3.3:** `@qa-expert` runs Vitest table, modal, and form tests. **Gate: 100% pass.**

### Phase 4: View Integration & Compliance Sign-Off
- **4.1:** Verify integration across all screens (`DashboardView.vue`, `LiquidationsView.vue`, `ReimbursementsView.vue`, `CashAdvancesView.vue`, Admin views).
- **4.2:** `@qa-expert` executes the complete test suite (all 33 test files, 183+ tests) verifying zero regressions.
- **4.3:** `@code-reviewer` executes the 11-point compliance audit from `docs/DESIGN.md Section X`.
- **4.4:** Record the documentation changelog entry in `docs/DOC_CHANGELOG.md` per `AGENTS.md`.

---

## 8. Verification & Compliance Checklist (`docs/DESIGN.md Section X`)

Before any phase or final task is marked complete, the team must verify:
- [ ] **Canonical Colors**: All colors strictly utilize the hex codes from Section III (`#252578`, `#2E85D8`, `#3A3A85`, `#4E4E91`, `#F8F9FA`, `#FFFFFF`, `#1E293B`, `#8D8B8F`, `#EF4444`, `#10B981`, `#F59E0B`). Zero unauthorized blues or grays.
- [ ] **Typography**: All headings, buttons, and navigation use `Poppins`. `Montserrat` is restricted to numerical readouts and secondary copy.
- [ ] **Spacing**: All margins and paddings are divisible by 8px (4px only for compact badges).
- [ ] **Icons**: All icons imported strictly from `lucide-vue-next`.
- [ ] **Loading States**: Skeletons used for data-fetching shimmers; Lucide `Loader2` spinning icon used for action buttons.
- [ ] **Elevations**: Shadows match Level 1 (`shadow-sm`) through Level 4 (`shadow-2xl`).
- [ ] **Status Badges**: Formatted in Title Case (`Approved`, `Pending`, `Rejected`). Single emphasis tier per table column.
- [ ] **Toasts**: Positioned top-right (`top: 24px; right: 24px; z-index: 1200;`), 4px left accent bar, 4-second hover-paused timer.
- [ ] **Tailwind 3.4**: Compatibility strictly maintained without Tailwind v4 features.
- [ ] **Figma Reference**: Canonical Figma project (`ciWpnQzNBU3Zopv97v29nN`, node `79-1078`) verified for geometry and alignment.
- [ ] **Automated Tests**: All 33 Vitest test files pass cleanly with zero failures.
