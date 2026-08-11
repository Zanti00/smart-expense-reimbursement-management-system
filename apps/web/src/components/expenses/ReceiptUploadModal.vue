<script setup>
import { ref, computed, watch } from "vue";
import { useReceiptStore } from "@/stores/receipts";
import { useToast } from "@/composables/useToast";
import ConfirmModal from "@/components/base/ConfirmModal.vue";
import CurrencySelect from "@/components/base/CurrencySelect.vue";
import { useUnsavedChanges } from "@/composables/useUnsavedChanges";
import {
  buildReceiptUploadFormPrefill,
  itemsGrossAmount,
  receiptFinancials,
} from "@/utils/receiptUtils";
import {
  X,
  UploadCloud,
  FileText,
  ChevronDown,
  Save,
  Plus,
  Trash2,
} from "lucide-vue-next";



const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  receiptToEdit: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:modelValue"]);

const receiptsStore = useReceiptStore();
const { addToast } = useToast();

function notify(message, type = "info") {
  addToast({ message, type });
}

const uploadFileInput = ref(null);
const uploadFile = ref(null);
const uploadFilePreview = ref("");
const uploadForm = ref({
  invoice_number: "",
  transaction_date: "",
  tin: "",
  vendor_name: "",
  expense_category_id: "",
  total_amount: "",
  vat_amount: "",
  vat_classification: "vat",
  currency: "",
  items: [],
});

const itemsGrossTotal = computed(() => itemsGrossAmount(uploadForm.value.items));

const receiptAmounts = computed(() =>
  receiptFinancials(
    { amount: Number(uploadForm.value.total_amount) || 0 },
    uploadForm.value.vat_classification,
  ),
);

const vatExclusiveSubtotal = computed(() => receiptAmounts.value.subtotal);

const validationErrors = computed(() => {
  const errors = [];

  if (!uploadFile.value && !props.receiptToEdit) {
    errors.push("Please select a receipt file.");
  }
  if (!uploadForm.value.invoice_number) {
    errors.push("Invoice number is required.");
  }
  if (!uploadForm.value.transaction_date) {
    errors.push("Transaction date is required.");
  }
  if (!uploadForm.value.tin) {
    errors.push("TIN number is required.");
  } else {
    const tinDigits = uploadForm.value.tin.replace(/\D/g, "");
    if (tinDigits.length < 9) {
      errors.push("TIN must contain at least 9 digits.");
    }
  }
  if (!uploadForm.value.vendor_name) {
    errors.push("Vendor name is required.");
  }
  if (!uploadForm.value.expense_category_id) {
    errors.push("Category is required.");
  }
  if (!uploadForm.value.vat_classification) {
    errors.push("VAT classification is required.");
  }
  if (
    uploadForm.value.total_amount === "" ||
    uploadForm.value.total_amount == null
  ) {
    errors.push("Total amount is required.");
  }
  if (
    uploadForm.value.vat_classification === "vat" &&
    (uploadForm.value.vat_amount === "" || uploadForm.value.vat_amount == null)
  ) {
    errors.push("VAT amount could not be computed.");
  }

  for (const item of uploadForm.value.items) {
    if (!item.name || !item.quantity || item.price === "" || item.price == null) {
      errors.push("All expense item fields are required.");
      break;
    }
  }

  return errors;
});

const isDirty = computed(() => {
  return (
    uploadFile.value !== null ||
    uploadForm.value.invoice_number !== "" ||
    uploadForm.value.transaction_date !== "" ||
    uploadForm.value.tin !== "" ||
    uploadForm.value.vendor_name !== "" ||
    uploadForm.value.expense_category_id !== "" ||
    uploadForm.value.total_amount !== "" ||
    uploadForm.value.items.length > 0
  );
});

const isSubmitted = ref(false);

const {
  showConfirmModal,
  handleConfirmLeave,
  handleCancelLeave,
  dismissWithConfirm,
} = useUnsavedChanges(isDirty, isSubmitted);

watch(itemsGrossTotal, (newGross) => {
  if (uploadForm.value.items.length > 0) {
    uploadForm.value.total_amount =
      newGross > 0 ? Number(newGross.toFixed(2)) : "";
  }
});

watch(
  [() => uploadForm.value.total_amount, () => uploadForm.value.vat_classification],
  () => {
    const amounts = receiptFinancials(
      { amount: Number(uploadForm.value.total_amount) || 0 },
      uploadForm.value.vat_classification,
    );

    uploadForm.value.vat_amount = amounts.vat.toFixed(2);
  },
  { immediate: true },
);

function formatDateForInput(dateStr) {
  if (!dateStr) return "";
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return "";
    return (
      d.getFullYear() +
      "-" +
      String(d.getMonth() + 1).padStart(2, "0") +
      "-" +
      String(d.getDate()).padStart(2, "0")
    );
  } catch (e) {
    return "";
  }
}

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return;

    if (!props.categories.length) {
      await receiptsStore.fetchCategories();
    }

    if (!props.receiptToEdit) return;

    uploadFile.value = null;
    uploadFilePreview.value = props.receiptToEdit.thumbnail || "";
    uploadForm.value = {
      invoice_number: props.receiptToEdit.invoiceNumber || "",
      transaction_date: formatDateForInput(props.receiptToEdit.date),
      tin: props.receiptToEdit.tin || "",
      vendor_name: props.receiptToEdit.vendorName || "",
      expense_category_id: props.receiptToEdit.categoryId || "",
      total_amount: props.receiptToEdit.amount || "",
      vat_classification: props.receiptToEdit.vatClassification || "vat",
      currency: props.receiptToEdit.currency || "",
      vat_amount: receiptFinancials(
        { amount: Number(props.receiptToEdit.amount) || 0 },
        props.receiptToEdit.vatClassification || "vat",
      ).vat.toFixed(2),
      items: props.receiptToEdit.items?.length
        ? props.receiptToEdit.items.map((item) => ({ ...item }))
        : [],
    };
  },
);

const isFormValid = computed(() => validationErrors.value.length === 0);

function addItem() {
  uploadForm.value.items.push({
    name: "",
    quantity: 1,
    price: "",
  });
}

function removeItem(index) {
  uploadForm.value.items.splice(index, 1);
}

function handleUploadFileSelect(event) {
  const file = event.target.files[0];
  if (file) {
    const validTypes = ["image/jpeg", "image/png", "application/pdf"];
    const ext = file.name.split(".").pop().toLowerCase();
    const validExts = ["jpg", "jpeg", "png", "pdf"];

    if (!validTypes.includes(file.type) && !validExts.includes(ext)) {
      notify("Invalid file type. Only JPEG, PNG, or PDF allowed.", "error");
      event.target.value = "";
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      notify("File size exceeds 2MB.", "error");
      event.target.value = "";
      return;
    }
    uploadFile.value = file;
    if (
      file.type.startsWith("image/") ||
      ["jpg", "jpeg", "png"].includes(ext)
    ) {
      uploadFilePreview.value = URL.createObjectURL(file);
    } else {
      uploadFilePreview.value = "";
    }

    const prefill = buildReceiptUploadFormPrefill({
      id: props.receiptToEdit?.id,
      file,
      receiptData: {
        expense_category_id: uploadForm.value.expense_category_id || null,
      },
      thumbnail: uploadFilePreview.value,
    });

    uploadForm.value = {
      ...uploadForm.value,
      ...prefill,
      expense_category_id:
        prefill.expense_category_id || uploadForm.value.expense_category_id,
    };
  }
}

function formatTIN(event) {
  let value = event.target.value.replace(/\D/g, "");
  let formatted = "";
  if (value.length > 0) formatted += value.substring(0, 3);
  if (value.length > 3) formatted += "-" + value.substring(3, 6);
  if (value.length > 6) formatted += "-" + value.substring(6, 9);
  if (value.length > 9) formatted += "-" + value.substring(9, 12);
  uploadForm.value.tin = formatted;
  event.target.value = formatted;
}

function triggerFileUpload() {
  if (receiptsStore.isSaving) return;
  if (uploadFileInput.value) {
    uploadFileInput.value.click();
  }
}

function resetUploadForm() {
  uploadFile.value = null;
  uploadFilePreview.value = "";
  if (uploadFileInput.value) uploadFileInput.value.value = "";

  uploadForm.value = {
    invoice_number: "",
    transaction_date: "",
    tin: "",
    vendor_name: "",
    expense_category_id: "",
    total_amount: "",
    vat_amount: "",
    vat_classification: "vat",
    currency: "",
    items: [],
  };
}

function close() {
  dismissWithConfirm(() => {
    resetUploadForm();
    emit("update:modelValue", false);
  });
}

async function saveReceipt() {
  if (
    props.receiptToEdit &&
    String(props.receiptToEdit.status || "").toLowerCase() !== "processed"
  ) {
    notify("Only receipts with processed status can be edited.", "error");
    return;
  }

  if (!isFormValid.value) {
    notify(validationErrors.value[0] || "Please fill in all required fields.", "error");
    return;
  }
  if (uploadForm.value.tin) {
    const tinRegex = /^\d{3}-\d{3}-\d{3}(?:-\d{3})?$/;
    if (!tinRegex.test(uploadForm.value.tin)) {
      notify("TIN must be in the format 000-000-000 or 000-000-000-000", "error");
      return;
    }
  }
  try {
    const payload = {
      expense_category_id: uploadForm.value.expense_category_id,
      vendor_name: uploadForm.value.vendor_name || null,
      transaction_date: uploadForm.value.transaction_date || null,
      total_amount: uploadForm.value.total_amount || null,
      vat_amount: uploadForm.value.vat_amount || null,
      tin: uploadForm.value.tin || null,
      invoice_number: uploadForm.value.invoice_number || null,
      vat_classification: uploadForm.value.vat_classification || null,
      currency: uploadForm.value.currency || null,
      items:
        uploadForm.value.items.length > 0 ? uploadForm.value.items : undefined,
    };

    if (props.receiptToEdit) {
      notify("Saving...");
      await receiptsStore.resubmitReceipt(
        props.receiptToEdit.id,
        payload,
        uploadFile.value,
      );
      notify("Receipt updated.", "success");
    } else {
      notify("Uploading receipt...");
      await receiptsStore.uploadReceipt(uploadFile.value, payload);
      notify("Receipt uploaded and stored successfully.", "success");
    }
    isSubmitted.value = true;
    close();
  } catch (e) {
    notify(e.message || "Failed to upload receipt.", "error");
  }
}

function formatCurrency(amount) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(amount);
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] flex items-center justify-center p-4"
      @click="close"
    >
      <div
        class="card w-full max-w-5xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300"
        @click.stop
      >
        <!-- Modal Header -->
        <div
          class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10"
        >
          <div class="flex items-center gap-3">
            <h2
              class="text-xl font-bold text-primary"
            >
              {{ receiptToEdit ? "Edit Receipt" : "Receipt Scanned" }}
            </h2>
          </div>
          <button
            @click="close"
            class="p-2 text-slate-400 hover:text-primary transition-colors"
            :disabled="receiptsStore.isSaving"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Modal Content (Two Columns) -->
        <div
          class="flex flex-col md:flex-row flex-1 overflow-y-auto max-h-[75vh] md:max-h-[80vh]"
        >
          <!-- Left Column: File Upload Area -->
          <div
            class="w-full md:w-[340px] p-6 bg-slate-50 border-r border-slate-100 flex flex-col items-center"
          >
            <div
              class="w-full aspect-[3/4] bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col overflow-hidden group relative cursor-pointer"
              @click="triggerFileUpload"
            >
              <!-- Preview if image file selected -->
              <div v-if="uploadFile && uploadFilePreview" class="w-full h-full">
                <img
                  :src="uploadFilePreview"
                  alt="Receipt preview"
                  class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity"
                />
              </div>
              <!-- PDF or no-file placeholder -->
              <div
                v-else
                class="w-full h-full flex flex-col items-center justify-center gap-3 text-slate-400"
              >
                <div
                  class="w-16 h-16 rounded-2xl bg-primary/5 flex items-center justify-center"
                >
                  <UploadCloud
                    v-if="!uploadFile"
                    class="w-7 h-7 text-primary/40"
                  />
                  <FileText v-else class="w-7 h-7 text-primary/40" />
                </div>
                <p
                  class="text-[10px] text-slate-300 font-semibold uppercase tracking-widest text-center px-4"
                >
                  {{
                    uploadFile
                      ? uploadFile.name
                      : receiptToEdit
                        ? "Click to replace file"
                        : "Click to select file"
                  }}
                </p>
                <p v-if="!uploadFile" class="text-[10px] text-slate-300">
                  JPEG, PNG, or PDF (max 2MB)
                </p>
              </div>
              <div
                class="absolute inset-0 bg-primary/10 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"
              >
                <UploadCloud class="w-10 h-10 text-primary" />
              </div>
              <div
                v-if="receiptsStore.isSaving"
                class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-white/85 text-accent backdrop-blur-[1px]"
              >
                <span
                  class="h-9 w-9 rounded-full border-2 border-current border-t-transparent animate-spin"
                  aria-hidden="true"
                />
                <span class="text-[10px] font-bold uppercase tracking-widest">
                  {{ receiptToEdit ? "Updating receipt..." : "Uploading receipt..." }}
                </span>
              </div>
            </div>
            <input
              ref="uploadFileInput"
              type="file"
              accept=".jpeg,.jpg,.png,.pdf"
              class="hidden"
              @change="handleUploadFileSelect"
            />
            <p class="mt-4 text-[11px] font-mono text-slate-400">
              {{ uploadFile ? uploadFile.name : "No file selected" }}
            </p>
          </div>

          <!-- Right Column: Form Data -->
          <div class="flex-1 p-6 space-y-6">
            <!-- Form Grid -->
            <div class="grid grid-cols-2 gap-4">
              <div class="input-wrapper">
                <label class="input-label"
                  >Invoice Number <span class="text-danger">*</span></label
                >
                <input
                  class="input"
                  type="text"
                  v-model="uploadForm.invoice_number"
                  placeholder="INV-2026-00001"
                />
              </div>
              <div class="input-wrapper">
                <label class="input-label"
                  >Date <span class="text-danger">*</span></label
                >
                <div class="relative">
                  <input
                    class="input"
                    type="date"
                    v-model="uploadForm.transaction_date"
                  />
                </div>
              </div>
            </div>

            <div class="input-wrapper">
              <label class="input-label"
                >TIN Number <span class="text-danger">*</span></label
              >
              <input
                class="input"
                type="text"
                v-model="uploadForm.tin"
                @input="formatTIN"
                placeholder="000-000-000-000"
                maxlength="15"
              />
            </div>

            <div class="input-wrapper">
              <label class="input-label"
                >Vendor Name <span class="text-danger">*</span></label
              >
              <input
                class="input"
                type="text"
                v-model="uploadForm.vendor_name"
                placeholder="Enter vendor name"
              />
            </div>

            <div class="input-wrapper">
              <label class="input-label"
                >Category <span class="text-danger">*</span></label
              >
              <div class="flex gap-3">
                <div class="relative flex-1">
                  <select
                    class="input appearance-none cursor-pointer"
                    v-model="uploadForm.expense_category_id"
                  >
                    <option value="" disabled>Select category</option>
                    <option
                      v-for="cat in categories"
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
              </div>
            </div>

            <div class="input-wrapper">
              <label class="input-label"
                >VAT Classification <span class="text-danger">*</span></label
              >
              <div class="relative">
                <select
                  class="input appearance-none cursor-pointer"
                  v-model="uploadForm.vat_classification"
                >
                  <option value="vat">VAT</option>
                  <option value="non-vat">Non-VAT</option>
                </select>
                <ChevronDown
                  class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                />
              </div>
            </div>

            <!-- Currency -->
            <div class="input-wrapper">
              <label class="input-label">Currency</label>
              <div class="flex gap-3 items-center">
                <div class="flex-1">
                  <CurrencySelect
                    v-model="uploadForm.currency"
                    :disabled="receiptsStore.isSaving"
                  />
                </div>
              </div>
            </div>

            <!-- Totals Inputs Section -->
            <div class="grid grid-cols-2 gap-4">
              <div class="input-wrapper">
                <label class="input-label"
                  >Total Amount (VAT-Inclusive)
                  <span class="text-danger">*</span></label
                >
                <input
                  class="input"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model="uploadForm.total_amount"
                  placeholder="0.00"
                />
              </div>
              <div class="input-wrapper">
                <label
                  class="input-label"
                  :class="{
                    'opacity-50': uploadForm.vat_classification === 'non-vat',
                  }"
                  >VAT Amount (Inclusive)
                  <span
                    v-if="uploadForm.vat_classification === 'vat'"
                    class="text-danger"
                    >*</span
                  ></label
                >
                <input
                  class="input disabled:opacity-70 disabled:cursor-not-allowed disabled:bg-slate-50"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model="uploadForm.vat_amount"
                  placeholder="0.00"
                  disabled
                />
              </div>
            </div>

            <!-- Items Section -->
            <div class="pt-4 border-t border-slate-100">
              <div class="flex items-center justify-between mb-4">
                <label class="input-label !mb-0">Expense Items</label>
                <button
                  type="button"
                  @click="addItem"
                  class="btn btn-secondary !py-1.5 !px-3 !text-xs flex items-center gap-1.5"
                >
                  <Plus class="w-3.5 h-3.5" />
                  Add Item
                </button>
              </div>

              <div class="space-y-3">
                <div
                  v-for="(item, index) in uploadForm.items"
                  :key="index"
                  class="flex gap-3 items-end bg-slate-50 p-3 rounded-lg border border-slate-100"
                >
                  <div class="flex-1 input-wrapper !mb-0">
                    <label class="input-label !text-[10px]"
                      >Item Name <span class="text-danger">*</span></label
                    >
                    <input
                      class="input !py-1.5 !text-sm"
                      type="text"
                      v-model="item.name"
                      placeholder="e.g. Office Supplies"
                    />
                  </div>
                  <div class="w-20 input-wrapper !mb-0">
                    <label class="input-label !text-[10px]"
                      >Qty <span class="text-danger">*</span></label
                    >
                    <input
                      class="input !py-1.5 !text-sm"
                      type="number"
                      min="1"
                      v-model="item.quantity"
                    />
                  </div>
                  <div class="w-28 input-wrapper !mb-0">
                    <label class="input-label !text-[10px]"
                      >Price (Incl. VAT)
                      <span class="text-danger">*</span></label
                    >
                    <input
                      class="input !py-1.5 !text-sm"
                      type="number"
                      step="0.01"
                      min="0"
                      v-model="item.price"
                      placeholder="0.00"
                    />
                  </div>
                  <button
                    type="button"
                    @click="removeItem(index)"
                    class="p-2 text-slate-400 hover:text-danger hover:bg-danger/10 rounded-lg transition-colors mb-[2px]"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
                <div
                  v-if="uploadForm.items.length === 0"
                  class="text-center py-6 border border-dashed border-slate-200 rounded-lg text-slate-400 text-sm"
                >
                  No items added yet.
                </div>
              </div>
            </div>

            <!-- Expense Breakdown / Summary -->
            <div class="pt-4 border-t border-slate-100">
              <label class="input-label mb-4">Expense Summary</label>
              <div
                class="bg-slate-50 rounded-xl p-5 border border-slate-100 space-y-3"
              >
                <div class="flex justify-between text-sm">
                  <span class="text-slate-500">VAT-Exclusive Subtotal</span>
                  <span class="font-mono font-medium text-slate-700">{{
                    formatCurrency(vatExclusiveSubtotal)
                  }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-slate-500">Inclusive VAT Amount</span>
                  <span class="font-mono font-medium text-slate-700">{{
                    formatCurrency(Number(uploadForm.vat_amount) || 0)
                  }}</span>
                </div>
                <div
                  class="pt-3 border-t border-slate-200 flex justify-between items-center"
                >
                  <span class="font-bold text-slate-700">Total Amount</span>
                  <span class="text-2xl font-black text-primary font-mono">{{
                    formatCurrency(Number(uploadForm.total_amount) || 0)
                  }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div
          class="px-6 py-6 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3 sticky bottom-0"
        >
          <button
            @click="close"
            class="btn btn-secondary !px-8"
            :disabled="receiptsStore.isSaving"
          >
            Discard All
          </button>
          <button
            @click="saveReceipt"
            class="btn btn-cta min-h-[42px] w-full sm:w-fit"
            :disabled="receiptsStore.isSaving || !isFormValid"
          >
            <span
              v-if="receiptsStore.isSaving"
              class="h-3.5 w-3.5 rounded-full border-2 border-current border-t-transparent animate-spin"
              aria-hidden="true"
            />
            <Save v-else class="w-4 h-4" />
            {{
              receiptsStore.isSaving
                ? receiptToEdit
                  ? "Updating..."
                  : "Uploading..."
                : "Save Receipt"
            }}
          </button>
        </div>
      </div>
    </div>
  </Transition>

  <ConfirmModal
    :is-open="showConfirmModal"
    title="Unsaved Changes"
    message="You have unsaved changes in your receipt upload. Are you sure you want to discard them?"
    confirm-text="Discard"
    cancel-text="Keep Editing"
    :danger="true"
    @confirm="handleConfirmLeave"
    @close="handleCancelLeave"
  />
</template>
