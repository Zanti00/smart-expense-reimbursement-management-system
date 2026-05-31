<script setup>
defineProps({
  kpi: {
    type: Object,
    required: false,
    default: () => ({})
  }
});
</script>

<template>
  <div class="kpi-card group relative">
    <!-- Colored accent top strip -->
    <div
      v-if="kpi.accent"
      :class="[
        'absolute top-0 left-0 right-0 h-0.5 rounded-t-xl bg-gradient-to-r',
        kpi.accent,
      ]"
    ></div>
    <div v-else class="absolute top-0 left-0 right-0 h-0.5 rounded-t-xl bg-slate-200 animate-pulse"></div>

    <div class="flex items-center justify-between mb-4">
      <span
        v-if="kpi.sub"
        class="text-xs text-slate-400"
        style="font-family: 'Open Sans', sans-serif"
      >
        {{ kpi.sub }}
      </span>
      <div v-else class="h-3 w-20 animate-pulse rounded bg-slate-200"></div>
    </div>
    
    <!-- Value skeleton (always pulses since it's a skeleton component) -->
    <div class="mb-1 h-8 w-24 animate-pulse rounded bg-slate-200"></div>
    
    <!-- Label -->
    <p v-if="kpi.label" class="kpi-label">{{ kpi.label }}</p>
    <div v-else class="h-4 w-32 animate-pulse rounded bg-slate-200 mt-2"></div>
    
    <!-- Lower subtext -->
    <template v-if="kpi.subtext !== undefined">
      <div
        v-if="kpi.subtext"
        class="kpi-label mt-1 normal-case tracking-normal text-[11px]"
      >
        <span>{{ kpi.subtext }}</span>
      </div>
    </template>
    <div v-else-if="!kpi.label" class="mt-2 h-3 w-40 animate-pulse rounded bg-slate-200"></div>
  </div>
</template>
