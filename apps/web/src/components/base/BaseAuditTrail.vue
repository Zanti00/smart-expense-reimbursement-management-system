<template>
  <div class="rounded-xl border border-slate-200 bg-white overflow-hidden text-slate-900 shadow-xs font-sans">
    <!-- Header -->
    <div
      class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-slate-50 cursor-pointer select-none transition-colors hover:bg-slate-100/70"
      @click="isOpen = !isOpen"
    >
      <div class="flex items-center gap-2">
        <History class="w-4 h-4 text-slate-500 shrink-0" />
        <h4 class="text-sm font-semibold text-slate-800 tracking-tight">
          Audit Trail ({{ trail.length }})
        </h4>
      </div>

      <div class="flex items-center flex-wrap gap-3" @click.stop>
        <!-- Download Log Links -->
        <div
          v-if="trail.length > 0"
          data-testid="audit-trail-download-group"
          class="flex items-center gap-1.5 text-xs text-slate-500"
        >
          <span class="font-medium text-slate-600">Download Log:</span>
          <button
            type="button"
            data-testid="audit-export-xlsx-btn"
            class="font-semibold text-sky-600 hover:underline hover:text-sky-700 transition-colors cursor-pointer"
            @click="handleExport('xlsx')"
            title="Download as Excel (.xlsx)"
          >
            XLSX
          </button>
          <span>/</span>
          <button
            type="button"
            data-testid="audit-export-csv-btn"
            class="font-semibold text-sky-600 hover:underline hover:text-sky-700 transition-colors cursor-pointer"
            @click="handleExport('csv')"
            title="Download as CSV (.csv)"
          >
            CSV
          </button>
          <span>/</span>
          <button
            type="button"
            data-testid="audit-export-pdf-btn"
            class="font-semibold text-sky-600 hover:underline hover:text-sky-700 transition-colors cursor-pointer"
            @click="handleExport('pdf')"
            title="Download as PDF (.pdf)"
          >
            PDF
          </button>
          <span>/</span>
          <button
            type="button"
            data-testid="audit-export-json-btn"
            class="font-semibold text-sky-600 hover:underline hover:text-sky-700 transition-colors cursor-pointer"
            @click="handleExport('json')"
            title="Download as JSON (.json)"
          >
            JSON
          </button>
        </div>

        <div v-if="trail.length > 0" class="h-4 w-px bg-slate-200 hidden sm:block" />

        <!-- Sort Toggle Button -->
        <button
          v-if="trail.length > 1"
          type="button"
          data-testid="audit-trail-sort-btn"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md border border-slate-200 hover:bg-slate-100 text-slate-600 transition-colors"
          @click="toggleSort"
          title="Toggle sort direction"
        >
          <ArrowUpDown class="w-3.5 h-3.5" />
          <span>{{ sortOrder === 'latest' ? 'Latest First ↓' : 'Oldest First ↑' }}</span>
        </button>

        <!-- Collapse/Expand Toggle -->
        <button
          type="button"
          data-testid="audit-trail-collapse-btn"
          class="p-1 rounded-md hover:bg-slate-200/60 text-slate-500 transition-colors"
          @click="isOpen = !isOpen"
          :aria-expanded="isOpen"
          aria-label="Toggle audit trail visibility"
        >
          <ChevronUp v-if="isOpen" class="w-4 h-4" />
          <ChevronDown v-else class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Body / Table -->
    <div v-if="isOpen" data-testid="audit-trail-body" class="overflow-x-auto bg-white">
      <!-- Empty State -->
      <div
        v-if="!trail || trail.length === 0"
        class="py-8 text-center text-xs text-slate-400 border-t border-slate-200"
      >
        No audit trail records found for this request.
      </div>

      <!-- Table -->
      <table v-else class="w-full text-xs text-left border-collapse bg-white">
        <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 text-[11px] uppercase tracking-wider">
          <tr>
            <th class="px-4 py-2.5 w-[190px]">Time</th>
            <th class="px-4 py-2.5 w-[200px]">User</th>
            <th class="px-4 py-2.5">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <tr
            v-for="entry in visibleTrail"
            :key="entry.id"
            data-testid="audit-event-item"
            class="hover:bg-slate-50/80 transition-colors"
          >
            <!-- Time Column (Poppins / font-sans with tabular-nums for aligned digits) -->
            <td class="px-4 py-2.5 text-xs text-slate-500 font-sans tabular-nums whitespace-nowrap align-top">
              {{ formatDateTime(entry.created_at) }}
            </td>

            <!-- User Column -->
            <td class="px-4 py-2.5 text-xs align-top">
              <div class="flex flex-col font-sans">
                <div class="flex items-center gap-1 flex-wrap">
                  <span class="font-semibold text-slate-900">
                    {{ entry.actor_name || 'System' }}
                  </span>
                  <span
                    v-if="entry.actor_role"
                    class="text-[11px] text-slate-500"
                  >
                    ({{ entry.actor_role }})
                  </span>
                </div>
                <!-- IP Address Badge (Admin Only) -->
                <div
                  v-if="entry.ip_address"
                  class="inline-flex items-center gap-1 text-[11px] text-slate-400 font-sans tabular-nums mt-0.5"
                  title="Recorded IP Address"
                >
                  <Globe class="w-2.5 h-2.5" />
                  <span>{{ entry.ip_address }}</span>
                </div>
              </div>
            </td>

            <!-- Action Column -->
            <td class="px-4 py-2.5 text-xs align-top font-sans">
              <div class="flex flex-col gap-1">
                <div class="flex items-center flex-wrap gap-2">
                  <!-- Domain badge (only shown when linked across multiple modules) -->
                  <span
                    v-if="shouldShowDomainBadge(entry)"
                    data-testid="audit-domain-badge"
                    class="text-[10px] uppercase font-semibold text-slate-400 tracking-wider"
                  >
                    [{{ entry.domain_label || entry.domain }}]
                  </span>

                  <!-- Action pill / outline -->
                  <span
                    class="px-1.5 py-0.5 text-xs font-semibold rounded border inline-block"
                    :class="getActionBadgeClasses(entry.tone)"
                  >
                    {{ entry.label || entry.action_type }}
                  </span>

                  <!-- Comment text inline if single-line -->
                  <span
                    v-if="entry.comment && !entry.comment.includes('\n')"
                    class="text-xs text-slate-600 leading-normal"
                  >
                    {{ entry.comment }}
                  </span>
                </div>

                <!-- Multi-line comment text -->
                <div
                  v-if="entry.comment && entry.comment.includes('\n')"
                  class="text-xs text-slate-600 whitespace-pre-wrap pl-2 border-l border-slate-200 mt-1 leading-relaxed"
                >
                  {{ entry.comment }}
                </div>

                <!-- Changes Diff (if present) -->
                <div
                  v-if="entry.changes && entry.changes.length > 0"
                  class="text-xs text-slate-500 space-y-0.5 mt-0.5"
                >
                  <div
                    v-for="(change, idx) in entry.changes"
                    :key="idx"
                    class="text-xs flex items-center gap-1.5"
                  >
                    <span class="font-semibold text-slate-700">{{ change.field || change.key }}:</span>
                    <span class="line-through text-slate-400 tabular-nums">{{ change.before }}</span>
                    <ArrowRight class="w-3 h-3 text-slate-400 shrink-0" />
                    <span class="font-medium text-slate-900 tabular-nums">{{ change.after }}</span>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Cap Expand/Collapse Toggle Button -->
      <div
        v-if="sortedTrail.length > cap"
        class="border-t border-slate-200 px-4 py-2 text-center bg-slate-50/50"
      >
        <button
          type="button"
          data-testid="audit-trail-expand-cap-btn"
          class="text-xs font-semibold text-primary hover:underline cursor-pointer"
          @click="isExpandedCap = !isExpandedCap"
        >
          {{ isExpandedCap ? 'Show less' : `Show all ${sortedTrail.length} events` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import {
  History,
  ChevronDown,
  ChevronUp,
  ArrowUpDown,
  Globe,
  ArrowRight,
} from "lucide-vue-next";
import { formatDateTime } from "@/utils/formatters";
import { exportAuditTrail } from "@/utils/auditTrailExport";

const props = defineProps({
  trail: {
    type: Array,
    default: () => [],
  },
  initialOpen: {
    type: Boolean,
    default: true,
  },
  cap: {
    type: Number,
    default: 4,
  },
  entityTitle: {
    type: String,
    default: "",
  },
  showDomainBadge: {
    type: Boolean,
    default: null,
  },
});

const isOpen = ref(props.initialOpen);
const sortOrder = ref("latest"); // 'latest' | 'oldest'
const isExpandedCap = ref(false);

const toggleSort = () => {
  sortOrder.value = sortOrder.value === "latest" ? "oldest" : "latest";
};

const hasMultipleDomains = computed(() => {
  if (!props.trail || !props.trail.length) return false;
  const domains = new Set(
    props.trail
      .map((e) => (e.domain_label || e.domain || "").toLowerCase().trim())
      .filter(Boolean),
  );
  return domains.size > 1;
});

const shouldShowDomainBadge = (entry) => {
  if (props.showDomainBadge === false) return false;
  if (props.showDomainBadge === true) return Boolean(entry.domain_label || entry.domain);
  return hasMultipleDomains.value && Boolean(entry.domain_label || entry.domain);
};

const handleExport = (format) => {
  exportAuditTrail(props.trail, format, {
    entityTitle: props.entityTitle,
    title: props.entityTitle ? `Audit Log - ${props.entityTitle}` : "Audit Trail Log",
  });
};

const sortedTrail = computed(() => {
  if (!props.trail || !props.trail.length) return [];
  const list = [...props.trail];
  return list.sort((a, b) => {
    const timeA = a.created_at ? new Date(a.created_at).getTime() : 0;
    const timeB = b.created_at ? new Date(b.created_at).getTime() : 0;
    return sortOrder.value === "latest" ? timeB - timeA : timeA - timeB;
  });
});

const visibleTrail = computed(() => {
  if (isExpandedCap.value || sortedTrail.value.length <= props.cap) {
    return sortedTrail.value;
  }
  return sortedTrail.value.slice(0, props.cap);
});

const getActionBadgeClasses = (tone) => {
  switch (tone) {
    case "success":
      return "border-emerald-500/70 text-emerald-700 bg-emerald-50/60";
    case "warning":
      return "border-amber-500/70 text-amber-700 bg-amber-50/60";
    case "danger":
      return "border-rose-500/70 text-rose-700 bg-rose-50/60";
    case "info":
    default:
      return "border-slate-300 text-slate-700 bg-slate-50";
  }
};
</script>
