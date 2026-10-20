<script setup>
import { ArrowRight } from "lucide-vue-next";
import StatusBadge from "@/components/base/StatusBadge.vue";
import { formatPeso, formatDate } from "@/utils/formatters";

defineProps({
  advances: {
    type: Array,
    default: () => [],
  },
  getBadgeStatus: {
    type: Function,
    default: (advance) =>
      String(advance?.status || "").toLowerCase().replace(/\s+/g, "-"),
  },
});

defineEmits(["select"]);
</script>

<template>
  <div class="p-5">
    <div v-if="advances.length" class="grid gap-3 sm:grid-cols-2">
      <button
        v-for="advance in advances"
        :key="advance.id"
        type="button"
        data-testid="liquidation-advance-card"
        :data-advance-id="advance.id"
        class="group flex w-full flex-col rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        @click="$emit('select', advance)"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p
              class="truncate text-sm font-semibold text-slate-800"
              :title="advance.purpose"
            >
              {{ advance.purpose || `Cash Advance #${advance.id}` }}
            </p>
            <p class="mt-1 text-[11px] font-medium text-slate-400">
              Ref #{{ advance.id }}
            </p>
          </div>
          <StatusBadge :status="getBadgeStatus(advance)" />
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3">
          <div>
            <p
              class="text-[10px] font-bold uppercase tracking-wider text-slate-400"
            >
              Outstanding
            </p>
            <p class="mt-0.5 text-base font-bold text-primary">
              {{
                formatPeso(
                  advance.balance ??
                    advance.outstanding_balance ??
                    advance.amount ??
                    0,
                )
              }}
            </p>
          </div>
          <div class="text-right">
            <p
              class="text-[10px] font-bold uppercase tracking-wider text-slate-400"
            >
              Due date
            </p>
            <p class="mt-0.5 text-xs font-semibold text-slate-600">
              {{
                formatDate(
                  advance.dueDate ||
                    advance.expected_liquidation_date ||
                    advance.expectedLiquidationDate,
                )
              }}
            </p>
          </div>
        </div>

        <div
          class="mt-3 flex items-center justify-end gap-1 text-xs font-semibold text-primary"
        >
          Liquidate this advance
          <ArrowRight
            class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
          />
        </div>
      </button>
    </div>

    <div
      v-else
      class="flex min-h-32 items-center justify-center rounded-xl border border-dashed border-slate-200 p-6 text-center"
    >
      <p class="text-sm text-slate-400">
        No cash advances are ready for liquidation.
      </p>
    </div>
  </div>
</template>
