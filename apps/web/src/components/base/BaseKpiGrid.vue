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
      <p class="kpi-value">{{ kpi.value }}</p>
      <p class="kpi-label">{{ kpi.label }}</p>
      <p
        v-if="kpi.subtext"
        class="kpi-label mt-1 normal-case tracking-normal text-[11px]"
      >
        {{ kpi.subtext }}
      </p>
    </div>
  </div>
</template>
