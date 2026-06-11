<script setup>
import { CheckCircle2, PlusCircle } from "lucide-vue-next";

defineProps({
  receiptCount: {
    type: Number,
    required: true,
  },
});

defineEmits(["add-receipts"]);
</script>

<template>
  <section class="card p-6">
    <div
      class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <div class="flex items-center gap-3">
        <h2
          class="text-lg font-bold text-primary"
          style="font-family: 'Poppins', sans-serif"
        >
          Upload Receipts
        </h2>
        <span
          class="badge text-[11px] font-bold"
          :class="
            receiptCount >= 1
              ? 'bg-accent-50 border-accent/15 text-accent'
              : 'bg-red-50 border-red-100 text-danger'
          "
        >
          {{ receiptCount }} receipt{{ receiptCount !== 1 ? "s" : "" }}
        </span>
      </div>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div
          v-if="receiptCount >= 1"
          class="flex items-center gap-2 text-accent text-sm font-semibold"
        >
          <CheckCircle2 class="w-4 h-4" /> Ready to submit
        </div>
        <button
          class="inline-flex h-9 w-fit shrink-0 items-center justify-center gap-2 rounded-lg bg-accent px-3.5 text-xs font-bold text-white transition-colors hover:bg-accent-600"
          type="button"
          @click="$emit('add-receipts')"
        >
          <PlusCircle class="h-3.5 w-3.5" /> Add More Receipts
        </button>
      </div>
    </div>
  </section>
</template>
