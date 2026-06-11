<script setup>
import { formatPeso } from "@/utils/formatters";

defineProps({
  receiptCount: {
    type: Number,
    required: true,
  },
  reportFile: {
    type: File,
    default: null,
  },
  cutoffPeriod: {
    type: String,
    required: true,
  },
  totalAmount: {
    type: Number,
    required: true,
  },
});
</script>

<template>
  <section
    class="bg-accent-50 border border-accent/15 rounded-xl p-6 space-y-4"
  >
    <h3
      class="text-base font-bold text-primary"
      style="font-family: 'Poppins', sans-serif"
    >
      Summary
    </h3>
    <div class="space-y-3">
      <div class="flex justify-between items-center text-sm">
        <span class="text-slate-500">Uploaded Receipts</span>
        <span class="font-bold text-accent">
          {{ receiptCount }} - ready
        </span>
      </div>
      <div class="flex justify-between items-center text-sm">
        <span class="text-slate-500">Report Attached</span>
        <span class="text-slate-400">{{
          reportFile ? reportFile.name : "None"
        }}</span>
      </div>
      <div
        class="flex justify-between items-center text-sm pb-4 border-b border-accent/20"
      >
        <span class="text-slate-500">Cutoff Period</span>
        <span
          :class="
            cutoffPeriod
              ? 'text-slate-700 font-semibold'
              : 'text-danger font-bold'
          "
        >
          {{ cutoffPeriod || "Not selected" }}
        </span>
      </div>
      <div class="flex justify-between items-center pt-2">
        <span
          class="text-base font-bold text-primary"
          style="font-family: 'Poppins', sans-serif"
          >Total Amount</span
        >
        <span class="text-2xl font-black text-accent font-mono">{{
          formatPeso(totalAmount)
        }}</span>
      </div>
    </div>
  </section>
</template>
