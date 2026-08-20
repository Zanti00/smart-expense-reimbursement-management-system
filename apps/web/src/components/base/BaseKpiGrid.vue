<script setup>
import BaseKpiCardSkeleton from "./BaseKpiCardSkeleton.vue";
import { formatKpiValue } from "@/utils/formatters";

defineProps({
  kpis: {
    type: Array,
    required: true,
  },
  gridClasses: {
    type: String,
    default: "grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4",
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  skeletonCount: {
    type: Number,
    default: 3,
  },
});
</script>

<template>
  <div class="grid" :class="gridClasses">
    <template v-if="isLoading">
      <template v-if="kpis && kpis.length > 0">
        <BaseKpiCardSkeleton v-for="(kpi, i) in kpis" :key="`skeleton-${kpi.label || i}`" :kpi="kpi" />
      </template>
      <template v-else>
        <BaseKpiCardSkeleton v-for="i in skeletonCount" :key="`skeleton-empty-${i}`" />
      </template>
    </template>
    <template v-else>
      <div v-for="kpi in kpis" :key="kpi.label" class="kpi-card group">
        <!-- Colored accent top strip -->
        <div
          :class="[
            'absolute top-0 left-0 right-0 h-0.5 rounded-t-xl',
            kpi.accent,
          ]"
        ></div>

        <div class="flex items-center justify-between mb-4">
          <span class="text-xs text-slate-400">
            {{ kpi.sub }}
          </span>
        </div>
        <p class="kpi-value truncate" :title="formatKpiValue(kpi.value)">{{ formatKpiValue(kpi.value) }}</p>
        <p class="kpi-label">{{ kpi.label }}</p>
        <div
          v-if="kpi.subtext"
          class="kpi-label mt-1 normal-case tracking-normal text-[11px]"
        >
          <span>{{ kpi.subtext }}</span>
        </div>
      </div>
    </template>
  </div>
</template>
