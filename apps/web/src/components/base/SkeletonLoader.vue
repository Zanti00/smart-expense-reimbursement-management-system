<script setup>
defineProps({
  rows: { type: Number, default: 3 },
  height: { type: String, default: 'h-6' },
  width: { type: String, default: 'w-full' },
  variant: {
    type: String,
    default: 'lines',
    validator: (value) => ['lines', 'table', 'chart', 'card', 'list'].includes(value),
  },
  columns: { type: Number, default: 5 },
})
</script>

<template>
  <div v-if="variant === 'table'" class="overflow-hidden rounded-lg border border-slate-100 bg-white">
    <div class="grid border-b border-slate-100 bg-slate-50" :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }">
      <div v-for="column in columns" :key="`head-${column}`" class="px-3 py-3">
        <div class="h-2.5 max-w-full rounded bg-slate-200 pulse" :class="column === 1 ? 'w-12 sm:w-16' : 'w-16 sm:w-20'"></div>
      </div>
    </div>
    <div v-for="row in rows" :key="`row-${row}`" class="grid border-b border-slate-100 last:border-b-0" :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }">
      <div v-for="column in columns" :key="`cell-${row}-${column}`" class="px-3 py-3.5">
        <div
          class="h-3 max-w-full rounded bg-slate-200 pulse"
          :class="[
            column === columns ? 'mx-auto h-8 w-16 rounded-lg border border-slate-200 bg-slate-100 sm:w-20' : '',
            column === columns - 1 ? 'mx-auto h-5 w-14 rounded-full sm:w-16' : '',
            column === 1 ? 'w-10 sm:w-14' : column === 2 ? 'w-20 sm:w-28' : 'w-16 sm:w-20',
          ]"
        ></div>
      </div>
    </div>
  </div>

  <div v-else-if="variant === 'chart'" class="flex h-full min-h-40 flex-col justify-end gap-3 rounded-lg bg-slate-50 p-4">
    <div class="mb-auto flex items-start justify-between">
      <div class="space-y-2">
        <div class="h-3 w-32 rounded bg-slate-200 pulse"></div>
        <div class="h-2.5 w-20 rounded bg-slate-200 pulse"></div>
      </div>
      <div class="h-7 w-16 rounded-full bg-slate-200 pulse"></div>
    </div>
    <div class="flex h-32 items-end gap-3">
      <div v-for="bar in 6" :key="`bar-${bar}`" class="flex-1 rounded-t bg-slate-200 pulse" :style="{ height: `${32 + ((bar * 17) % 56)}%` }"></div>
    </div>
    <div class="grid grid-cols-6 gap-3">
      <div v-for="tick in 6" :key="`tick-${tick}`" class="h-2 rounded bg-slate-200 pulse"></div>
    </div>
  </div>

  <div v-else-if="variant === 'card'" class="rounded-lg border border-slate-100 bg-white p-4">
    <div class="mb-4 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-md bg-slate-200 pulse"></div>
        <div class="space-y-2">
          <div class="h-3 w-28 rounded bg-slate-200 pulse"></div>
          <div class="h-2.5 w-20 rounded bg-slate-200 pulse"></div>
        </div>
      </div>
      <div class="h-6 w-16 rounded-full bg-slate-200 pulse"></div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div v-for="item in 4" :key="`field-${item}`" class="rounded-md border border-slate-100 bg-slate-50 p-3">
        <div class="mb-2 h-2.5 w-16 rounded bg-slate-200 pulse"></div>
        <div class="h-3.5 w-24 rounded bg-slate-200 pulse"></div>
      </div>
    </div>
  </div>

  <div v-else-if="variant === 'list'" class="space-y-3">
    <div v-for="row in rows" :key="`list-${row}`" class="flex items-center gap-3 rounded-lg border border-slate-100 bg-white p-3">
      <div class="h-9 w-9 shrink-0 rounded-md bg-slate-200 pulse"></div>
      <div class="min-w-0 flex-1 space-y-2">
        <div class="h-3 w-3/5 rounded bg-slate-200 pulse"></div>
        <div class="h-2.5 w-4/5 rounded bg-slate-200 pulse"></div>
      </div>
      <div class="h-6 w-16 rounded-full bg-slate-200 pulse"></div>
    </div>
  </div>

  <div v-else class="flex flex-col gap-3">
    <div 
      v-for="i in rows" 
      :key="i"
      :class="[height, width, 'rounded bg-slate-200 pulse']"
    ></div>
  </div>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .5; }
}
.pulse {
  animation: pulse 1s linear infinite;
}
</style>
