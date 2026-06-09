<script setup>
import { AlertTriangle, FileText, Image as ImageIcon, Eye, Pencil, Trash2 } from "lucide-vue-next";
import { formatPeso as formatCurrency, formatDate } from "@/utils/formatters";
import StatusBadge from "@/components/base/StatusBadge.vue";

const props = defineProps({
  expense: {
    type: Object,
    required: true,
  },
  isSelected: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["select", "view", "delete", "edit"]);
</script>

<template>
  <div
    class="bg-white rounded-xl overflow-hidden flex flex-col group transition-all hover:shadow-xl relative cursor-pointer"
    :class="
      isSelected
        ? 'border-2 border-primary shadow-md'
        : 'border border-slate-100 shadow-md'
    "
    @click="$emit('select', expense.id)"
  >
    <!-- Selected Badge -->
    <Transition name="pop">
      <div
        v-if="isSelected"
        class="absolute top-3 right-3 z-10 w-7 h-7 bg-primary rounded-full flex items-center justify-center shadow-md"
      >
        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
    </Transition>

    <!-- Expense Image Preview -->
    <div
      class="aspect-square w-full bg-slate-50 overflow-hidden flex-shrink-0 border-b border-slate-100"
    >
      <img
        v-if="expense.thumbnail"
        :src="expense.thumbnail"
        :alt="expense.fileName"
        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 opacity-100"
      />
      <div
        v-else
        class="w-full h-full flex flex-col items-center justify-center gap-2"
      >
        <div
          class="w-12 h-12 rounded-2xl bg-primary/5 flex items-center justify-center"
        >
          <FileText
            v-if="
              expense.fileType === 'application/pdf' ||
              expense.fileType === 'pdf'
            "
            class="w-6 h-6 text-primary/40"
          />
          <ImageIcon v-else class="w-6 h-6 text-primary/40" />
        </div>
        <p
          class="text-[10px] text-slate-300 font-semibold uppercase tracking-widest"
          style="font-family: &quot;Poppins&quot;, sans-serif"
        >
          No Preview
        </p>
      </div>
    </div>

    <!-- Card Body -->
    <div class="p-4 flex flex-col flex-1">
      <div
        v-if="expense.status === 'automatic-rejected'"
        class="mb-3 rounded-lg border border-danger/20 bg-danger/5 px-3 py-2"
      >
        <div class="flex items-start gap-2">
          <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-danger" />
          <div>
            <p class="text-[11px] font-bold text-danger">Automatic Rejected</p>
            <p class="mt-0.5 text-[10px] leading-snug text-slate-500">
              Update the details or upload a clearer copy.
            </p>
          </div>
        </div>
      </div>

      <!-- File / Merchant Info -->
      <div class="mb-3">
        <h3
          class="font-bold text-slate-800 text-[13px] leading-snug truncate"
          :style="{ fontFamily: '\'Poppins\', sans-serif' }"
        >
          {{ expense.vendorName || "Unknown Vendor" }}
        </h3>
        <p class="text-slate-400 text-[11px] mt-0.5">
          {{ formatDate(expense.date) }}
        </p>
      </div>

      <!-- Category + Amount -->
      <div class="mt-auto flex items-center justify-between mb-3">
        <span
          class="px-2.5 py-1 bg-primary/5 text-primary-600 rounded-md text-[11px] font-semibold border border-primary/10 truncate max-w-[55%]"
          style="font-family: &quot;Poppins&quot;, sans-serif"
        >
          {{ expense.category }}
        </span>
        <span class="font-bold text-[14px] text-success font-mono">
          {{ expense.amount > 0 ? formatCurrency(expense.amount) : "—" }}
        </span>
      </div>

      <div class="mb-3">
        <StatusBadge :status="expense.status" />
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-2">
        <button
          v-if="expense.status === 'automatic-rejected'"
          class="btn btn-secondary flex-1 !py-2 !text-xs"
          @click.stop="$emit('edit', expense)"
        >
          <Pencil class="w-3.5 h-3.5" /> Edit Receipt
        </button>
        <button
          class="btn btn-primary flex-1 !py-2 !text-xs"
          @click.stop="$emit('view', expense)"
        >
          <Eye class="w-3.5 h-3.5" /> View
        </button>
        <button
          class="px-3 py-2 rounded-lg border transition-all flex items-center justify-center"
          :class="
            isSelected
              ? 'border-danger/30 text-danger hover:bg-red-50'
              : 'border-slate-200 text-slate-400 hover:border-danger/30 hover:text-danger hover:bg-red-50'
          "
          @click.stop="$emit('delete', expense.id)"
          title="Delete"
        >
          <Trash2 class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>
