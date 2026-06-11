<script setup>
import { ref } from "vue";
import { ChevronDown, FileText } from "lucide-vue-next";

defineProps({
  cutoffPeriod: {
    type: String,
    required: true,
  },
  reportFile: {
    type: File,
    default: null,
  },
});

const emit = defineEmits(["update:cutoffPeriod", "update:reportFile"]);

const CUTOFF_OPTIONS = [
  "Jan 01 - Jan 15, 2025",
  "Jan 16 - Jan 31, 2025",
  "Feb 01 - Feb 15, 2025",
  "Feb 16 - Feb 28, 2025",
  "Mar 01 - Mar 15, 2025",
  "Mar 16 - Mar 31, 2025",
];

const reportDrag = ref(false);
const reportInput = ref(null);

function handleReportDrop(e) {
  reportDrag.value = false;
  const file = e.dataTransfer.files[0];
  if (file) emit("update:reportFile", file);
}

function handleReportSelect(e) {
  const file = e.target.files[0];
  if (file) emit("update:reportFile", file);
  // Reset input value so same file can be selected again if needed
  if (e.target) e.target.value = "";
}
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Cutoff Period -->
    <section class="card p-6 flex flex-col gap-4">
      <div>
        <h3
          class="text-base font-bold text-primary mb-1"
          style="font-family: 'Poppins', sans-serif"
        >
          Cutoff Period <span class="text-danger">*</span>
        </h3>
        <div class="relative">
          <select
            :value="cutoffPeriod"
            @change="$emit('update:cutoffPeriod', $event.target.value)"
            class="input appearance-none cursor-pointer bg-white pr-10"
            :class="cutoffPeriod ? 'text-slate-700' : 'text-slate-400'"
          >
            <option value="" disabled>Select cutoff period</option>
            <option v-for="opt in CUTOFF_OPTIONS" :key="opt" :value="opt">
              {{ opt }}
            </option>
          </select>
          <ChevronDown
            class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
          />
        </div>
        <p class="text-[11px] text-slate-400 mt-2">
          You can submit one reimbursement per cutoff period.
        </p>
      </div>
    </section>

    <!-- Report Attachment -->
    <section class="card p-6 flex flex-col gap-4">
      <h3
        class="text-base font-bold text-primary"
        style="font-family: 'Poppins', sans-serif"
      >
        Report Attachment
        <span class="text-danger">*</span>
      </h3>
      <div
        class="border-2 border-dashed rounded-xl p-5 flex items-center justify-between transition-all cursor-pointer"
        :class="
          reportDrag
            ? 'border-accent bg-accent/5'
            : 'border-slate-200 hover:border-primary/30 bg-slate-50/40'
        "
        @dragover.prevent="reportDrag = true"
        @dragleave.prevent="reportDrag = false"
        @drop.prevent="handleReportDrop"
      >
        <div class="flex items-center gap-4">
          <div
            class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0"
          >
            <FileText class="w-5 h-5 text-slate-400" />
          </div>
          <div>
            <p
              class="text-sm font-semibold text-slate-700"
              style="font-family: 'Poppins', sans-serif"
            >
              {{ reportFile ? reportFile.name : "No file selected" }}
            </p>
            <p class="text-[11px] text-slate-400">
              Upload activity report (PDF, DOC, DOCX)
            </p>
          </div>
        </div>
        <button
          class="btn btn-secondary !py-1.5 !px-5 !text-xs flex-shrink-0"
          @click="reportInput?.click()"
          type="button"
        >
          Browse
        </button>
        <input
          ref="reportInput"
          type="file"
          class="hidden"
          accept=".pdf,.doc,.docx"
          @change="handleReportSelect"
        />
      </div>
    </section>
  </div>
</template>
