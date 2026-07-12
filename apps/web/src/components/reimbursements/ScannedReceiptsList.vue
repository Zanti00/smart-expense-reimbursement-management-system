<script setup>
import { computed } from "vue";
import {
  FileText,
  Image as ImageIcon,
  Trash2,
  MapPin,
  Sparkles,
  ChevronDown,
  X,
  PlusCircle,
} from "lucide-vue-next";
import {
  itemsGrossAmount,
  receiptFinancials,
} from "@/utils/receiptUtils";

const props = defineProps({
  receipts: {
    type: Array,
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  allowRemove: {
    type: Boolean,
    default: true,
  },
});

defineEmits(["remove-receipt"]);

const hasUploadingReceipts = computed(() =>
  props.receipts.some((receipt) => receipt.isUploading),
);

function getCategoryOptions() {
  return props.categories;
}

function syncCategoryName(receipt) {
  const category = props.categories.find(
    (cat) => String(cat.id) === String(receipt.categoryId),
  );
  receipt.category = category?.name || "";
}

function cleanName(fileName) {
  return (fileName || "").replace(/\.[^.]+$/, "").replace(/[_-]/g, " ");
}

function recalculateFinancials(receipt) {
  const amounts = receiptFinancials(
    { amount: Number(receipt.amount) || 0 },
    receipt.vatClassification,
  );

  receipt.vatClassification = amounts.vatClassification;
  receipt.tax = amounts.vat.toFixed(2);
  receipt.subtotal = amounts.subtotal.toFixed(2);
}

function recalculateFromSubtotal(receipt) {
  const sub = Number(receipt.subtotal) || 0;
  if (receipt.vatClassification === 'non-vat') {
    receipt.tax = "0.00";
    receipt.amount = sub.toFixed(2);
  } else {
    const tax = Number(receipt.tax) || 0;
    receipt.amount = (sub + tax).toFixed(2);
  }
}

function handleVatClassChange(receipt) {
  recalculateFinancials(receipt);
}

function addReceiptItem(receipt) {
  if (!receipt.items) {
    receipt.items = [];
  }
  receipt.items.push({ name: "New Item", qty: 1, price: 0 });
}

function recalculateFromItems(receipt) {
  const newTotal = itemsGrossAmount(receipt.items || []);
  receipt.amount = newTotal;
  recalculateFinancials(receipt);
}

function removeReceiptItem(receipt, index) {
  receipt.items.splice(index, 1);
  recalculateFromItems(receipt);
}
</script>

<template>
  <section class="card p-6">
    <div class="flex items-center justify-between mb-5">
      <h2
        class="text-lg font-bold text-primary"
        style="font-family: 'Poppins', sans-serif"
      >
        Scanned Receipts
      </h2>
    </div>

    <div
      v-if="hasUploadingReceipts"
      class="mb-5 flex items-center gap-2 rounded-xl border border-accent/20 bg-accent-50 px-4 py-3 text-xs font-bold uppercase tracking-widest text-accent"
    >
      <span
        class="h-3.5 w-3.5 rounded-full border-2 border-current border-t-transparent animate-spin"
        aria-hidden="true"
      />
      Uploading and reading receipt data...
    </div>

    <!-- One block per receipt -->
    <div class="flex flex-col gap-8">
      <div
        v-for="(receipt, idx) in receipts"
        :key="receipt.id"
        class="border border-accent/15 rounded-xl p-6 bg-accent-50/20"
      >
        <!-- Receipt number badge -->
        <div class="flex items-center gap-2 mb-4">
          <span
            class="w-6 h-6 rounded-full bg-primary text-white text-[11px] font-bold flex items-center justify-center"
            >{{ idx + 1 }}</span
          >
          <span
            class="text-xs font-bold text-primary"
            style="font-family: 'Poppins', sans-serif"
            >{{ cleanName(receipt.fileName) }}</span
          >
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
          <!-- Left: Image Preview -->
          <div class="lg:col-span-4 flex flex-col gap-4">
            <div
              class="relative aspect-[3/4] rounded-xl overflow-hidden border border-slate-200 bg-white shadow-sm flex items-center justify-center"
            >
              <img
                v-if="receipt.thumbnail"
                :src="receipt.thumbnail"
                :alt="receipt.fileName"
                class="w-full h-full object-contain"
              />
              <div
                v-else
                class="flex flex-col items-center gap-2 text-slate-300"
              >
                <FileText
                  v-if="
                    receipt.fileType === 'pdf' ||
                    receipt.fileType === 'application/pdf'
                  "
                  class="w-12 h-12 opacity-40"
                />
                <ImageIcon v-else class="w-12 h-12 opacity-40" />
                <p
                  class="text-[10px] font-semibold uppercase tracking-widest"
                  style="font-family: 'Poppins', sans-serif"
                >
                  No Preview
                </p>
              </div>
              <div
                v-if="receipt.isUploading"
                class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/80 text-accent backdrop-blur-[1px]"
              >
                <span
                  class="h-8 w-8 rounded-full border-2 border-current border-t-transparent animate-spin"
                  aria-hidden="true"
                />
                <span class="text-[10px] font-bold uppercase tracking-widest">
                  Uploading...
                </span>
              </div>
            </div>
            <div>
              <button
                v-if="allowRemove"
                class="inline-flex h-9 w-fit items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3.5 text-xs font-bold text-danger transition-colors hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed"
                type="button"
                :disabled="receipt.isUploading"
                @click="$emit('remove-receipt', receipt)"
              >
                <Trash2 class="w-3.5 h-3.5" /> Delete Receipt
              </button>
            </div>
          </div>

          <!-- Right: Extracted Fields -->
          <div class="lg:col-span-8 flex flex-col gap-4">
            <!-- Row 1: Invoice + Date -->
            <div class="grid grid-cols-2 gap-4">
              <div class="input-wrapper">
                <label class="input-label">Invoice Number <span class="text-danger">*</span></label>
                <input
                  class="input disabled:opacity-50 disabled:cursor-not-allowed"
                  type="text"
                  v-model="receipt.invoiceNumber"
                  :disabled="receipt.isUploading"
                />
              </div>
              <div class="input-wrapper">
                <label class="input-label">Date <span class="text-danger">*</span></label>
                <div class="relative">
                  <input
                    class="input disabled:opacity-50 disabled:cursor-not-allowed"
                    type="date"
                    v-model="receipt.date"
                    :disabled="receipt.isUploading"
                  />
                </div>
              </div>
            </div>

            <!-- Merchant -->
            <div class="input-wrapper">
              <div class="flex items-center justify-between">
                <label class="input-label">TIN Number <span class="text-danger">*</span></label>
              </div>
              <input class="input disabled:opacity-50 disabled:cursor-not-allowed" type="text" v-model="receipt.tin" :disabled="receipt.isUploading" />
            </div>

            <div class="input-wrapper">
              <label class="input-label">Merchant Name <span class="text-danger">*</span></label>
              <input
                class="input disabled:opacity-50 disabled:cursor-not-allowed"
                type="text"
                v-model="receipt.merchantName"
                :disabled="receipt.isUploading"
              />
            </div>

            <!-- Location -->
            <div class="input-wrapper">
              <label class="input-label">Location <span class="text-danger">*</span></label>
              <div class="relative">
                <input
                  class="input pr-10 disabled:opacity-50 disabled:cursor-not-allowed"
                  type="text"
                  v-model="receipt.location"
                  :disabled="receipt.isUploading"
                />
                <MapPin
                  class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                />
              </div>
            </div>

            <!-- Category with AI badge -->
            <div class="input-wrapper">
              <label class="input-label"
                >Category (AI Auto-Detected) <span class="text-danger">*</span></label
              >
              <div class="flex gap-2">
                <div class="relative flex-1">
                  <select
                    class="input appearance-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    v-model="receipt.categoryId"
                    :disabled="receipt.isUploading"
                    @change="syncCategoryName(receipt)"
                  >
                    <option value="" disabled>Select category</option>
                    <option
                      v-for="cat in getCategoryOptions()"
                      :key="cat.id"
                      :value="cat.id"
                    >
                      {{ cat.name }}
                    </option>
                  </select>
                  <ChevronDown
                    class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                  />
                </div>
                <span
                  class="bg-accent text-white px-3 py-2 rounded-lg text-[11px] font-bold flex items-center gap-1.5 flex-shrink-0"
                >
                  <Sparkles class="w-3 h-3 fill-white" /> AI Detected
                </span>
              </div>
            </div>

            <!-- Order Items -->
            <div class="input-wrapper">
              <label class="input-label"
                >Order Items <span class="text-danger">*</span></label
              >
              <div
                class="border border-slate-100 rounded-lg overflow-hidden shadow-sm bg-white"
              >
                <table class="w-full text-left border-collapse">
                  <thead
                    class="bg-slate-50 text-[11px] text-slate-500 uppercase"
                  >
                    <tr>
                      <th class="px-4 py-2.5 font-bold">Items</th>
                      <th class="px-4 py-2.5 font-bold text-center">
                        Qty
                      </th>
                      <th class="px-4 py-2.5 font-bold text-right">
                        Price
                      </th>
                    </tr>
                  </thead>
                  <tbody class="text-sm divide-y divide-slate-50">
                    <tr
                      v-for="(item, itemIdx) in receipt.items"
                      :key="itemIdx"
                    >
                      <td class="px-4 py-2">
                        <input
                          type="text"
                          class="input !py-1 !text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                          v-model="item.name"
                          :disabled="receipt.isUploading"
                        />
                      </td>
                      <td class="px-0 py-2 w-20">
                        <input
                          type="number"
                          class="input !py-1 !text-sm text-center disabled:opacity-50 disabled:cursor-not-allowed"
                          v-model="item.qty"
                          :disabled="receipt.isUploading"
                          @input="recalculateFromItems(receipt)"
                        />
                      </td>
                      <td class="px-4 py-2 w-32">
                        <input
                          type="number"
                          class="input !py-1 !text-sm text-right font-mono text-primary font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                          v-model.number="item.price"
                          :disabled="receipt.isUploading"
                          @input="recalculateFromItems(receipt)"
                        />
                      </td>
                      <td class="pr-4 py-2 w-10 text-right">
                        <button
                          class="text-slate-400 hover:text-danger transition-colors p-1 disabled:opacity-50 disabled:cursor-not-allowed"
                          :disabled="receipt.isUploading"
                          @click="removeReceiptItem(receipt, itemIdx)"
                        >
                          <X class="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <div
                  class="px-4 py-2 bg-slate-50/50 border-t border-slate-50"
                >
                  <button
                    class="text-xs font-bold text-accent flex items-center gap-1 hover:text-accent-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="receipt.isUploading"
                    @click="addReceiptItem(receipt)"
                  >
                    <PlusCircle class="w-3.5 h-3.5" /> Add Item
                  </button>
                </div>
              </div>
            </div>

            <!-- Financials for this receipt -->
            <div
              class="flex flex-col gap-4 pt-2 border-t border-slate-100"
            >
              <div class="flex items-end justify-between gap-4">
                <div class="flex gap-4 flex-wrap items-end">
                  <div class="input-wrapper">
                    <label class="input-label">VAT Classification <span class="text-danger">*</span></label>
                    <div class="relative">
                      <select
                        class="input !w-32 !bg-white appearance-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                        v-model="receipt.vatClassification"
                        :disabled="receipt.isUploading"
                        @change="handleVatClassChange(receipt)"
                      >
                        <option value="vat">VAT</option>
                        <option value="non-vat">NON-VAT</option>
                      </select>
                      <ChevronDown class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                    </div>
                  </div>
                  <div class="input-wrapper">
                    <label class="input-label">Subtotal <span class="text-danger">*</span></label>
                    <input
                      class="input !w-32 !bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                      type="number"
                      v-model="receipt.subtotal"
                      :disabled="receipt.isUploading"
                      @input="recalculateFromSubtotal(receipt)"
                    />
                  </div>
                  <div class="input-wrapper">
                    <label class="input-label">Tax (VAT 12%) <span class="text-danger">*</span></label>
                    <input
                      class="input !w-32 !bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                      type="number"
                      v-model="receipt.tax"
                      :disabled="receipt.vatClassification === 'non-vat' || receipt.isUploading"
                      @input="recalculateFromSubtotal(receipt)"
                    />
                  </div>
                </div>
                <div
                  class="bg-accent-50 px-6 py-3 rounded-xl border border-accent/15 flex flex-col items-end shadow-sm"
                >
                  <label
                    class="text-[10px] font-bold text-accent uppercase tracking-wider mb-1"
                    >Total <span class="text-danger">*</span></label
                  >
                  <input
                    type="number"
                    class="input !w-36 !bg-white font-mono text-xl font-black text-accent text-right disabled:opacity-50 disabled:cursor-not-allowed"
                    v-model="receipt.amount"
                    :disabled="receipt.isUploading"
                    @input="recalculateFinancials(receipt)"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
