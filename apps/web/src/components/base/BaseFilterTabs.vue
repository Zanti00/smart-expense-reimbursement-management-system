<script setup>
defineProps({
  tabs: {
    type: Array,
    required: true,
  },
  modelValue: {
    type: String,
    required: true,
  },
});

defineEmits(["update:modelValue"]);
</script>

<template>
  <div
    class="flex w-fit items-center gap-1 rounded-xl border border-black/5 bg-white/70 p-1 shadow-sm overflow-x-auto max-w-full"
  >
    <button
      v-for="tab in tabs"
      :key="tab"
      class="rounded-lg px-5 py-2 text-sm font-semibold transition-all duration-200 ease-out whitespace-nowrap"
      :class="
        modelValue === tab
          ? 'bg-primary text-white shadow-sm'
          : 'text-slate-700 hover:bg-accent-50 hover:text-accent-700'
      "
      @click="$emit('update:modelValue', tab)"
    >
      {{ tab }}
    </button>
  </div>
</template>
