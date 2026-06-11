<script setup>
import { FileText } from 'lucide-vue-next'

defineProps({
  receipt: {
    type: Object,
    required: true
  },
  confidence: {
    type: Number,
    default: 100
  }
})

defineEmits(['remove'])
</script>

<template>
  <div class="lg:w-[40%] flex flex-col gap-2 w-full">
    <div class="card p-2 bg-slate-100 flex flex-col h-[500px] border-slate-200">
      <div class="flex items-center justify-between px-2 pt-1 pb-2">
        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Source Document</span>
        
        <span v-if="receipt.ocrStatus === 'processing'" class="badge">
          <span class="w-1.5 h-1.5 rounded-full block bg-primary animate-pulse"></span> SCANNING
        </span>
        <span v-else-if="receipt.ocrStatus === 'done' && confidence < 80" class="badge">
          <span class="w-1.5 h-1.5 rounded-full block bg-amber-600"></span> NEEDS REVIEW
        </span>
        <span v-else-if="receipt.ocrStatus === 'done'" class="badge">
          <span class="w-1.5 h-1.5 rounded-full block bg-emerald-600"></span> CAPTURED
        </span>
      </div>

      <div class="relative flex-1 bg-white border border-slate-200 flex items-center justify-center overflow-hidden">
        <img v-if="receipt.preview" :src="receipt.preview" class="w-full h-full object-contain" />
        <FileText v-else class="w-8 h-8 text-slate-300" />
        
        <!-- Scanning Overlay Line -->
        <div v-if="receipt.ocrStatus === 'processing'" class="absolute inset-x-0 w-full z-10 pointer-events-none animate-scan-slow">
          <div class="h-1 bg-[#252578]/40 shadow-[0_0_8px_#252578]"></div>
          <div class="h-24 bg-[#252578]/10"></div>
        </div>
      </div>
    </div>
    <button class="text-[10px] font-bold text-slate-400 hover:text-danger uppercase text-center py-2" @click="$emit('remove')">
      [ REMOVE FILE ]
    </button>
  </div>
</template>

<style scoped>
@keyframes scan-slow {
  0% { transform: translateY(-100%); opacity: 0; }
  10% { opacity: 1; }
  90% { opacity: 1; }
  100% { transform: translateY(500px); opacity: 0; }
}

.animate-scan-slow {
  animation: scan-slow 1.5s linear infinite;
}
</style>
