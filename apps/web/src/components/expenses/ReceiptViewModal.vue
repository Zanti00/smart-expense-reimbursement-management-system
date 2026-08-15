<script setup>
import { computed, ref } from "vue";
import {
  X,
  FileText,
  Image as ImageIcon,
  Download,
  Trash2,
  Pencil,
  AlertTriangle,
} from "lucide-vue-next";
import { formatAmount, formatDate as formatDateBase } from "@/utils/formatters";
import { canEditReceipt, canDeleteReceipt } from "@/utils/receiptUtils";
import StatusBadge from "@/components/base/StatusBadge.vue";
import ImagePreviewModal from "@/components/base/ImagePreviewModal.vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  receipt: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:modelValue", "delete", "edit"]);

const canEdit = computed(() => canEditReceipt(props.receipt));
const canDelete = computed(() => canDeleteReceipt(props.receipt));
const imageError = ref(false);
const isImagePreviewOpen = ref(false);

function close() {
  emit("update:modelValue", false);
}

function editReceipt() {
  emit("edit", props.receipt);
  close();
}

const actualSubtotal = computed(() => {
  if (props.receipt?.items?.length) {
    return props.receipt.items.reduce(
      (sum, item) => sum + (Number(item.price) * Number(item.quantity) || 0),
      0,
    );
  }
  return props.receipt
    ? props.receipt.amount - (props.receipt.vatAmount || 0)
    : 0;
});

function formatDate(dateStr) {
  return formatDateBase(dateStr, {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="modelValue && receipt"
      class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
      @click="close"
    >
      <div
        class="relative bg-white w-full max-w-[840px] rounded-3xl shadow-[0_20px_40px_-10px_rgba(0,0,0,0.1)] flex flex-col md:flex-row p-6 gap-8 overflow-hidden min-h-[500px] max-h-[90vh]"
        @click.stop
      >
        <!-- Close Button -->
        <button
          @click="close"
          class="absolute top-6 right-6 text-slate-400 hover:bg-slate-100 transition-colors p-2 rounded-full flex items-center justify-center z-10"
        >
          <X class="w-5 h-5" />
        </button>

        <!-- Left Column: Image -->
        <div
          class="w-full md:w-5/12 bg-gradient-to-b from-slate-100 to-slate-50 rounded-2xl flex items-center justify-center p-4 shrink-0 relative overflow-hidden min-h-[400px]"
        >
          <div
            v-if="
              receipt.thumbnail &&
              receipt.fileType !== 'application/pdf' &&
              !imageError
            "
            class="relative w-full h-full flex items-center justify-center group cursor-zoom-in"
            title="Click to zoom and preview image"
            @click="isImagePreviewOpen = true"
          >
            <img
              :src="receipt.thumbnail"
              class="w-full h-full object-contain rounded-md transition-transform duration-200 group-hover:scale-[1.02]"
              alt="Receipt"
              @error="imageError = true"
            />
            <!-- Hover Preview Overlay Badge -->
            <div
              class="absolute inset-0 bg-slate-950/0 group-hover:bg-slate-950/15 transition-all duration-200 rounded-md flex items-center justify-center pointer-events-none"
            >
              <span
                class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 bg-slate-900/80 backdrop-blur-sm text-white text-[11px] font-medium px-3 py-1.5 rounded-full shadow-md flex items-center gap-1.5"
              >
                <ImageIcon class="w-3.5 h-3.5" />
                Click to preview
              </span>
            </div>
          </div>
          <div v-else class="flex flex-col items-center gap-2 text-slate-300">
            <FileText
              v-if="
                receipt.fileType === 'application/pdf' ||
                receipt.fileType === 'pdf'
              "
              class="w-12 h-12"
            />
            <ImageIcon v-else class="w-12 h-12" />
            <span class="text-xs text-slate-400">No preview</span>
          </div>
        </div>

        <!-- Right Column: Content -->
        <div
          class="w-full md:w-7/12 flex flex-col pt-4 pb-2 pr-2 overflow-y-auto"
        >
          <!-- Rejection Alert -->
          <div
            v-if="receipt.status === 'automatic-rejected'"
            class="flex items-start gap-2.5 p-3 rounded-lg bg-red-50 border border-red-100 mb-5"
          >
            <AlertTriangle class="h-4 w-4 shrink-0 text-red-500 mt-0.5" />
            <div>
              <p class="text-xs font-medium text-red-700">Automatic Rejected</p>
              <p class="text-xs text-red-500 mt-0.5">
                {{
                  receipt.complianceReason ||
                  "System validation could not approve this receipt."
                }}
              </p>
            </div>
          </div>

          <!-- Order Info -->
          <div
            class="flex justify-between items-end border-b border-slate-100 pb-4 mb-5"
          >
            <div class="flex flex-col gap-0.5">
              <span class="text-[11px] text-slate-400 uppercase tracking-wide"
                >Confirmation</span
              >
              <span class="text-sm text-slate-700">{{
                receipt.invoiceNumber || receipt.fileName
              }}</span>
            </div>
            <div class="flex flex-col gap-0.5 items-end">
              <span class="text-sm text-slate-500">{{
                formatDate(receipt.date)
              }}</span>
            </div>
          </div>

          <!-- Vendor -->
          <div class="mb-5">
            <span class="text-[11px] text-slate-400 uppercase tracking-wide"
              >Vendor</span
            >
            <p class="text-base font-medium text-slate-800 mt-0.5">
              {{ receipt.vendorName || "Unknown Vendor" }}
            </p>
          </div>

          <!-- Items List -->
          <div v-if="receipt.items && receipt.items.length > 0" class="mb-5">
            <h3 class="text-[11px] text-slate-400 uppercase tracking-wide mb-3">
              Items
            </h3>
            <div class="flex flex-col gap-3">
              <div
                v-for="(item, index) in receipt.items"
                :key="item.id || index"
                class="flex justify-between items-center"
              >
                <div class="flex items-center gap-3">
                  <span
                    class="w-6 h-6 rounded-full bg-slate-100 text-accent flex items-center justify-center text-xs font-medium shrink-0"
                  >
                    {{ index + 1 }}
                  </span>
                  <span class="text-sm text-slate-700"
                    >{{ item.name || "Item" }} ({{ item.quantity }}x)</span
                  >
                </div>
                <span class="text-sm text-slate-700">
                  {{
                    formatAmount(
                      Number(item.price) * Number(item.quantity) || 0,
                      receipt.currency || "PHP",
                    )
                  }}
                </span>
              </div>
            </div>
          </div>

          <!-- Summary -->
          <div class="flex flex-col gap-2 pt-4 border-t border-slate-100 mb-5">
            <div class="flex justify-between items-center text-sm">
              <span class="text-slate-400">Subtotal</span>
              <span class="text-slate-600">{{
                formatAmount(actualSubtotal, receipt.currency || "PHP")
              }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-slate-400">VAT</span>
              <span class="text-slate-600">{{
                formatAmount(receipt.vatAmount || 0, receipt.currency || "PHP")
              }}</span>
            </div>
            <div
              class="flex justify-between items-center pt-2 mt-1 border-t border-slate-100"
            >
              <span class="text-sm text-slate-700">Total</span>
              <span class="text-base font-semibold text-primary">
                {{ formatAmount(receipt.amount, receipt.currency || "PHP") }}
              </span>
            </div>
          </div>

          <!-- Metadata -->
          <div
            class="grid grid-cols-2 gap-x-6 gap-y-3 mb-5 pb-5 border-b border-slate-100"
          >
            <div>
              <span class="text-[11px] text-slate-400 uppercase tracking-wide"
                >Category</span
              >
              <p class="text-sm text-slate-700 mt-0.5">
                {{ receipt.category || "—" }}
              </p>
            </div>
            <div>
              <span class="text-[11px] text-slate-400 uppercase tracking-wide"
                >TIN</span
              >
              <p class="text-sm text-slate-700 mt-0.5">
                {{ receipt.tin || "—" }}
              </p>
            </div>
            <div>
              <span class="text-[11px] text-slate-400 uppercase tracking-wide"
                >VAT Type</span
              >
              <p class="text-sm text-slate-700 mt-0.5 uppercase">
                {{ receipt.vatClassification || "—" }}
              </p>
            </div>
            <div>
              <span class="text-[11px] text-slate-400 uppercase tracking-wide"
                >Currency</span
              >
              <p class="text-sm text-slate-700 mt-0.5">
                {{ receipt.currency || "PHP" }}
              </p>
            </div>
          </div>

          <!-- Status -->
          <div class="flex justify-between items-center mb-6">
            <span class="text-sm text-slate-500">Status</span>
            <StatusBadge :status="receipt.status" />
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-3 mt-auto">
            <button
              v-if="canEdit"
              @click="editReceipt"
              class="btn btn-secondary flex-1"
            >
              <Pencil class="w-4 h-4" /> Edit
            </button>
            <button
              v-if="canDelete"
              @click="
                emit('delete', receipt.id);
                close();
              "
              class="btn btn-danger !px-3"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- Reusable Fullscreen Image Preview Modal with Zoom & Controls -->
  <ImagePreviewModal
    v-model="isImagePreviewOpen"
    :src="receipt?.thumbnail || ''"
    :alt="receipt?.vendorName || 'Receipt'"
    :title="receipt?.invoiceNumber ? `Invoice #${receipt.invoiceNumber}` : (receipt?.fileName || receipt?.vendorName || 'Receipt Image')"
  />
</template>
