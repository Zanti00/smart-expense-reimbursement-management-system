<script setup>
import { formatPeso } from "@/utils/formatters";
import {
  ArrowLeft,
  CalendarDays,
  X,
  Sparkles,
  CheckCircle,
  Download,
  MapPin,
  FileText,
  XCircle,
} from "lucide-vue-next";

defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  receipt: {
    type: Object,
    default: null,
  },
  pendingDecision: {
    type: String,
    default: "",
  },
  isReviewingOwnLiquidation: {
    type: Boolean,
    default: false,
  },
  getFileUrl: {
    type: Function,
    required: true,
  },
});

defineEmits([
  "close",
  "close-review",
  "request-decision",
  "cancel-decision",
  "confirm-decision",
]);
</script>

<template>
  <div
    v-if="isOpen && receipt"
    class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/40 p-2 backdrop-blur-[1px] sm:p-4"
  >
    <div
      class="flex max-h-[calc(100dvh-1rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl sm:max-h-[92vh]"
    >
      <header
        class="flex items-center justify-between px-5 py-4 text-white border-b border-primary/10 bg-primary"
      >
        <div class="flex items-center min-w-0 gap-4">
          <button
            class="inline-flex items-center gap-2 px-2 py-1 text-xs font-bold transition-colors rounded-md text-white/90 hover:bg-white/10"
            type="button"
            @click="$emit('close')"
          >
            <ArrowLeft class="w-4 h-4" />
            Back
          </button>
          <div class="w-px h-6 bg-white/20" />
          <div class="flex items-center min-w-0 gap-2">
            <span
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg shrink-0 bg-white/10"
            >
              <CalendarDays class="w-4 h-4" />
            </span>
            <div class="min-w-0">
              <h3 class="text-lg font-bold text-white truncate font-heading">
                Receipt Details
              </h3>
              <p class="text-xs font-semibold truncate text-white/65">
                AI-scanned liquidation receipt extraction
              </p>
            </div>
          </div>
        </div>
        <button
          class="inline-flex items-center justify-center transition-colors rounded-full h-9 w-9 text-white/85 hover:bg-white/10 hover:text-white"
          type="button"
          title="Close receipt details"
          @click="$emit('close-review')"
        >
          <X class="w-5 h-5" />
        </button>
      </header>

      <div
        class="flex-1 overflow-y-auto bg-slate-50 p-3 scrollbar-thin sm:p-5"
      >
        <div
          class="flex flex-col gap-3 px-4 py-3 mb-4 border rounded-lg border-accent/20 bg-accent-50 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="flex items-center gap-3">
            <span
              class="inline-flex items-center justify-center bg-white rounded-lg shadow-sm h-9 w-9 shrink-0 text-accent"
            >
              <Sparkles class="w-4 h-4" />
            </span>
            <div>
              <p
                class="text-xs font-bold uppercase tracking-[0.12em] text-accent"
              >
                AI Scanned
              </p>
              <p class="text-sm font-semibold text-primary">
                Details automatically extracted from the submitted liquidation
                receipt.
              </p>
            </div>
          </div>
          <span
            class="inline-flex w-fit items-center gap-1 rounded-full bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-accent shadow-sm"
          >
            <CheckCircle class="h-3.5 w-3.5" />
            Verified fields
          </span>
        </div>

        <div
          class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
        >
          <div class="grid grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside
              class="p-4 border-b border-slate-200 bg-slate-100/70 sm:p-5 xl:border-b-0 xl:border-r"
            >
              <div class="flex items-center justify-between gap-3 mb-4">
                <div class="min-w-0">
                  <p class="kpi-label text-slate-400">Receipt Preview</p>
                  <h4
                    class="mt-1 text-base font-bold truncate font-heading text-slate-900"
                  >
                    {{ receipt.merchantName }}
                  </h4>
                </div>
                <span
                  class="inline-flex rounded-md bg-accent-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-accent"
                >
                  {{ receipt.category }}
                </span>
              </div>
              <div
                class="overflow-hidden bg-white border rounded-lg shadow-sm border-slate-200"
              >
                <img
                  :src="getFileUrl(receipt.filePath)"
                  alt="Scanned receipt"
                  class="h-full max-h-[520px] w-full object-cover object-top"
                />
              </div>
              <a
                :href="getFileUrl(receipt.filePath)"
                target="_blank"
                class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-accent/20 bg-white px-3 py-2.5 text-xs font-bold text-accent transition-colors hover:bg-accent-50"
              >
                <Download class="w-4 h-4" />
                Download Receipt
              </a>
            </aside>

            <section class="p-5 space-y-5">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="space-y-1">
                  <span class="input-label">Invoice Number</span>
                  <input
                    class="input"
                    readonly
                    :value="receipt.invoiceNumber"
                  />
                </label>
                <label class="space-y-1">
                  <span class="input-label">Transaction Date</span>
                  <span class="relative block">
                    <input
                      class="pr-10 input"
                      readonly
                      :value="receipt.transactionDate"
                    />
                  </span>
                </label>
                <label class="space-y-1">
                  <span class="flex items-center justify-between gap-2">
                    <span class="input-label">TIN Number</span>
                    <span
                      class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent"
                    >
                      <Sparkles class="w-3 h-3" />
                      AI Read
                    </span>
                  </span>
                  <input
                    class="input"
                    readonly
                    :value="receipt.tinNumber"
                  />
                </label>
                <label class="space-y-1">
                  <span class="input-label">Merchant Name</span>
                  <input
                    class="input"
                    readonly
                    :value="receipt.merchantName"
                  />
                </label>
                <label class="space-y-1 md:col-span-2">
                  <span class="input-label">Location</span>
                  <span class="relative block">
                    <input
                      class="input pl-9"
                      readonly
                      :value="receipt.location"
                    />
                    <MapPin
                      class="absolute w-4 h-4 -translate-y-1/2 pointer-events-none left-3 top-1/2 text-accent"
                    />
                  </span>
                </label>
                <label class="space-y-1 md:col-span-2">
                  <span class="flex items-center justify-between gap-2">
                    <span class="input-label"
                      >Category (AI Auto-Detected)</span
                    >
                    <span
                      class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent"
                    >
                      <Sparkles class="w-3 h-3" />
                      AI Detected
                    </span>
                  </span>
                  <select class="input" disabled>
                    <option selected>{{ receipt.category }}</option>
                  </select>
                </label>
              </div>

              <div
                class="overflow-x-auto overflow-y-hidden bg-white border rounded-lg border-slate-200"
              >
                <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                  <h4
                    class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500"
                  >
                    Order Items
                  </h4>
                </div>
                <table class="w-full text-sm text-left border-collapse">
                  <thead>
                    <tr
                      class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400"
                    >
                      <th class="px-4 py-3">Items</th>
                      <th class="px-4 py-3 text-center">Qty</th>
                      <th class="px-4 py-3 text-right">Price</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr
                      v-for="item in receipt.items"
                      :key="item.name"
                    >
                      <td class="px-4 py-3 font-semibold text-slate-700">
                        {{ item.name }}
                      </td>
                      <td class="px-4 py-3 text-center text-slate-500">
                        {{ item.quantity }}
                      </td>
                      <td
                        class="px-4 py-3 font-semibold text-right text-slate-700"
                      >
                        {{ formatPeso(item.price) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div
                class="grid grid-cols-1 gap-3 pt-4 border-t border-slate-200 md:grid-cols-2 xl:grid-cols-3"
              >
                <label class="space-y-1">
                  <span class="input-label">Subtotal</span>
                  <input
                    class="font-semibold input"
                    readonly
                    :value="formatPeso(receipt.subtotal)"
                  />
                </label>
                <label class="space-y-1">
                  <span class="input-label">Tax (VAT)</span>
                  <input
                    class="font-semibold input"
                    readonly
                    :value="formatPeso(receipt.vat)"
                  />
                </label>
                <div
                  class="p-3 border rounded-lg border-accent/20 bg-accent-50 md:col-span-2 xl:col-span-1"
                >
                  <p class="input-label text-accent">Orders Total</p>
                  <p class="mt-1 text-xl font-bold font-heading text-primary">
                    {{ formatPeso(receipt.amount || 0) }}
                  </p>
                </div>
              </div>

              <footer
                class="flex items-center gap-2 text-xs font-semibold text-slate-400"
              >
                <FileText class="w-4 h-4" />
                Uploaded with receipt {{ receipt.id }}
              </footer>

              <div
                class="p-4 border rounded-xl border-slate-200 bg-slate-50/70"
              >
                <label class="space-y-2">
                  <span class="input-label"
                    >Admin Notes for this Receipt</span
                  >
                  <textarea
                    v-model="receipt.notes"
                    class="bg-white resize-none input min-h-24"
                    placeholder="Leave comments or auditor feedback for this receipt..."
                  />
                </label>
              </div>
            </section>
          </div>
        </div>
      </div>

      <div class="px-5 py-4 bg-white border-t border-slate-200">
        <div
          v-if="pendingDecision"
          class="flex flex-col gap-3 p-4 border rounded-lg border-accent/20 bg-accent-50 sm:flex-row sm:items-center sm:justify-between"
        >
          <p class="text-sm font-semibold text-primary">
            Are you sure you want to {{ pendingDecision }} this
            receipt?
          </p>
          <div class="flex items-center gap-2 shrink-0">
            <button
              class="inline-flex items-center justify-center px-4 text-xs font-bold transition-colors bg-white border rounded-lg min-h-9 border-slate-200 text-slate-600 hover:bg-slate-50"
              type="button"
              @click="$emit('cancel-decision')"
            >
              Cancel
            </button>
            <button
              class="btn btn-cta min-h-[42px]"
              type="button"
              @click="$emit('confirm-decision')"
            >
              Confirm
            </button>
          </div>
        </div>
        <div v-else class="flex flex-col gap-2 sm:flex-row sm:justify-end">
          <button
            class="inline-flex items-center justify-center gap-2 px-4 text-sm font-bold text-red-700 transition-colors border border-red-200 rounded-lg min-h-10 bg-red-50 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="isReviewingOwnLiquidation"
            @click="$emit('request-decision', 'rejected')"
          >
            <XCircle class="w-4 h-4" />
            Reject
          </button>
          <button
            class="btn btn-cta min-h-[42px] disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="isReviewingOwnLiquidation"
            @click="$emit('request-decision', 'accepted')"
          >
            <CheckCircle class="w-4 h-4" />
            Accept
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
