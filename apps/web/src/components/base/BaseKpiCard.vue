<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  theme: {
    type: String,
    default: "primary",
  },
});
</script>

<template>
  <div class="kpi-card border-l-2" :class="`border-l-${theme}`">
    <div class="mb-4 flex items-center justify-between">
      <span class="kpi-label">{{ label }}</span>
      <slot name="icon"></slot>
    </div>
    <p class="kpi-value">
      {{ value }}
    </p>
    <slot name="subtext"></slot>
  </div>
</template>
