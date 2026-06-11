<script setup>
import { Sparkles, UploadCloud, AlertTriangle } from "lucide-vue-next";

defineProps({
  receiptDrag: Boolean,
});

defineEmits(["drop", "dragover", "dragleave", "click"]);
</script>

<template>
  <section class="card p-6">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="font-heading text-lg font-bold text-primary">
        Upload Receipts
      </h2>
      <div class="flex items-center gap-2 text-xs font-bold text-accent">
        <Sparkles class="h-4 w-4" />
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
        class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-accent/10 text-accent"
      >
        <UploadCloud class="h-8 w-8" />
      </span>
      <h3 class="font-heading text-base font-bold text-slate-800">
        Drag and drop receipt images here, or click to browse
      </h3>
      <p class="mt-1 text-sm text-slate-400">
        Supports: JPG, PNG, PDF (Max 10MB per file)
      </p>
      <p class="mt-4 flex items-center gap-2 text-sm font-bold text-danger">
        <AlertTriangle class="h-4 w-4" />
        At least 1 receipt is required to proceed
      </p>
      <button
        class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-accent px-6 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-accent-600"
        type="button"
        @click.stop="$emit('click')"
      >
        <UploadCloud class="h-4 w-4" />
        Select Files
      </button>
    </div>
  </section>
</template>
