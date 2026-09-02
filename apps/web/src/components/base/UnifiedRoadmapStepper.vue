<script setup>
import { computed } from "vue";
import {
  Check,
  Clock,
  X,
  AlertTriangle,
  Banknote,
  FileText,
  ShieldCheck,
  Wallet,
  ArrowRight,
  ArrowLeft,
  ArrowDown,
  CircleDot,
} from "lucide-vue-next";
import { formatPeso, formatDate } from "@/utils/formatters";
import RoadmapStepItem from "./RoadmapStepItem.vue";

const props = defineProps({
  cashAdvance: {
    type: Object,
    required: true,
  },
  liquidation: {
    type: Object,
    default: null,
  },
  statusHistory: {
    type: Array,
    default: () => [],
  },
  penalties: {
    type: Array,
    default: () => [],
  },
  overpaymentAmount: {
    type: Number,
    default: 0,
  },
  /** optional aging object from calculateAging to derive overdue days */
  aging: {
    type: Object,
    default: null,
  },
  /** layout variant: 'horizontal' (default linear 1-row) or 'serpentine' (4x2 reverted-S for narrower forms) */
  layout: {
    type: String,
    default: "horizontal",
    validator: (val) => ["serpentine", "horizontal"].includes(val),
  },
});

const emit = defineEmits(["navigate", "view-cash-advance", "view-liquidation"]);

function normalize(val) {
  return String(val || "")
    .toLowerCase()
    .trim()
    .replace(/[\s/_-]+/g, "-");
}

function formatDateOnly(value) {
  if (!value) return null;
  const formatted = formatDate(value);
  return formatted === "—" ? null : formatted;
}

function formatActorName(actor) {
  if (!actor) return null;
  if (typeof actor === "string") {
    const trimmed = actor.trim();
    if (trimmed.startsWith("{") && trimmed.endsWith("}")) {
      try {
        const parsed = JSON.parse(trimmed);
        return formatActorName(parsed);
      } catch {
        return trimmed;
      }
    }
    return trimmed;
  }
  if (typeof actor === "object") {
    return (
      actor.name ||
      actor.full_name ||
      actor.fullName ||
      actor.username ||
      actor.user_name ||
      actor.userName ||
      actor.email ||
      null
    );
  }
  return String(actor);
}

function resolveHistoryDate(statusKeys) {
  if (!props.statusHistory || props.statusHistory.length === 0) return null;
  const keys = statusKeys.map(normalize);
  // search in reverse to find latest matching entry
  for (let i = props.statusHistory.length - 1; i >= 0; i--) {
    const h = props.statusHistory[i];
    if (!h) continue;
    const s = normalize(
      h.status ||
        h.to_status ||
        h.toStatus ||
        h.new_status ||
        h.state ||
        h.action ||
        "",
    );
    // also check h.from_status vs to_status? we match target status
    if (keys.includes(s)) {
      const rawActor =
        h.actor_name ||
        h.actorName ||
        h.user_name ||
        h.userName ||
        h.changed_by_name ||
        h.changedByName ||
        h.performed_by_name ||
        h.actor ||
        h.changed_by ||
        h.changedBy ||
        h.user ||
        h.performed_by ||
        h.approver ||
        null;

      return {
        raw: h,
        date:
          h.changed_at ||
          h.changedAt ||
          h.created_at ||
          h.createdAt ||
          h.date ||
          h.timestamp ||
          h.updated_at ||
          null,
        actor: formatActorName(rawActor),
        comment:
          typeof h.comment === "string"
            ? h.comment
            : (typeof h.reason === "string"
                ? h.reason
                : (typeof h.note === "string" ? h.note : null)),
      };
    }
  }
  return null;
}

function revisionCountForStep(stepKey) {
  if (["request", "approval"].includes(stepKey)) {
    return Number(
      props.cashAdvance?.revision_count ??
        props.cashAdvance?.revisionCount ??
        0,
    );
  }
  if (["liq-submitted", "under-review", "decision"].includes(stepKey)) {
    return Number(
      props.liquidation?.revision_count ??
        props.liquidation?.revisionCount ??
        props.cashAdvance?.revision_count ??
        0,
    );
  }
  return 0;
}

const caStatus = computed(() => normalize(props.cashAdvance?.status));
const liqStatus = computed(() => normalize(props.liquidation?.status));

const isOverdue = computed(() => {
  if (normalize(props.cashAdvance?.status) === "overdue") return true;
  if (props.aging?.isOverdue) return true;
  if (props.penalties && props.penalties.length > 0) return true;
  const due =
    props.cashAdvance?.expected_liquidation_date || props.cashAdvance?.dueDate;
  if (!due) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const d = new Date(due);
  d.setHours(0, 0, 0, 0);
  const terminal = ["liquidated", "settled", "rejected"];
  if (terminal.includes(caStatus.value)) return false;
  if (["pending", "approved", "under-review"].includes(caStatus.value))
    return false;
  return d < today;
});

const totalPenalty = computed(() => {
  if (props.penalties && props.penalties.length > 0) {
    return props.penalties.reduce(
      (s, p) =>
        s + Number(p.penalty_amount ?? p.amount ?? p.penaltyAmount ?? 0),
      0,
    );
  }
  if (
    props.cashAdvance?.penalties &&
    Array.isArray(props.cashAdvance.penalties)
  ) {
    return props.cashAdvance.penalties.reduce(
      (s, p) => s + Number(p.penalty_amount ?? p.amount ?? 0),
      0,
    );
  }
  if (props.aging?.penalty) return Number(props.aging.penalty);
  if (isOverdue.value) {
    const due =
      props.cashAdvance?.expected_liquidation_date ||
      props.cashAdvance?.dueDate;
    if (due) {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const d = new Date(due);
      d.setHours(0, 0, 0, 0);
      const diff = Math.floor((today - d) / (1000 * 60 * 60 * 24));
      if (diff > 0) return diff * 50;
    }
  }
  return 0;
});

const overdueDays = computed(() => {
  if (props.penalties && props.penalties.length > 0)
    return props.penalties.length;
  if (props.aging?.penalty) return Math.ceil(Number(props.aging.penalty) / 50);
  const due =
    props.cashAdvance?.expected_liquidation_date || props.cashAdvance?.dueDate;
  if (!due || !isOverdue.value) return 0;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const d = new Date(due);
  d.setHours(0, 0, 0, 0);
  return Math.max(0, Math.floor((today - d) / (1000 * 60 * 60 * 24)));
});

/**
 * 8-step unified definition (1-8 + optional 9)
 */
const allSteps = computed(() => {
  const steps = [
    {
      idx: 0,
      id: 1,
      key: "request",
      label: "Request",
      subDefault: "Submitted",
      icon: FileText,
      caKeys: ["pending"],
      liqKeys: [],
      statusKeys: ["pending"],
      domain: "cash-advance",
    },
    {
      idx: 1,
      id: 2,
      key: "approval",
      label: "Approval",
      subDefault: "Approved",
      icon: ShieldCheck,
      caKeys: ["approved", "revise", "rejected"],
      liqKeys: [],
      statusKeys: ["approved", "revise", "rejected"],
      domain: "cash-advance",
    },
    {
      idx: 2,
      id: 3,
      key: "disbursed",
      label: "Disbursed",
      subDefault: "Released",
      icon: Wallet,
      caKeys: ["disbursed"],
      liqKeys: [],
      statusKeys: ["disbursed"],
      domain: "cash-advance",
    },
    {
      idx: 3,
      id: 4,
      key: "acknowledged",
      label: "Acknowledged",
      subDefault: "Signed",
      icon: Check,
      caKeys: ["signed"],
      liqKeys: [],
      statusKeys: ["signed", "acknowledged"],
      domain: "cash-advance",
    },
    {
      idx: 4,
      id: 5,
      key: "liq-submitted",
      label: "Liquidation",
      subDefault: "Submitted",
      icon: FileText,
      caKeys: [],
      liqKeys: ["pending"],
      statusKeys: ["pending", "submitted"],
      domain: "liquidation",
    },
    {
      idx: 5,
      id: 6,
      key: "under-review",
      label: "Under Review",
      subDefault: "In Progress",
      icon: Clock,
      caKeys: ["under-review", "under_review"],
      liqKeys: ["under-review", "under_review", "pending-under-review"],
      statusKeys: ["under-review", "under_review"],
      domain: "liquidation",
    },
    {
      idx: 6,
      id: 7,
      key: "decision",
      label: "Decision",
      subDefault: "Approved",
      icon: CircleDot,
      caKeys: [],
      liqKeys: ["approved", "liquidated", "rejected", "revise"],
      statusKeys: ["approved", "liquidated", "rejected", "revise"],
      domain: "liquidation",
    },
    {
      idx: 7,
      id: 8,
      key: "settled",
      label: "Settled",
      subDefault: "Complete",
      icon: Banknote,
      caKeys: ["liquidated", "settled", "incomplete", "overdue"],
      liqKeys: ["settled", "liquidated"],
      statusKeys: ["liquidated", "settled", "incomplete", "overdue"],
      domain: "cash-advance",
    },
  ];

  if (Number(props.overpaymentAmount) > 0 && props.layout === "horizontal") {
    steps.push({
      idx: 8,
      id: 9,
      key: "overpayment",
      label: "Overpayment",
      subDefault: "Forwarded",
      icon: ArrowRight,
      caKeys: [],
      liqKeys: ["overpayment"],
      statusKeys: ["overpayment"],
      domain: "liquidation",
      isOptional: true,
    });
  }
  return steps;
});

const row1Steps = computed(() => allSteps.value.slice(0, 4));
const row2StepsDisplay = computed(() => {
  // Steps 5-8 displayed visually as Step 8 (Settled), Step 7 (Decision), Step 6 (Under Review), Step 5 (Liquidation)
  const steps5to8 = allSteps.value.slice(4, 8);
  return [...steps5to8].reverse();
});

const currentIndex = computed(() => {
  const ca = caStatus.value;
  const liq = liqStatus.value;

  if (ca === "rejected") return 1;
  if (liq === "rejected" || liq === "reject") return 6;
  if (liq === "revise") return 6;
  if (ca === "revise") return 1;
  if (ca === "pending") return 0;
  if (ca === "approved") return 2;
  if (ca === "disbursed") return 3;
  if (ca === "signed" && !props.liquidation) return 4;

  if (props.liquidation) {
    if (liq === "pending") return 4;
    if (
      liq === "under-review" ||
      liq === "under_review" ||
      liq === "pending-under-review"
    )
      return 5;
    if (liq === "approved" || liq === "liquidated") return 7;
  }

  if (
    ["liquidated", "settled"].includes(ca) ||
    ["settled", "liquidated"].includes(liq)
  )
    return 7;
  if (ca === "incomplete") return 7;
  if (ca === "overdue" || isOverdue.value) {
    if (!props.liquidation) return 4;
    if (liq === "under-review" || liq === "pending") return 5;
    return 7;
  }

  if (ca === "under-review" || ca === "under_review") return 5;
  if (ca === "signed") return 4;

  return 0;
});

const isRejectedFlow = computed(
  () =>
    caStatus.value === "rejected" ||
    liqStatus.value === "rejected" ||
    liqStatus.value === "reject",
);

const rejectedAtIndex = computed(() => {
  if (caStatus.value === "rejected") return 1;
  if (liqStatus.value === "rejected" || liqStatus.value === "reject") return 6;
  return -1;
});

const isReviseFlow = computed(
  () => caStatus.value === "revise" || liqStatus.value === "revise",
);

function stepState(stepIdx) {
  const cur = currentIndex.value;
  const rejIdx = rejectedAtIndex.value;

  if (rejIdx !== -1) {
    if (stepIdx < rejIdx) return "completed";
    if (stepIdx === rejIdx) return "rejected";
    return "future";
  }

  if (isReviseFlow.value) {
    if (stepIdx < cur) return "completed";
    if (stepIdx === cur) return "revise";
    return "future";
  }

  if (stepIdx < cur) return "completed";
  if (stepIdx === cur) return "current";

  const isTerminalSuccess =
    ["liquidated", "settled"].includes(caStatus.value) ||
    ["liquidated", "settled", "approved"].includes(liqStatus.value);
  if (isTerminalSuccess && stepIdx === cur) return "completed";

  return "future";
}

function stepSublabel(step, state) {
  if (state === "rejected") return "Rejected";
  if (state === "revise") return "Needs Revision";
  if (state === "completed") {
    if (
      step.key === "decision" &&
      ["approved", "liquidated"].includes(liqStatus.value)
    )
      return "Approved";
    if (step.key === "settled" && isOverdue.value) return "Overdue";
    return step.subDefault;
  }
  if (state === "current") {
    if (step.key === "request") return "Pending";
    if (step.key === "approval") return "In Review";
    if (step.key === "disbursed") return "Processing";
    if (step.key === "acknowledged") return "Awaiting";
    if (step.key === "liq-submitted") return "In Review";
    if (step.key === "under-review") return "In Progress";
    if (step.key === "decision") return "Awaiting";
    if (step.key === "settled") {
      if (isOverdue.value) return "Overdue";
      return "Pending";
    }
    if (step.key === "overpayment") return formatPeso(props.overpaymentAmount);
  }
  if (state === "future") return "Pending";
  return step.subDefault;
}

// Serpentine progress calculations
const row1ProgressPercent = computed(() => {
  if (isRejectedFlow.value && rejectedAtIndex.value !== -1) {
    if (rejectedAtIndex.value < 1) return 0;
    if (rejectedAtIndex.value === 1) return 33.33;
    if (rejectedAtIndex.value === 2) return 66.66;
    return 100;
  }
  const cur = currentIndex.value;
  if (cur <= 0) return 0;
  if (cur === 1) return 33.33;
  if (cur === 2) return 66.66;
  return 100;
});

const isTurnConnected = computed(() => {
  if (isRejectedFlow.value && rejectedAtIndex.value !== -1) {
    return rejectedAtIndex.value >= 4;
  }
  return currentIndex.value >= 4;
});

const row2ProgressPercent = computed(() => {
  if (isRejectedFlow.value && rejectedAtIndex.value !== -1) {
    if (rejectedAtIndex.value < 5) return 0;
    if (rejectedAtIndex.value === 5) return 33.33;
    if (rejectedAtIndex.value === 6) return 66.66;
    return 100;
  }
  const cur = currentIndex.value;
  if (cur <= 4) return 0;
  if (cur === 5) return 33.33;
  if (cur === 6) return 66.66;
  return 100;
});

function handleStepClick(step, state) {
  if (state === "future") return;
  const payload = { index: step.idx, step, state };
  emit("navigate", payload);
  if (step.domain === "cash-advance") emit("view-cash-advance", payload);
  else emit("view-liquidation", payload);
}
</script>

<template>
  <section
    class="relative rounded-xl border border-slate-100 bg-white p-3 sm:p-4"
    aria-label="Unified Cash Advance to Liquidation Roadmap"
  >
    <!-- Mobile vertical background line -->
    <div
      class="pointer-events-none absolute left-7 top-7 bottom-7 w-0.5 bg-slate-100 sm:hidden"
      aria-hidden="true"
    ></div>

    <!-- 1. SERPENTINE / REVERTED 'S' LAYOUT (Default 4x2 Grid) -->
    <div v-if="layout === 'serpentine'" class="relative flex flex-col gap-4 sm:gap-5">
      <!-- Phase 1: Cash Advance (Steps 1 -> 4, Left to Right) -->
      <div class="relative">
        <div class="mb-2 flex items-center justify-between px-1">
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Phase 1: Cash Advance
          </span>
          <span class="hidden sm:inline-flex items-center gap-1 text-[10px] font-medium text-slate-400">
            Flow <ArrowRight class="h-2.5 w-2.5" />
          </span>
        </div>

        <div class="relative flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start">
          <!-- Row 1 Desktop connector line (Left to Right) -->
          <div
            class="pointer-events-none absolute top-4 -translate-y-1/2 hidden h-0.5 bg-slate-100 sm:block"
            style="left: 12.5%; right: 12.5%;"
            aria-hidden="true"
          >
            <div
              class="h-full bg-emerald-200 transition-all duration-300"
              :style="{ width: `${row1ProgressPercent}%` }"
            ></div>
          </div>

          <!-- Steps 1 to 4 -->
          <div
            v-for="step in row1Steps"
            :key="step.key"
            class="relative sm:w-1/4"
          >
            <RoadmapStepItem
              :step="step"
              :state="stepState(step.idx)"
              :sublabel="stepSublabel(step, stepState(step.idx))"
              :history-entry="resolveHistoryDate(step.statusKeys)"
              :revision-count="revisionCountForStep(step.key)"
              :is-overdue="isOverdue"
              :total-penalty="totalPenalty"
              :overdue-days="overdueDays"
              :overpayment-amount="overpaymentAmount"
              @click="handleStepClick"
            />
          </div>
        </div>
      </div>

      <!-- Serpentine Transition Link (Step 4 to Step 5 on the right) -->
      <div class="relative hidden sm:flex items-center justify-end px-[12.5%] -my-3 z-0 pointer-events-none">
        <div class="flex flex-col items-center translate-x-1/2">
          <div
            class="w-0.5 h-3.5 transition-colors duration-300"
            :class="isTurnConnected ? 'bg-emerald-200' : 'bg-slate-100'"
          ></div>
          <div
            class="flex items-center justify-center h-4 w-4 rounded-full border transition-colors duration-300"
            :class="
              isTurnConnected
                ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                : 'border-slate-200 bg-slate-50 text-slate-400'
            "
          >
            <ArrowDown class="h-2.5 w-2.5" />
          </div>
          <div
            class="w-0.5 h-3.5 transition-colors duration-300"
            :class="isTurnConnected ? 'bg-emerald-200' : 'bg-slate-100'"
          ></div>
        </div>
      </div>

      <!-- Phase 2: Liquidation (Steps 5 <- 8, Right to Left) -->
      <div class="relative pt-2 border-t border-slate-100 sm:border-t-0 sm:pt-0">
        <div class="mb-2 flex items-center justify-between px-1">
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Phase 2: Liquidation & Settlement
          </span>
          <span class="hidden sm:inline-flex items-center gap-1 text-[10px] font-medium text-slate-400">
            <ArrowLeft class="h-2.5 w-2.5" /> Flow
          </span>
        </div>

        <div class="relative flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start">
          <!-- Row 2 Desktop connector line (Right to Left) -->
          <div
            class="pointer-events-none absolute top-4 -translate-y-1/2 hidden h-0.5 bg-slate-100 sm:block"
            style="left: 12.5%; right: 12.5%;"
            aria-hidden="true"
          >
            <div
              class="h-full bg-emerald-200 transition-all duration-300 ml-auto"
              :style="{ width: `${row2ProgressPercent}%` }"
            ></div>
          </div>

          <!-- Steps 8 down to 5 (displayed visually left-to-right as 8, 7, 6, 5) -->
          <div
            v-for="step in row2StepsDisplay"
            :key="step.key"
            class="relative sm:w-1/4"
          >
            <RoadmapStepItem
              :step="step"
              :state="stepState(step.idx)"
              :sublabel="stepSublabel(step, stepState(step.idx))"
              :history-entry="resolveHistoryDate(step.statusKeys)"
              :revision-count="revisionCountForStep(step.key)"
              :is-overdue="isOverdue"
              :total-penalty="totalPenalty"
              :overdue-days="overdueDays"
              :overpayment-amount="overpaymentAmount"
              @click="handleStepClick"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- 2. HORIZONTAL 1-ROW LAYOUT (Optional) -->
    <div
      v-else
      class="relative flex flex-col gap-3 sm:flex-row sm:justify-between sm:gap-1 sm:items-start"
    >
      <!-- Connector lines desktop -->
      <div
        v-if="allSteps.length > 1"
        class="pointer-events-none absolute top-4 -translate-y-1/2 hidden h-0.5 bg-slate-100 sm:block"
        :style="{
          left: `${100 / (2 * allSteps.length)}%`,
          right: `${100 / (2 * allSteps.length)}%`,
        }"
        aria-hidden="true"
      >
        <!-- progress overlay -->
        <div
          class="h-full bg-emerald-200 transition-all duration-300"
          :style="{
            width:
              currentIndex === 0
                ? '0%'
                : isRejectedFlow
                  ? `${(rejectedAtIndex / (allSteps.length - 1)) * 100}%`
                  : `${(Math.min(currentIndex, allSteps.length - 1) / (allSteps.length - 1)) * 100}%`,
          }"
        ></div>
      </div>

      <div
        v-for="step in allSteps"
        :key="step.key"
        class="relative flex flex-row items-start gap-3 sm:flex-1 sm:flex-col sm:items-center sm:text-center sm:gap-0"
      >
        <RoadmapStepItem
          :step="step"
          :state="stepState(step.idx)"
          :sublabel="stepSublabel(step, stepState(step.idx))"
          :history-entry="resolveHistoryDate(step.statusKeys)"
          :revision-count="revisionCountForStep(step.key)"
          :is-overdue="isOverdue"
          :total-penalty="totalPenalty"
          :overdue-days="overdueDays"
          :overpayment-amount="overpaymentAmount"
          @click="handleStepClick"
        />
      </div>
    </div>

    <!-- Footer Legend -->
    <div
      class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-50 pt-2 text-[10px] leading-none text-slate-400"
    >
      <span class="inline-flex items-center gap-1">
        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Completed
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span> Current
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="h-2 w-2 rounded-full bg-orange-500"></span> Needs Revision
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="h-2 w-2 rounded-full bg-red-500"></span> Rejected
      </span>
      <span v-if="isOverdue" class="inline-flex items-center gap-1">
        <span class="h-2 w-2 rounded-full bg-orange-500"></span> Overdue
      </span>
    </div>
  </section>
</template>
