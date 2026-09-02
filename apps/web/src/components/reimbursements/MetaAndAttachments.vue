<script setup>
import { ref, computed } from "vue";
import { ChevronDown, FileText, UploadCloud, X } from "lucide-vue-next";
import { useToast } from "@/composables/useToast";
import { formatCutoffPeriod } from "@/utils/formatters";

const props = defineProps({
  cutoffPeriod: {
    type: String,
    required: true,
  },
  reportFile: {
    type: [File, String, Object],
    default: null,
  },
});

const emit = defineEmits(["update:cutoffPeriod", "update:reportFile"]);

const DEFAULT_CUTOFF_OPTIONS = [
  "Jun 01 - Jun 15, 2026",
  "Jun 16 - Jun 30, 2026",
  "Jul 01 - Jul 15, 2026",
  "Jul 16 - Jul 31, 2026",
  "Aug 01 - Aug 15, 2026",
  "Aug 16 - Aug 31, 2026",
  "Sep 01 - Sep 15, 2026",
  "Sep 16 - Sep 30, 2026",
  "Oct 01 - Oct 15, 2026",
  "Oct 16 - Oct 31, 2026",
  "Nov 01 - Nov 15, 2026",
  "Nov 16 - Nov 30, 2026",
  "Dec 01 - Dec 15, 2026",
  "Dec 16 - Dec 31, 2026",
];

const cutoffOptions = computed(() => {
  const options = [...DEFAULT_CUTOFF_OPTIONS];
  if (props.cutoffPeriod && !options.includes(props.cutoffPeriod)) {
    options.unshift(props.cutoffPeriod);
  }
  return options;
});

const reportDrag = ref(false);
const reportInput = ref(null);
const { addToast } = useToast();

const VALID_MIMES = [
  "application/pdf",
  "application/msword",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
];

function isValidFile(file) {
  if (!file) return false;
  if (!VALID_MIMES.includes(file.type)) {
    addToast({
      message:
        "Invalid file type. Only PDF, DOC, and DOCX are allowed for the report.",
      type: "error",
    });
    return false;
  }
  return true;
}

function handleReportDrop(e) {
  reportDrag.value = false;
  const file = e.dataTransfer.files[0];
  if (file && isValidFile(file)) emit("update:reportFile", file);
}

function handleReportSelect(e) {
  const file = e.target.files[0];
  if (file && isValidFile(file)) emit("update:reportFile", file);
  // Reset input value so same file can be selected again if needed
  if (e.target) e.target.value = "";
}

function reportFileName(file) {
  if (!file) return "";
  if (typeof file === "string") return file.split("/").pop() || "Attached Report";
  return file.name || "Attached Report";
}

function reportFileSize(file) {
  if (!file || typeof file === "string" || !file.size) return "";
  return `${(file.size / 1024 / 1024).toFixed(2)} MB`;
}

function removeReportFile() {
  emit("update:reportFile", null);
}
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Cutoff Period -->
    <section class="card p-6 flex flex-col gap-4">
      <div>
        <h3
          class="text-base font-bold text-primary mb-1"
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
            <option v-for="opt in cutoffOptions" :key="opt" :value="opt">
              {{ formatCutoffPeriod(opt) !== "—" ? formatCutoffPeriod(opt) : opt }}
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
      >
        Report Attachment
        <span class="text-danger">*</span>
      </h3>

      <div v-if="reportFile" class="flex flex-col gap-3">
        <div
          class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-3"
        >
          <div class="flex min-w-0 items-center gap-3">
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400"
            >
              <FileText class="h-5 w-5" />
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-bold text-slate-800">
                {{ reportFileName(reportFile) }}
              </p>
              <p v-if="reportFileSize(reportFile)" class="text-xs font-semibold text-slate-400">
                {{ reportFileSize(reportFile) }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="rounded-full p-2 text-danger transition-colors hover:bg-red-50"
            aria-label="Remove report attachment"
            @click="removeReportFile"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div
        class="border-2 border-dashed rounded-xl p-5 flex flex-col gap-4 transition-all cursor-pointer sm:flex-row sm:items-center sm:justify-between"
        :class="
          reportDrag
            ? 'border-accent bg-accent/5'
            : 'border-slate-200 hover:border-primary/30 bg-slate-50/40'
        "
        @dragover.prevent="reportDrag = true"
        @dragleave.prevent="reportDrag = false"
        @drop.prevent="handleReportDrop"
      >
        <div class="flex items-center gap-4 min-w-0 flex-1">
          <div
            class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0"
          >
            <UploadCloud class="w-5 h-5 text-slate-400" />
          </div>
          <div class="min-w-0 flex-1">
            <p
              class="text-sm truncate font-semibold text-slate-700"
            >
              Select file
            </p>
            <p class="text-[11px] text-slate-400">
              Upload activity report (PDF, DOC, DOCX)
            </p>
          </div>
        </div>
        <button
          class="btn btn-secondary min-h-10 w-full !px-5 !text-xs flex-shrink-0 sm:w-fit"
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
