<script setup>
import { ref, reactive } from "vue";
import { useRouter } from "vue-router";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useToast } from "@/composables/useToast";
import ToastNotification from "@/components/ToastNotification.vue";
import {
  X,
  FileText,
  UploadCloud,
  Info,
  Send,
  Calendar,
} from "lucide-vue-next";

const router = useRouter();
const store = useCashAdvanceStore();
const { addToast } = useToast();

const submitting = ref(false);
const form = reactive({
  purpose: "",
  amount: "",
  expected_disbursement_date: "",
  expected_liquidation_date: "",
  documents: [],
});
const fileInput = ref(null);

function handleFileUpload(event) {
  const files = Array.from(event.target.files);

  if (form.documents.length + files.length > 5) {
    addToast({ message: "Maximum of 5 files allowed.", type: "error" });
    if (fileInput.value) fileInput.value.value = "";
    return;
  }

  for (const file of files) {
    if (file.size > 2 * 1024 * 1024) {
      addToast({
        message: `File ${file.name} exceeds the 2MB size limit.`,
        type: "error",
      });
      if (fileInput.value) fileInput.value.value = "";
      return;
    }

    const validTypes = [
      "application/pdf",
      "application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "image/jpeg",
      "image/png",
      "image/jpg",
    ];

    if (!validTypes.includes(file.type)) {
      addToast({
        message: `Invalid file type: ${file.name}. Must be PDF, DOC, DOCX, XLSX, or Image.`,
        type: "error",
      });
      if (fileInput.value) fileInput.value.value = "";
      return;
    }
  }

  for (const file of files) {
    if (file.type.startsWith("image/")) {
      file.previewUrl = URL.createObjectURL(file);
    }
    form.documents.push(file);
  }

  if (fileInput.value) fileInput.value.value = "";
}

function removeFile(index) {
  const file = form.documents[index];
  if (file.previewUrl) {
    URL.revokeObjectURL(file.previewUrl);
  }
  form.documents.splice(index, 1);
}

async function handleRequest() {
  if (
    !form.purpose ||
    !form.amount ||
    !form.expected_disbursement_date ||
    !form.expected_liquidation_date ||
    form.documents.length === 0
  )
    return;

  const today = new Date().toISOString().split("T")[0];
  if (form.expected_liquidation_date < today) {
    addToast({
      message: "Liquidation Deadline must be today or a future date.",
      type: "error",
    });
    return;
  }

  if (form.expected_disbursement_date >= form.expected_liquidation_date) {
    addToast({
      message: "Disbursement date cannot be greater than or equal to the liquidation date.",
      type: "error",
    });
    return;
  }

  submitting.value = true;

  const formData = new FormData();
  formData.append("purpose", form.purpose);
  formData.append("amount", form.amount);
  formData.append("expected_disbursement_date", form.expected_disbursement_date);
  formData.append("expected_liquidation_date", form.expected_liquidation_date);
  form.documents.forEach((file) => formData.append("documents[]", file));

  try {
    await store.request(formData);
    addToast({
      message: "Cash advance requested successfully",
      type: "success",
    });
    router.push("/cash-advances");
  } catch (error) {
    addToast({ message: error.message || "Failed to create request", type: "error" });
  } finally {
    submitting.value = false;
  }
}

function goBack() {
  router.push("/cash-advances");
}
</script>

<template>
  <ToastNotification />
  <div class="flex flex-col bg-clinical min-h-full">
    <header
      class="sticky top-0 z-10 flex flex-shrink-0 items-center gap-3 px-6 py-4 text-white shadow-sm"
      style="background: linear-gradient(135deg, #252578 0%, #2f2f7e 100%)"
    >
      <button
        class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
        type="button"
        title="Back to Cash Advances"
        @click="goBack"
      >
        <X class="h-4 w-4" />
      </button>
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-widest text-white/60">
          Cash Advances
        </p>
        <h2 class="font-heading text-sm font-bold leading-tight text-white">
          New Cash Advance Request
        </h2>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto">
      <div class="mx-auto flex w-full max-w-5xl flex-col gap-8 p-6">
        <section class="card p-5 md:p-6">
          <form id="cashAdvanceForm" class="space-y-6" @submit.prevent="handleRequest">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
              <div class="input-wrapper">
                <label class="input-label" for="ca-amount">Amount Requested *</label>
                <div class="relative">
                  <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-mono text-slate-400">PHP</span>
                  <input
                    id="ca-amount"
                    v-model="form.amount"
                    class="input !pl-14 text-base"
                    min="0"
                    placeholder="0.00"
                    type="number"
                  />
                </div>
              </div>

              <div class="input-wrapper">
                <label class="input-label" for="ca-disbursement">Disbursement Date *</label>
                <div class="relative">
                  <input
                    id="ca-disbursement"
                    v-model="form.expected_disbursement_date"
                    class="input !pr-12 text-base"
                    type="date"
                  />
                  <Calendar class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
              </div>

              <div class="input-wrapper">
                <label class="input-label" for="ca-due">Liquidation Deadline *</label>
                <div class="relative">
                  <input
                    id="ca-due"
                    v-model="form.expected_liquidation_date"
                    class="input !pr-12 text-base"
                    type="date"
                  />
                  <Calendar class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
              </div>
            </div>

            <div class="input-wrapper">
              <label class="input-label" for="ca-purpose">Purpose *</label>
              <textarea
                id="ca-purpose"
                v-model="form.purpose"
                class="input min-h-[132px] resize-none text-base leading-relaxed"
                placeholder="Describe the purpose of this cash advance request..."
                rows="4"
              />
            </div>

            <div class="input-wrapper">
              <label class="input-label">Request Documents (Max 5) *</label>
              <input
                type="file"
                ref="fileInput"
                @change="handleFileUpload"
                class="hidden"
                accept=".pdf,.doc,.docx,.xlsx,image/*"
                multiple
              />

              <div v-if="form.documents.length > 0" class="flex flex-col gap-3 mb-4">
                <div
                  v-for="(file, index) in form.documents"
                  :key="index"
                  class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-3"
                >
                  <div class="flex items-center gap-3">
                    <img
                      v-if="file.previewUrl"
                      :src="file.previewUrl"
                      class="h-10 w-10 rounded object-cover"
                    />
                    <div v-else class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                      <FileText class="h-5 w-5" />
                    </div>
                    <div class="flex flex-col">
                      <span class="text-sm font-bold text-slate-800">{{ file.name }}</span>
                      <span class="text-xs font-semibold text-slate-400">{{ (file.size / 1024 / 1024).toFixed(2) }} MB</span>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="removeFile(index)"
                    class="text-danger hover:bg-red-50 p-2 rounded-full transition-colors"
                  >
                    <X class="h-4 w-4" />
                  </button>
                </div>
              </div>

              <button
                v-if="form.documents.length < 5"
                class="group flex w-full flex-col items-center justify-between gap-4 rounded-lg border-2 border-dashed border-slate-200 bg-slate-50/80 p-6 text-left transition-all duration-200 ease-out hover:border-accent/30 hover:bg-accent-50/50 md:flex-row"
                type="button"
                @click="fileInput.click()"
              >
                <div class="flex flex-col items-center gap-4 md:flex-row">
                  <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm transition-colors group-hover:text-accent">
                    <UploadCloud class="h-6 w-6" />
                  </div>
                  <div class="text-center md:text-left">
                    <h3 class="font-heading text-base font-bold text-slate-800">Select files</h3>
                    <p class="text-sm text-slate-500">Upload up to 5 request documents (PDF, DOC, DOCX, XLSX, Images)</p>
                  </div>
                </div>
                <span class="rounded-md border border-black/5 bg-white px-5 py-2 text-sm font-bold text-primary shadow-sm transition-colors group-hover:border-accent/20 group-hover:text-accent">
                  Browse
                </span>
              </button>
              <p
                class="mt-1 flex items-center gap-1 text-xs font-semibold"
                :class="form.documents.length > 0 ? 'text-success' : 'text-danger'"
              >
                <Info class="h-3.5 w-3.5" />
                At least 1 request document is required to process your cash advance.
              </p>
            </div>
          </form>
        </section>

        <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div class="rounded-lg border border-accent/20 bg-accent-50 p-5">
            <div class="mb-4 flex items-start gap-3">
              <Info class="mt-0.5 h-5 w-5 text-accent" />
              <h3 class="font-heading text-base font-bold text-accent-800">
                Important Information
              </h3>
            </div>
            <ul class="list-inside list-disc space-y-2 text-sm leading-relaxed text-accent-800">
              <li>Cash advance requests are subject to approval by the accounting department</li>
              <li>Approved amounts will be disbursed within 3-5 business days</li>
              <li>You must submit reimbursement with receipts after using the cash advance</li>
              <li>Unused amounts must be returned to the company</li>
            </ul>
          </div>
        </section>

        <footer class="flex flex-col items-center justify-end gap-3 border-t border-black/5 pt-6 sm:flex-row">
          <button class="btn btn-secondary w-full px-8 py-3 sm:w-auto" type="button" @click="goBack">
            Cancel
          </button>
          <button
            id="submit-advance-btn"
            class="btn btn-primary w-full px-8 py-3 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="
              submitting ||
              !form.purpose ||
              !form.amount ||
              !form.expected_disbursement_date ||
              !form.expected_liquidation_date ||
              form.documents.length === 0
            "
            form="cashAdvanceForm"
            type="submit"
          >
            <Send class="h-4 w-4" />
            {{ submitting ? "Submitting..." : "Submit Request" }}
          </button>
        </footer>
      </div>
    </main>
  </div>
</template>
