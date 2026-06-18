<script setup>
import { Sparkles, UploadCloud, AlertTriangle } from "lucide-vue-next";

defineProps({
  receiptDrag: Boolean,
});

defineEmits(["drop", "dragover", "dragleave", "click"]);
</script>

<template>
  <section class="p-6 card">
    <div
      class="flex flex-col gap-3 mb-5 sm:flex-row sm:items-center sm:justify-between"
    >
      <h2 class="text-lg font-bold font-heading text-primary">
        Upload Receipts
      </h2>
      <div class="flex items-center gap-2 text-xs font-bold text-accent">
        <Sparkles class="w-4 h-4" />
        <span>Upload your receipt - AI reads everything automatically</span>
      </div>
    </div>
    <div
      class="flex min-h-[320px] flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-colors cursor-pointer"
      :class="
        receiptDrag
          ? 'border-accent bg-accent/5'
          : 'border-slate-200 bg-slate-50/50 hover:border-accent/50'
      "
      @dragover.prevent="$emit('dragover')"
      @dragleave.prevent="$emit('dragleave')"
      @drop.prevent="$emit('drop', $event)"
      @click="$emit('click')"
    >
      <span
        class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-2xl bg-accent/10 text-accent"
      >
        <UploadCloud class="w-8 h-8" />
      </span>
      <h3 class="text-base font-bold font-heading text-slate-800">
        Drag and drop receipt images here, or click to browse
      </h3>
      <p class="mt-1 text-sm text-slate-400">
        Supports: JPG, PNG, PDF (Max 2MB per file)
      </p>
      <p class="flex items-center gap-2 mt-4 text-sm font-bold text-danger">
        <AlertTriangle class="w-4 h-4" />
        At least 1 receipt is required to proceed
      </p>
      <button
        class="inline-flex items-center justify-center gap-2 px-6 py-3 mt-6 text-sm font-bold text-white transition-colors rounded-lg shadow-sm bg-accent hover:bg-accent-600"
        type="button"
        @click.stop="$emit('click')"
      >
        <UploadCloud class="w-4 h-4" />
        Select Files
      </button>
    </div>
  </section>
</template>
