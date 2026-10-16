# ONE UI Component Design System Refactoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the entire frontend component design system of `apps/web` from custom vanilla CSS to `shadcn-vue` (Radix Vue primitives) using the Layered Adapter Pattern, strictly enforcing `docs/DESIGN.md` guardrails and preserving 100% test passing across the 183-test baseline.

**Architecture:** Implement headless primitives in `@/components/ui/` (`radix-vue`, `cva`, `cn()`), refactor `@/components/base/` as design system adapter wrappers with ONE UI brand defaults, modernize `AppLayout.vue` navigation chrome, and update modal/table widgets without breaking existing views.

**Tech Stack:** Vue 3.4 SPA, Radix Vue, Tailwind CSS 3.4 (strictly pinned), `tailwindcss-animate`, `class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-vue-next`, Vitest.

**Spec:** [`docs/superpowers/specs/2026-10-16-shadcn-vue-design-system.md`](../specs/2026-10-16-shadcn-vue-design-system.md)

## Global Constraints

- Zero Arbitrary Styling: Never inject arbitrary ad-hoc Tailwind classes, spontaneous colors, or non-standard spacing (`p-[13px]`, `bg-[#2a4599]`, `h-[47px]`). Every visual property must originate from ONE UI tokens.
- Framework & Package Pinning: `web-interface` is built with Vue 3 SPA, `shadcn-vue` (Radix Vue primitives), and Tailwind CSS 3.4. DO NOT bump `web-interface` to Tailwind v4. Do not introduce Tailwind v4 syntax into `web-interface`.
- Component Primitive Standard: All interactive components (Modals, Dialogs, Selects, Dropdowns, Tooltips, Accordions) must be sourced from `shadcn-vue` headless primitives styled with Tailwind CSS. Never install or use third-party pre-styled widget libraries.
- Icon Standard: Glyph assets must strictly and exclusively be sourced from Lucide Icons (`lucide-vue-next`). Do not import or mix FontAwesome, Material Icons, or raw unvetted SVGs.
- Strict 8px Spacing Hierarchy: Every margin and padding must adhere to the 8px grid (`8px`, `16px`, `24px`, `32px`, `48px`). 4px is allowed only for compact badge/tag padding.
- Bifurcated Loading States: Skeleton Loaders are mandatory for layout rendering and async data fetching. Spinner Graphics (`Loader2` from `lucide-vue-next`) are reserved exclusively for action triggers, button submissions, and network uploads.
- Title Case Status Badges: All status badge labels must use Title Case (e.g., `Active`, `Pending`, `Rejected`, `Approved`), never ALL-CAPS.
- Brand Primary Hex: `#252578` (`bg-primary`, `text-[#252578]`).
- Brand Accent Hex: `#2E85D8` (`bg-secondary`, `text-[#2e85d8]`).
- Brand Deep Hex: `#3A3A85` (Active sidebar link background).
- Brand Deep Border Hex: `#4E4E91` (Active sidebar link container border).
- Page Background Hex: `#F8F9FA` (`bg-slate-50`).
- Surface Pure White Hex: `#FFFFFF` (`bg-white`, `bg-card`).
- Dark Slate Hex: `#1E293B` (`text-slate-800`).
- Medium/Muted Slate Hex: `#8D8B8F` (`text-[#8d8b8f]`).
- Error / Destructive Hex: `#EF4444` (`text-red-500`, `bg-red-50`).
- Warning / Pending Hex: `#F59E0B` (`text-amber-500`, `bg-amber-50`, persistent `#FFFDF0`).
- Success / Approved Hex: `#10B981` (`text-emerald-500`, `bg-emerald-50`).
- Toast Notification Standard: Pinned at top-right (`top: 24px; right: 24px; z-index: 1200;`), Level 3 shadow (`shadow-lg`), 4px left accent bar, 4-second auto-dismiss with hover pause.
- Git Safety Rule: No mutating git commits without explicit user confirmation.

---

## Tasks

### Task 1: Package Dependencies, Class Merger Helper & Token Infrastructure

**Files:**
- Modify: `apps/web/package.json`
- Create: `apps/web/src/utils/cn.js`
- Create: `apps/web/components.json`
- Modify: `apps/web/src/assets/index.css`
- Modify: `apps/web/tailwind.config.js`
- Test: `apps/web/src/utils/cn.test.js`

**Interfaces:**
- Produces: `cn(...inputs: ClassValue[]): string` in `apps/web/src/utils/cn.js`
- Produces: Semantic CSS variables (`--primary`, `--secondary`, `--destructive`, `--radius`, etc.) in `index.css` and extended utilities in `tailwind.config.js`.

- [ ] **Step 1: Write the failing test for `cn()` utility**

Create `apps/web/src/utils/cn.test.js`:
```javascript
import { describe, it, expect } from 'vitest'
import { cn } from './cn'

describe('cn utility helper', () => {
  it('merges class names correctly', () => {
    expect(cn('px-2 py-1', 'bg-primary')).toBe('px-2 py-1 bg-primary')
  })

  it('resolves Tailwind class conflicts in favor of the latter class', () => {
    expect(cn('p-4', 'p-6')).toBe('p-6')
    expect(cn('text-red-500', 'text-blue-500')).toBe('text-blue-500')
  })

  it('handles conditional falsy values cleanly', () => {
    expect(cn('btn', false && 'btn-active', null, undefined, 'text-sm')).toBe('btn text-sm')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test -- apps/web/src/utils/cn.test.js`
Expected: FAIL with "Cannot find module './cn'"

- [ ] **Step 3: Install dependencies and configure `cn.js`, `components.json`, `index.css`, and `tailwind.config.js`**

Install dependencies in `apps/web`:
```bash
npm install radix-vue class-variance-authority clsx tailwind-merge tailwindcss-animate
```

Create `apps/web/src/utils/cn.js`:
```javascript
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}
```

Create `apps/web/components.json`:
```json
{
  "$schema": "https://shadcn-vue.com/schema.json",
  "style": "default",
  "tailwind": {
    "config": "tailwind.config.js",
    "css": "src/assets/index.css",
    "baseColor": "slate"
  },
  "aliases": {
    "components": "@/components",
    "utils": "@/utils/cn",
    "ui": "@/components/ui"
  }
}
```

Update `apps/web/src/assets/index.css` to add the canonical CSS variables at the root:
```css
@import url("https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Montserrat:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap");

@import "tailwindcss/base";
@import "tailwindcss/components";
@import "tailwindcss/utilities";

:root {
  --font-primary: "Poppins", "Montserrat", "Open Sans", sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, monospace;

  /* ONE UI Canonical Semantic Color Tokens */
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

  --radius: 0.5rem;
}
```

Update `apps/web/tailwind.config.js` to add `tailwindcss-animate` plugin and expose these variables while preserving Tailwind 3.4:
```javascript
import animate from 'tailwindcss-animate'

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}'
  ],
  theme: {
    extend: {
      colors: {
        border: 'var(--border)',
        input: 'var(--input)',
        ring: 'var(--ring)',
        background: 'var(--background)',
        foreground: 'var(--foreground)',
        primary: {
          DEFAULT: 'var(--primary)',
          foreground: 'var(--primary-foreground)',
          50: '#F4F5FF',
          100: '#E6E8FF',
          200: '#C8CCFF',
          300: '#9EA5F8',
          400: '#676DCD',
          500: '#252578',
          600: '#20206C',
          700: '#1A1A59',
          800: '#11113F',
          900: '#090923',
        },
        secondary: {
          DEFAULT: 'var(--secondary)',
          foreground: 'var(--secondary-foreground)',
          50: '#EFF7FF',
          100: '#DCEEFF',
          200: '#B9DCFA',
          300: '#86C2F4',
          400: '#54A5EA',
          500: '#2E85D8',
          600: '#1F6EBB',
          700: '#185695',
          800: '#153F6C',
          900: '#102A47',
        },
        brand: {
          deep: 'var(--brand-deep)',
          'deep-border': 'var(--brand-deep-border)',
        },
        destructive: {
          DEFAULT: 'var(--destructive)',
          foreground: 'var(--destructive-foreground)',
        },
        success: {
          DEFAULT: 'var(--success)',
          foreground: 'var(--success-foreground)',
        },
        warning: {
          DEFAULT: 'var(--warning)',
          foreground: 'var(--warning-foreground)',
        },
        muted: {
          DEFAULT: 'var(--muted)',
          foreground: 'var(--muted-foreground)',
        },
        accent: {
          DEFAULT: 'var(--accent)',
          foreground: 'var(--accent-foreground)',
        },
        card: {
          DEFAULT: 'var(--card)',
          foreground: 'var(--card-foreground)',
        },
        popover: {
          DEFAULT: 'var(--popover)',
          foreground: 'var(--popover-foreground)',
        },
        clinical: '#F8F9FA',
        sterile: '#FFFFFF',
        slate: {
          300: '#94A3B8',
          400: '#64748B',
          500: '#475569',
          600: '#334155',
          700: '#1E293B',
          800: '#0F172A',
          900: '#020617',
        },
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
        card: '14px',
      },
      boxShadow: {
        sm: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
        md: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06)',
        lg: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05)',
        '2xl': '0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.08)',
      },
      fontFamily: {
        sans: ['Poppins', 'Montserrat', 'Open Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        heading: ['Poppins', 'Montserrat', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      }
    }
  },
  plugins: [animate]
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test -- apps/web/src/utils/cn.test.js`
Expected: PASS (all 3 tests pass)

---

### Task 2: Tier 1 Headless Primitives Scaffolding (`@/components/ui/`)

**Files:**
- Create: `apps/web/src/components/ui/button/Button.vue`
- Create: `apps/web/src/components/ui/button/index.js`
- Create: `apps/web/src/components/ui/badge/Badge.vue`
- Create: `apps/web/src/components/ui/badge/index.js`
- Create: `apps/web/src/components/ui/card/Card.vue`
- Create: `apps/web/src/components/ui/card/CardHeader.vue`
- Create: `apps/web/src/components/ui/card/CardTitle.vue`
- Create: `apps/web/src/components/ui/card/CardContent.vue`
- Create: `apps/web/src/components/ui/card/index.js`
- Create: `apps/web/src/components/ui/dialog/Dialog.vue`
- Create: `apps/web/src/components/ui/dialog/DialogTrigger.vue`
- Create: `apps/web/src/components/ui/dialog/DialogContent.vue`
- Create: `apps/web/src/components/ui/dialog/DialogHeader.vue`
- Create: `apps/web/src/components/ui/dialog/DialogFooter.vue`
- Create: `apps/web/src/components/ui/dialog/DialogTitle.vue`
- Create: `apps/web/src/components/ui/dialog/DialogDescription.vue`
- Create: `apps/web/src/components/ui/dialog/index.js`
- Create: `apps/web/src/components/ui/skeleton/Skeleton.vue`
- Create: `apps/web/src/components/ui/skeleton/index.js`
- Test: `apps/web/src/components/ui/ui-primitives.test.js`

**Interfaces:**
- Consumes: `cn` from `@/utils/cn`, `radix-vue` primitive components, `cva`
- Produces: Exported UI primitives in `@/components/ui/`

- [ ] **Step 1: Write the failing unit tests for UI primitives**

Create `apps/web/src/components/ui/ui-primitives.test.js`:
```javascript
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { Button } from './button'
import { Badge } from './badge'
import { Card, CardTitle } from './card'
import { Skeleton } from './skeleton'

describe('Tier 1 UI Primitives', () => {
  it('renders Button with primary variant and Poppins font', () => {
    const wrapper = mount(Button, {
      slots: { default: 'Click Me' },
      props: { variant: 'primary' }
    })
    expect(wrapper.text()).toBe('Click Me')
    expect(wrapper.classes()).toContain('bg-primary')
    expect(wrapper.classes()).toContain('font-sans')
  })

  it('renders Badge with rounded-full geometry and medium tier', () => {
    const wrapper = mount(Badge, {
      slots: { default: 'Active' },
      props: { variant: 'success' }
    })
    expect(wrapper.text()).toBe('Active')
    expect(wrapper.classes()).toContain('rounded-full')
  })

  it('renders Card with Level 1 elevation and rounded-[14px]', () => {
    const wrapper = mount(Card, {
      slots: { default: '<div class="content">Metric</div>' }
    })
    expect(wrapper.classes()).toContain('rounded-[14px]')
    expect(wrapper.classes()).toContain('shadow-sm')
  })

  it('renders Skeleton with animate-pulse', () => {
    const wrapper = mount(Skeleton)
    expect(wrapper.classes()).toContain('animate-pulse')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test -- apps/web/src/components/ui/ui-primitives.test.js`
Expected: FAIL with missing module imports.

- [ ] **Step 3: Implement the UI primitives**

1. Create `apps/web/src/components/ui/button/Button.vue`:
```vue
<script setup>
import { cva } from 'class-variance-authority'
import { Primitive } from 'radix-vue'
import { cn } from '@/utils/cn'

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 rounded-md font-sans text-xs font-semibold transition-all duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        primary: 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 active:scale-[0.98]',
        secondary: 'bg-white text-slate-800 border border-slate-200 shadow-sm hover:bg-slate-100 active:scale-[0.98]',
        destructive: 'bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90 active:scale-[0.98]',
        outline: 'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50',
        ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        link: 'text-secondary underline-offset-4 hover:underline',
        cta: 'bg-secondary text-white shadow-md hover:bg-secondary/90 active:scale-[0.98]',
      },
      size: {
        default: 'h-9 px-4 py-2',
        sm: 'h-7 rounded-md px-2.5 text-[10px]',
        lg: 'h-11 rounded-md px-6 text-sm',
        icon: 'h-8 w-8 p-1.5',
      },
    },
    defaultVariants: {
      variant: 'primary',
      size: 'default',
    },
  }
)

const props = defineProps({
  as: { type: String, default: 'button' },
  asChild: { type: Boolean, default: false },
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'default' },
  class: { type: String, default: '' },
})
</script>

<template>
  <Primitive
    :as="as"
    :as-child="asChild"
    :class="cn(buttonVariants({ variant, size }), props.class)"
  >
    <slot />
  </Primitive>
</template>
```

Create `apps/web/src/components/ui/button/index.js`:
```javascript
export { default as Button } from './Button.vue'
```

2. Create `apps/web/src/components/ui/badge/Badge.vue`:
```vue
<script setup>
import { cva } from 'class-variance-authority'
import { cn } from '@/utils/cn'

const badgeVariants = cva(
  'inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold font-sans transition-colors',
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground',
        secondary: 'bg-secondary text-secondary-foreground',
        success: 'bg-emerald-50 text-emerald-600 border border-emerald-300',
        warning: 'bg-amber-50 text-amber-600 border border-amber-300',
        destructive: 'bg-red-50 text-red-600 border border-red-300',
        outline: 'text-foreground border border-slate-200',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
)

const props = defineProps({
  variant: { type: String, default: 'default' },
  class: { type: String, default: '' },
})
</script>

<template>
  <div :class="cn(badgeVariants({ variant }), props.class)">
    <slot />
  </div>
</template>
```

Create `apps/web/src/components/ui/badge/index.js`:
```javascript
export { default as Badge } from './Badge.vue'
```

3. Create `apps/web/src/components/ui/card/Card.vue`:
```vue
<script setup>
import { cn } from '@/utils/cn'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <div :class="cn('rounded-[14px] bg-white border border-slate-100 shadow-sm text-foreground transition-all duration-200 hover:shadow-md hover:-translate-y-1', props.class)">
    <slot />
  </div>
</template>
```

Create `apps/web/src/components/ui/card/CardHeader.vue`:
```vue
<script setup>
import { cn } from '@/utils/cn'
const props = defineProps({ class: { type: String, default: '' } })
</script>
<template>
  <div :class="cn('flex flex-col space-y-1.5 p-6', props.class)">
    <slot />
  </div>
</template>
```

Create `apps/web/src/components/ui/card/CardTitle.vue`:
```vue
<script setup>
import { cn } from '@/utils/cn'
const props = defineProps({ class: { type: String, default: '' } })
</script>
<template>
  <h3 :class="cn('font-sans text-lg font-bold leading-none tracking-tight text-slate-800', props.class)">
    <slot />
  </h3>
</template>
```

Create `apps/web/src/components/ui/card/CardContent.vue`:
```vue
<script setup>
import { cn } from '@/utils/cn'
const props = defineProps({ class: { type: String, default: '' } })
</script>
<template>
  <div :class="cn('p-6 pt-0', props.class)">
    <slot />
  </div>
</template>
```

Create `apps/web/src/components/ui/card/index.js`:
```javascript
export { default as Card } from './Card.vue'
export { default as CardHeader } from './CardHeader.vue'
export { default as CardTitle } from './CardTitle.vue'
export { default as CardContent } from './CardContent.vue'
```

4. Create `apps/web/src/components/ui/dialog/` components using `radix-vue`:
`Dialog.vue` (wraps `DialogRoot`), `DialogTrigger.vue`, `DialogContent.vue` (`DialogPortal`, `DialogOverlay` with `bg-slate-900/50 backdrop-blur-sm`, `DialogContent` with `rounded-2xl shadow-2xl p-6 bg-white`), `DialogHeader.vue`, `DialogFooter.vue`, `DialogTitle.vue`, `DialogDescription.vue`, and export them in `apps/web/src/components/ui/dialog/index.js`.

5. Create `apps/web/src/components/ui/skeleton/Skeleton.vue`:
```vue
<script setup>
import { cn } from '@/utils/cn'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <div :class="cn('animate-pulse rounded-md bg-slate-200/70', props.class)" />
</template>
```

Create `apps/web/src/components/ui/skeleton/index.js`:
```javascript
export { default as Skeleton } from './Skeleton.vue'
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test -- apps/web/src/components/ui/ui-primitives.test.js`
Expected: PASS (all 4 tests pass)

---

### Task 3: Tier 2 Base Adapter Components Refactoring (`@/components/base/`)

**Files:**
- Modify: `apps/web/src/components/base/BaseButton.vue`
- Modify: `apps/web/src/components/base/BaseInput.vue`
- Modify: `apps/web/src/components/base/StatusBadge.vue`
- Modify: `apps/web/src/components/base/BaseModal.vue`
- Modify: `apps/web/src/components/base/ConfirmModal.vue`
- Modify: `apps/web/src/components/ToastNotification.vue`
- Test: Existing component tests (`ToastNotification.test.js`, `StatusBadge.vue` consumers)

**Interfaces:**
- Consumes: `@/components/ui/button`, `@/components/ui/badge`, `@/components/ui/dialog`
- Produces: Backwards-compatible components in `@/components/base/` satisfying all existing prop signatures (`variant`, `requireHold`, `isOpen`, `status`).

- [ ] **Step 1: Inspect existing base component test suites to verify baseline**

Run: `npm test -- apps/web/src/components/ToastNotification.test.js`
Expected: PASS

- [ ] **Step 2: Refactor `BaseButton.vue` to wrap `ui/button`**

Update `apps/web/src/components/base/BaseButton.vue`:
- Import `Button` from `@/components/ui/button`.
- Retain `requireHold`, `holdDuration`, `loading`, and `disabled` props.
- Render Lucide `Loader2` with `animate-spin` when `loading: true` as mandated by bifurcated loading rule.
- Preserve hold-to-confirm progress overlay when `requireHold: true`.

- [ ] **Step 3: Refactor `StatusBadge.vue` to wrap `ui/badge` and enforce Title Case**

Update `apps/web/src/components/base/StatusBadge.vue`:
- Import `Badge` from `@/components/ui/badge`.
- Ensure all labels are Title Case (`Approved`, `Pending`, `Under Review`, `Rejected`, `Liquidated`, `Settled`).
- Map status strings to canonical semantic colors (`emerald` for approved, `amber` for pending, `red` for rejected).

- [ ] **Step 4: Refactor `BaseModal.vue` and `ConfirmModal.vue` to wrap `ui/dialog`**

Update `apps/web/src/components/base/BaseModal.vue`:
- Use `DialogRoot` (v-model:open driven by `isOpen`), `DialogPortal`, `DialogOverlay` (`bg-slate-900/50 backdrop-blur-sm`), and `DialogContent` (`rounded-2xl shadow-2xl p-6 bg-white`).
- Emit `close` when dialog open state changes to false or Escape is pressed.

Update `apps/web/src/components/base/ConfirmModal.vue`:
- Centered status circular badge at top (red for danger/delete, green for approve).
- Centered action footer with `<BaseButton variant="secondary">` for cancel and `<BaseButton :variant="confirmVariant">` for confirm.

- [ ] **Step 5: Refactor `ToastNotification.vue`**

Ensure `ToastNotification.vue` renders at viewport top-right (`top: 24px; right: 24px; z-index: 1200;`), with 4px left vertical accent bar (`#10B981` success, `#EF4444` error), Level 3 shadow (`shadow-lg`), and pauses auto-dismiss countdown on `mouseenter`.

- [ ] **Step 6: Run Vitest base test suites to verify passing status**

Run: `npm test -- apps/web/src/components/ToastNotification.test.js apps/web/src/components/base/`
Expected: PASS with 0 regressions.

---

### Task 4: Global Layout & Navigation Chrome Modernization (`AppLayout.vue`)

**Files:**
- Modify: `apps/web/src/layouts/AppLayout.vue`
- Test: `apps/web/src/layouts/AppLayout.test.js`

**Interfaces:**
- Consumes: `Button` from `@/components/ui/button`, `DropdownMenu` primitives, `lucide-vue-next` (`PanelLeft`, `Bell`, `LogOut`, etc.)
- Produces: Canonical ONE UI responsive sidebar and header navigation.

- [ ] **Step 1: Write unit test for `AppLayout.vue` navigation**

Create `apps/web/src/layouts/AppLayout.test.js`:
```javascript
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AppLayout from './AppLayout.vue'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: { template: '<div>Home</div>' }, meta: { title: 'Dashboard' } }
  ]
})

describe('AppLayout Navigation Chrome', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders sidebar with canonical background Brand Primary #252578', async () => {
    const wrapper = mount(AppLayout, {
      global: { plugins: [router] }
    })
    const aside = wrapper.find('aside')
    expect(aside.exists()).toBe(true)
    expect(aside.classes()).toContain('bg-primary')
  })

  it('renders header with sidebar toggle button', () => {
    const wrapper = mount(AppLayout, {
      global: { plugins: [router] }
    })
    expect(wrapper.find('header').exists()).toBe(true)
  })
})
```

- [ ] **Step 2: Run test to verify initial state**

Run: `npm test -- apps/web/src/layouts/AppLayout.test.js`

- [ ] **Step 3: Refactor `AppLayout.vue`**

Update `apps/web/src/layouts/AppLayout.vue`:
1. **Sidebar:**
   - Background: `bg-primary` (`#252578`).
   - Active navigation pill: `bg-brand-deep` (`#3A3A85`), `border border-brand-deep-border` (`#4E4E91`), `rounded-lg`, white text.
   - Category eyebrows: uppercase text in `text-white/60 font-bold text-[10px] tracking-wider`.
   - Collapsed state: width `w-16`, centered icons, text labels hidden.
   - Pinned footer: `border-t border-white/20`, operator status, Logout button.
2. **Top Header:**
   - Background: `bg-white/90 backdrop-blur-md border-b border-slate-100 shadow-sm`.
   - Sidebar toggle: Lucide `PanelLeft` button (`w-5 h-5`).
   - Page Title: Poppins Bold (700), Dark Slate (`text-slate-800`), 18px–20px.
   - User profile dropdown trigger: Avatar circle in `#A7A7C8` with white user initials, user display name (Poppins SemiBold), and user role (Poppins Regular).
   - Dropdown menu: Radix `DropdownMenu` floating 8px below with `My Profile`, 1px divider, and red `Logout` action (`text-red-600 hover:bg-red-50`).

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test -- apps/web/src/layouts/AppLayout.test.js`
Expected: PASS

---

### Task 5: Complex Widgets Modernization & Raw Button Refactoring

**Files:**
- Modify: `apps/web/src/components/base/BaseTable.vue`
- Modify: `apps/web/src/components/base/ActionDropdownMenu.vue`
- Modify: `apps/web/src/components/base/DecisionConfirmationModal.vue`
- Modify: `apps/web/src/components/expenses/ReceiptUploadModal.vue`
- Modify: `apps/web/src/components/cash-advances/CashAdvanceDetailsModal.vue`
- Modify: `apps/web/src/components/liquidations/LiquidationSettlementForm.vue`
- Test: Existing table and modal test suites (`LiquidationTable.test.js`, `ReceiptUploadModal.spec.js`, `DecisionConfirmationModal.vue` consumers)

**Interfaces:**
- Consumes: `BaseButton.vue`, `BaseInput.vue`, `ui/dropdown-menu`, `ui/dialog`
- Produces: Standardized data tables and modal workflows with zero raw inline buttons.

- [x] **Step 1: Run existing test suites for tables and modals to establish baseline**

Run: `npm test -- apps/web/src/components/liquidations/LiquidationTable.test.js apps/web/src/components/expenses/ReceiptUploadModal.spec.js`
Expected: PASS

- [x] **Step 2: Refactor `BaseTable.vue` and `ActionDropdownMenu.vue`**

Update `apps/web/src/components/base/BaseTable.vue`:
- Ensure column headers use uppercase Poppins, bold, expanded letter tracking, Muted Slate (`text-slate-400`).
- Ensure rows have minimum height of 56px (`min-h-[56px]`), subtle horizontal bottom divider (`border-b border-slate-100`), hover background `hover:bg-slate-50`.
- Represent two-line dates/users cleanly with stacked spans.

Update `apps/web/src/components/base/ActionDropdownMenu.vue`:
- Wrap `radix-vue` `DropdownMenu` primitives.
- Trigger with three-dot icon (`MoreVertical`).
- Dropdown items styled with `hover:bg-slate-100 rounded-md cursor-pointer px-3 py-2 text-sm`.

- [x] **Step 3: Refactor `DecisionConfirmationModal.vue`**

Update `apps/web/src/components/base/DecisionConfirmationModal.vue`:
- Use `ui/dialog` primitives.
- Centered header with circular status icon badge.
- Rejection reason textarea with validation.
- Centered action footer with `<BaseButton variant="secondary">` and `<BaseButton :variant="decisionVariant">`.

- [x] **Step 4: Modernize raw buttons in modals**

Replace raw `<button class="btn btn-secondary ...">` and `<button class="btn btn-primary ...">` in:
- `apps/web/src/components/expenses/ReceiptUploadModal.vue`
- `apps/web/src/components/cash-advances/CashAdvanceDetailsModal.vue`
- `apps/web/src/components/liquidations/LiquidationSettlementForm.vue`
with `<BaseButton variant="secondary">` and `<BaseButton variant="primary">`.

- [x] **Step 5: Run Vitest suites for tables and modals**

Run: `npm test -- apps/web/src/components/liquidations/LiquidationTable.test.js apps/web/src/components/expenses/ReceiptUploadModal.spec.js`
Expected: PASS

---

### Task 6: View Integration, Full Vitest Regression Audit & Compliance Sign-Off

**Files:**
- Audit: `apps/web/src/views/DashboardView.vue`
- Audit: `apps/web/src/views/LiquidationsView.vue`
- Audit: `apps/web/src/views/ReimbursementsView.vue`
- Audit: `apps/web/src/views/CashAdvancesView.vue`
- Modify: `docs/DOC_CHANGELOG.md`
- Test: Full Vitest suite (all 33 test files)

**Interfaces:**
- Consumes: All updated base components, layouts, and styles.
- Produces: 100% compliant ONE UI design system across the entire application with 0 test failures.

- [x] **Step 1: Run the full Vitest test suite**

Run: `npm test` in `apps/web`
Expected: PASS across all 33 test files (183+ tests passing with 0 failures).

- [x] **Step 2: Verify `docs/DESIGN.md Section X` Compliance Checklist**

Execute verification for all 11 compliance points:
1. Canonical Colors: Only `#252578`, `#2E85D8`, `#3A3A85`, `#4E4E91`, `#F8F9FA`, `#FFFFFF`, `#1E293B`, `#8D8B8F`, `#EF4444`, `#10B981`, `#F59E0B`.
2. Typography: Primary headings and labels use `Poppins`; numerical readouts use `Montserrat`.
3. Spacing: 8px grid hierarchy strictly maintained.
4. Icons: Exclusively `lucide-vue-next`.
5. Loading states: Skeletons for layout loading, `Loader2` spinner for action submissions.
6. Elevations: Level 1 (`shadow-sm`) through Level 4 (`shadow-2xl`).
7. Status Badges: Title Case strictly enforced; single tier per column.
8. Toasts: Top-right viewport fixed, 4px left strip, 4-second hover-paused timer.
9. Tailwind 3.4: Tailwind 3.4 compatibility preserved with zero Tailwind 4 features.
10. Figma alignment: Component geometry verified against canonical ONE UI specs.
11. Automated tests: All 33 test files pass.

- [x] **Step 3: Record entry in `docs/DOC_CHANGELOG.md`**

Append changelog entry documenting the migration from vanilla CSS to shadcn-vue / Radix Vue design system architecture in `docs/DOC_CHANGELOG.md`.
