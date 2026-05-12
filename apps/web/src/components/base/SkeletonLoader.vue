<script setup>
defineProps({
  rows: { type: Number, default: 3 },
  height: { type: String, default: 'h-6' },
  width: { type: String, default: 'w-full' }
})
</script>

<template>
  <div class="flex flex-col gap-3">
    <div 
      v-for="i in rows" 
      :key="i"
      :class="[height, width, 'bg-slate-200 animate-pulse']"
    ></div>
  </div>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .5; }
}
.animate-pulse {
  animation: pulse 1s linear infinite;
}
</style>
