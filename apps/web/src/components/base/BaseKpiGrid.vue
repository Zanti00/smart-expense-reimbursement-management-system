<script setup>
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
});
</script>

<template>
  <div class="grid" :class="gridClasses">
    <div v-for="kpi in kpis" :key="kpi.label" class="kpi-card group">
      <!-- Colored accent top strip (overrides the default ::before with per-card gradient) -->
      <div
        :class="[
          'absolute top-0 left-0 right-0 h-0.5 rounded-t-xl bg-gradient-to-r',
          kpi.accent,
        ]"
      />

      <div class="flex items-center justify-between mb-4">
        <span
          class="text-xs text-slate-400"
          style="font-family: 'Open Sans', sans-serif"
        >
          {{ kpi.sub }}
        </span>
        <div
          v-if="kpi.icon"
          :class="[
            'w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0',
            kpi.iconBg,
          ]"
        >
          <component :is="kpi.icon" :class="['w-4 h-4', kpi.iconColor]" />
        </div>
      </div>
      <template v-if="isLoading">
        <div class="mb-1 h-8 w-24 animate-pulse rounded bg-slate-200"></div>
      </template>
      <template v-else>
        <p class="kpi-value">{{ kpi.value }}</p>
      </template>
      <p class="kpi-label">{{ kpi.label }}</p>
      <div
        v-if="kpi.subtext"
        class="kpi-label mt-1 normal-case tracking-normal text-[11px]"
      >
        <div v-if="isLoading" class="mt-1 h-3 w-40 animate-pulse rounded bg-slate-200"></div>
        <span v-else>{{ kpi.subtext }}</span>
      </div>
    </div>
  </div>
</template>
