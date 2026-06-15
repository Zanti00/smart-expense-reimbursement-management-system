<script setup>
import { computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { formatPeso } from "@/utils/formatters";
import {
  ArrowLeft,
  CalendarDays,
  X,
  Sparkles,
  CheckCircle,
  FileText,
  Download,
  Clock,
  XCircle,
} from "lucide-vue-next";

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  receipt: {
    type: Object,
    default: null,
  },
  reviewerNotes: {
    type: String,
    default: "",
  },
  pendingDecisionAction: {
    type: String,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "close",
  "close-all",
  "update:reviewerNotes",
  "request-decision",
  "cancel-decision",
  "confirm-decision",
]);

const auth = useAuthStore();

const isProcessing = computed(() => props.receipt?.status === 'processing');

const localReviewerNotes = computed({
  get: () => props.reviewerNotes,
  set: (val) => emit("update:reviewerNotes", val),
});
</script>

<template>
  <Transition name="modal">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[1px]"
    >
      <div
        class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
      >
        <header
          class="flex items-center justify-between border-b border-primary/10 bg-primary px-5 py-4 text-white"
        >
          <div class="flex min-w-0 items-center gap-4">
            <button
              class="inline-flex items-center gap-2 rounded-md px-2 py-1 text-xs font-bold text-white/90 transition-colors hover:bg-white/10"
              @click="emit('close')"
            >
              <ArrowLeft class="h-4 w-4" />
              Back
            </button>
            <div class="h-6 w-px bg-white/20" />
            <div class="flex min-w-0 items-center gap-2">
              <span
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10"
              >
                <CalendarDays class="h-4 w-4" />
              </span>
              <div class="min-w-0">
                <h3
                  class="truncate font-heading text-lg font-bold text-white"
                >
                  Receipt Details
                </h3>
                <p class="truncate text-xs font-semibold text-white/65">
                  AI-scanned reimbursement receipt extraction
                </p>
              </div>
            </div>
          </div>
          <button
            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white/85 transition-colors hover:bg-white/10 hover:text-white"
            title="Close receipt details"
            @click="emit('close-all')"
          >
            <X class="h-5 w-5" />
          </button>
        </header>

        <div class="flex-1 overflow-y-auto bg-slate-50 p-5 scrollbar-thin">
          <div
            class="mb-4 flex flex-col gap-3 rounded-lg border border-accent/20 bg-accent-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="flex items-center gap-3">
              <span
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-accent shadow-sm"
              >
                <Sparkles class="h-4 w-4" />
              </span>
              <div>
                <p
                  class="text-xs font-bold uppercase tracking-[0.12em] text-accent"
                >
                  AI Scanned
                </p>
                <p class="text-sm font-semibold text-primary">
                  Details automatically extracted from the uploaded receipt.
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
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
          >
            <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)]">
              <aside
                class="border-b border-slate-200 bg-slate-100/70 p-5 lg:border-b-0 lg:border-r flex flex-col"
              >
                <div class="mb-4 flex items-center justify-between gap-3">
                  <div>
                    <p class="kpi-label text-slate-400">
                      Receipt Scanned Image
                    </p>
                    <h4
                      class="mt-1 font-heading text-base font-bold text-slate-900"
                    >
                      {{ receipt?.vendor_name || "Receipt" }}
                    </h4>
                  </div>
                  <span
                    class="inline-flex rounded-md bg-accent-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-accent"
                  >
                    {{ receipt?.category || "Expense" }}
                  </span>
                </div>
                <div
                  class="overflow-hidden rounded-lg border border-slate-200 shadow-sm flex items-center justify-center bg-slate-50 flex-1 aspect-square lg:aspect-auto"
                >
                  <div v-if="isProcessing" class="h-full w-full animate-pulse bg-slate-200"></div>
                  <img
                    v-else-if="receipt?.file_url"
                    :src="receipt.file_url"
                    alt="Scanned receipt"
                    class="h-full w-full object-contain max-h-[500px]"
                  />
                  <div
                    v-else
                    class="flex flex-col items-center justify-center p-8 text-slate-300"
                  >
                    <FileText class="w-12 h-12 stroke-1" />
                    <span class="text-xs mt-2">No image attached</span>
                  </div>
                </div>
                <a
                  v-if="receipt?.file_url && !isProcessing"
                  :href="receipt.file_url"
                  target="_blank"
                  class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-accent/20 bg-white px-3 py-2.5 text-xs font-bold text-accent transition-colors hover:bg-accent-50"
                >
                  <Download class="h-4 w-4" />
                  Download Receipt
                </a>
              </aside>

              <section class="space-y-5 p-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <label class="space-y-1">
                    <span class="input-label">Invoice Number</span>
                    <div v-if="isProcessing" class="h-10 w-full animate-pulse rounded-lg bg-slate-200"></div>
                    <input
                      v-else
                      class="input bg-slate-50"
                      disabled
                      :value="receipt?.invoice_number || '--'"
                    />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Transaction Date</span>
                    <div v-if="isProcessing" class="h-10 w-full animate-pulse rounded-lg bg-slate-200"></div>
                    <span v-else class="relative block">
                      <input
                        class="input pr-10 bg-slate-50"
                        disabled
                        :value="
                          receipt?.transaction_date
                            ? new Date(
                                receipt.transaction_date,
                              ).toLocaleDateString()
                            : '--'
                        "
                      />
                      <CalendarDays
                        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                      />
                    </span>
                  </label>
                  <label class="space-y-1">
                    <span class="flex items-center justify-between gap-2">
                      <span class="input-label">TIN Number</span>
                      <span
                        class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent"
                      >
                        <Sparkles class="h-3 w-3" />
                        AI Read
                      </span>
                    </span>
                    <div v-if="isProcessing" class="h-10 w-full animate-pulse rounded-lg bg-slate-200"></div>
                    <input
                      v-else
                      class="input bg-slate-50"
                      disabled
                      :value="receipt?.tin || '--'"
                    />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Merchant Name</span>
                    <div v-if="isProcessing" class="h-10 w-full animate-pulse rounded-lg bg-slate-200"></div>
                    <input
                      v-else
                      class="input bg-slate-50"
                      disabled
                      :value="receipt?.vendor_name || '--'"
                    />
                  </label>
                  <label class="space-y-1 md:col-span-2">
                    <span class="flex items-center justify-between gap-2">
                      <span class="input-label"
                        >VAT Classification</span
                      >
                    </span>
                    <div v-if="isProcessing" class="h-10 w-full animate-pulse rounded-lg bg-slate-200"></div>
                    <input
                      v-else
                      class="input bg-slate-50"
                      disabled
                      :value="
                        receipt?.vat_classification
                          ? receipt.vat_classification.toUpperCase()
                          : 'N/A'
                      "
                    />
                  </label>
                </div>

                <!-- Order Items List -->
                <div v-if="isProcessing" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                  <div class="border-b border-slate-200 bg-slate-50/50 px-5 py-3.5">
                    <div class="h-3 w-24 animate-pulse rounded bg-slate-200"></div>
                  </div>
                  <ul class="divide-y divide-slate-100">
                    <li class="flex items-center justify-between gap-4 px-5 py-4" v-for="i in 2" :key="i">
                      <div class="flex items-center gap-3.5 w-full">
                        <div class="h-9 w-9 shrink-0 animate-pulse rounded-full bg-slate-200"></div>
                        <div class="h-4 w-1/3 animate-pulse rounded bg-slate-200"></div>
                      </div>
                      <div class="h-4 w-16 animate-pulse rounded bg-slate-200"></div>
                    </li>
                  </ul>
                </div>

                <div
                  class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                  v-else-if="
                    receipt?.items && receipt.items.length > 0
                  "
                >
                  <div
                    class="border-b border-slate-200 bg-slate-50/50 px-5 py-3.5"
                  >
                    <h4
                      class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500"
                    >
                      Order Items
                    </h4>
                  </div>
                  <ul class="divide-y divide-slate-100">
                    <li
                      v-for="item in receipt.items"
                      :key="item.name || item.id"
                      class="group flex items-center justify-between gap-4 px-5 py-4 transition-colors hover:bg-slate-50/80"
                    >
                      <div class="flex min-w-0 items-center gap-3.5">
                        <div
                          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100/80 text-xs font-bold text-slate-600 transition-colors group-hover:bg-white group-hover:shadow-sm"
                        >
                          {{ item.quantity }}x
                        </div>
                        <div class="min-w-0">
                          <p class="truncate text-sm font-bold text-slate-800">
                            {{ item.name }}
                          </p>
                        </div>
                      </div>
                      <div class="text-right">
                        <p class="font-heading text-sm font-bold text-primary">
                          {{ formatPeso(item.price) }}
                        </p>
                      </div>
                    </li>
                  </ul>
                </div>

                <div
                  class="grid grid-cols-1 gap-3 border-t border-slate-200 pt-4 sm:grid-cols-3"
                >
                  <label class="space-y-1">
                    <span class="input-label">Subtotal</span>
                    <div v-if="isProcessing" class="h-10 w-full animate-pulse rounded-lg bg-slate-200"></div>
                    <input
                      v-else
                      class="input font-semibold bg-slate-50"
                      disabled
                      :value="
                        receipt?.total_amount
                          ? formatPeso(receipt.total_amount)
                          : '--'
                      "
                    />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Tax (VAT)</span>
                    <div v-if="isProcessing" class="h-10 w-full animate-pulse rounded-lg bg-slate-200"></div>
                    <input
                      v-else
                      class="input font-semibold bg-slate-50"
                      disabled
                      :value="
                        receipt?.vat_amount !== undefined && receipt?.vat_amount !== null
                          ? formatPeso(receipt.vat_amount)
                          : formatPeso(0)
                      "
                    />
                  </label>
                  <div
                    class="rounded-lg border border-accent/20 bg-accent-50 p-3"
                  >
                    <p class="input-label text-accent">Orders Total</p>
                    <div v-if="isProcessing" class="mt-1 h-7 w-24 animate-pulse rounded bg-accent/20"></div>
                    <p
                      v-else
                      class="mt-1 font-heading text-xl font-bold text-primary"
                    >
                      {{
                        receipt?.total_amount
                          ? formatPeso(Number(receipt.total_amount) + Number(receipt.vat_amount || 0))
                          : "--"
                      }}
                    </p>
                  </div>
                </div>

                <footer
                  class="flex items-center gap-2 text-xs font-semibold text-slate-400"
                >
                  <Clock class="h-4 w-4" />
                  Uploaded receipt Ref #{{ receipt?.id }}
                </footer>
              </section>
            </div>
          </div>
        </div>

        <!-- Admin Receipt Decision Actions -->
        <div
          v-if="
            auth.isAdmin &&
            receipt &&
            (receipt.status === 'processing' ||
              receipt.status === 'pending' ||
              receipt.status === 'submitted')
          "
          class="border-t border-slate-200 bg-white px-5 py-4"
        >
          <div class="mb-4">
            <label class="input-label mb-1.5 block"
              >Reviewer Notes (Optional)</label
            >
            <textarea
              v-model="localReviewerNotes"
              class="input min-h-20 resize-none bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              placeholder="Add notes explaining the decision..."
              :disabled="isProcessing || isSubmitting"
            />
          </div>
          <div
            v-if="pendingDecisionAction"
            class="flex flex-col gap-3 rounded-lg border border-accent/20 bg-accent-50 p-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <p class="text-sm font-semibold text-primary">
              Are you sure you want to
              {{ pendingDecisionAction }} this receipt?
            </p>
            <div class="flex shrink-0 items-center gap-2">
              <button
                class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                type="button"
                :disabled="isProcessing || isSubmitting"
                @click="emit('cancel-decision')"
              >
                Cancel
              </button>
              <button
                class="inline-flex min-h-9 items-center justify-center rounded-lg bg-accent px-4 text-xs font-bold text-white transition-colors hover:bg-accent/90 disabled:cursor-not-allowed disabled:opacity-60"
                type="button"
                :disabled="isProcessing || isSubmitting"
                @click="emit('confirm-decision')"
              >
                Confirm
              </button>
            </div>
          </div>
          <div v-else class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button
              class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 text-sm font-bold text-red-700 transition-colors hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-400 disabled:border-slate-200"
              type="button"
              :disabled="isProcessing"
              @click="emit('request-decision', 'Reject')"
            >
              <XCircle class="h-4 w-4" />
              Reject
            </button>
            <button
              class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-accent px-4 text-sm font-bold text-white transition-colors hover:bg-accent/90 disabled:cursor-not-allowed disabled:opacity-60 disabled:bg-slate-300"
              type="button"
              :disabled="isProcessing"
              @click="emit('request-decision', 'Approve')"
            >
              <CheckCircle class="h-4 w-4" />
              Approve
            </button>
          </div>
        </div>
        <div
          v-else-if="receipt?.admin_notes"
          class="border-t border-slate-200 bg-slate-50 px-5 py-4"
        >
          <p
            class="text-xs font-bold text-slate-400 uppercase tracking-wider"
          >
            Reviewer Notes
          </p>
          <p class="text-sm font-semibold text-slate-700 mt-1">
            {{ receipt.admin_notes }}
          </p>
        </div>
      </div>
    </div>
  </Transition>
</template>
