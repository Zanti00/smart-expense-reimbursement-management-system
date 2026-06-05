<script setup>
import { computed, ref } from "vue";
import { ChevronDown, ChevronUp, ChevronsUpDown, Eye } from "lucide-vue-next";
import { formatPeso } from "@/utils/formatters";
import CashAdvanceTableSkeleton from "./CashAdvanceTableSkeleton.vue";

const props = defineProps({
  rows: {
    type: Array,
    required: true,
  },
  isAdmin: {
    type: Boolean,
    default: false,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["view"]);

const sortKey = ref("");
const sortDirection = ref("asc");

const columns = computed(() => [
  ...(props.isAdmin
    ? [
        { key: "id", label: "ID" },
        { key: "fileDescription", label: "File Description" },
      ]
    : []),
  { key: "purpose", label: "Purpose" },
  { key: "requested", label: "Date Requested" },
  { key: "dueDate", label: "Due Date" },
  { key: "amount", label: "Amount", align: "right" },
  { key: "outstanding", label: "Outstanding", align: "right" },
  ...(props.isAdmin ? [{ key: "user", label: "User" }] : []),
  { key: "status", label: "Status", align: "center" },
  { key: "actions", sortKey: "id", label: "Actions", align: "center" },
]);

const sortedRows = computed(() => {
  const rows = [...props.rows];
  if (!sortKey.value) return rows;

  return rows.sort((a, b) => {
    const aValue = getSortValue(a, sortKey.value);
    const bValue = getSortValue(b, sortKey.value);
    if (aValue === bValue) return 0;
    const result = aValue > bValue ? 1 : -1;
    return sortDirection.value === "asc" ? result : -result;
  });
});

function getSortValue(row, key) {
  const value = row[key];
  if (["amount", "outstanding"].includes(key)) return Number(value || 0);
  if (["requested", "dueDate"].includes(key)) {
    const timestamp = new Date(value).getTime();
    return Number.isNaN(timestamp) ? String(value || "").toLowerCase() : timestamp;
  }
  return String(value || "").toLowerCase();
}

function toggleSort(column) {
  const key = column.sortKey || column.key;
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
    return;
  }
  sortKey.value = key;
  sortDirection.value = "asc";
}

function isSorted(column) {
  return sortKey.value === (column.sortKey || column.key);
}

function statusClass(status) {
  const classes = {
    unliquidated: "bg-amber-50 text-amber-700 border border-amber-200",
    liquidated: "bg-blue-50 text-blue-700 border border-blue-200",
    pending: "bg-yellow-100 text-yellow-800 border border-yellow-200",
    granted: "bg-emerald-50 text-emerald-700 border border-emerald-200",
    approved: "bg-success text-white border border-success",
    disbursed: "bg-[#F0FDFA] text-[#0D9488] border border-teal-100",
    rejected: "bg-[#FEF2F2] text-[#B91C1C] border border-red-200",
  };
  return classes[status] || "bg-slate-100 text-slate-600";
}
</script>

<template>
  <section
    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
  >
    <div
      class="flex flex-col gap-1 border-b border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <h2
          class="font-heading text-base font-bold leading-tight text-slate-800"
        >
          Cash Advance {{ isAdmin ? "Management" : "Requests" }}
        </h2>
        <p class="mt-0.5 text-xs text-slate-400">
          {{
            isAdmin
              ? "Administrative review queue"
              : "Your cash advance records"
          }}
        </p>
      </div>
      <span class="kpi-label text-slate-400">
        <template v-if="isLoading">Loading...</template>
        <template v-else>Showing {{ sortedRows.length }} records</template>
      </span>
    </div>
    <div class="overflow-x-auto">
      <table
        class="w-full border-collapse text-left"
        :class="isAdmin ? 'min-w-[1180px]' : 'min-w-[880px]'"
      >
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50">
            <th
              v-for="column in columns"
              :key="column.key"
              class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em]"
              :class="[
                column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                isSorted(column) ? 'text-accent' : 'text-slate-500',
              ]"
            >
              <button
                class="inline-flex items-center gap-1.5 transition-colors hover:text-accent"
                :class="column.align === 'right' ? 'justify-end' : column.align === 'center' ? 'justify-center' : 'justify-start'"
                type="button"
                @click="toggleSort(column)"
              >
                <span>{{ column.label }}</span>
                <ChevronUp v-if="isSorted(column) && sortDirection === 'asc'" class="h-3.5 w-3.5" />
                <ChevronDown v-else-if="isSorted(column)" class="h-3.5 w-3.5" />
                <ChevronsUpDown v-else class="h-3.5 w-3.5 text-slate-300" />
              </button>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <template v-if="isLoading">
            <CashAdvanceTableSkeleton
              v-for="i in 5"
              :key="`skeleton-${i}`"
              :is-admin="isAdmin"
            />
          </template>
          <template v-else-if="sortedRows.length === 0">
            <tr>
              <td
                :colspan="isAdmin ? 10 : 7"
                class="px-5 py-8 text-center text-sm text-slate-500"
              >
                No cash advance records found.
              </td>
            </tr>
          </template>
          <template v-else>
            <tr
              v-for="row in sortedRows"
              :key="row.id"
              class="group whitespace-nowrap transition-colors duration-200 ease-out hover:bg-slate-50/80"
            >
              <td
                v-if="isAdmin"
                class="px-5 py-5 font-mono text-sm font-bold text-slate-900"
              >
                {{ row.id }}
              </td>
              <td
                v-if="isAdmin"
                class="max-w-[170px] px-5 py-5 text-sm text-slate-500"
              >
                <span class="block truncate">{{ row.fileDescription }}</span>
              </td>
              <td class="max-w-[220px] px-5 py-5 text-sm text-slate-600">
                <span class="block truncate">{{ row.purpose }}</span>
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">
                {{ row.requested }}
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">
                {{ row.dueDate }}
              </td>
              <td class="px-5 py-5 text-right text-sm font-bold text-primary">
                {{ formatPeso(row.amount) }}
              </td>
              <td
                class="px-5 py-5 text-right text-sm font-semibold text-slate-600"
              >
                {{ formatPeso(row.outstanding) }}
              </td>
              <td v-if="isAdmin" class="px-5 py-5">
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary"
                    >{{ row.initials }}</span
                  >
                  <span class="text-sm font-medium text-slate-700">{{
                    row.user
                  }}</span>
                </div>
              </td>
              <td class="px-5 py-5 text-center">
                <span
                  :class="[
                    'inline-flex rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
                    statusClass(row.status),
                  ]"
                  >{{ row.status }}</span
                >
              </td>
              <td class="px-5 py-5 text-center">
                <button
                  class="inline-flex min-h-9 items-center justify-center gap-2 rounded-lg border border-accent/15 bg-accent/5 px-3 text-xs font-bold text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.02] focus:outline-none"
                  title="View cash advance"
                  @click="$emit('view', row)"
                >
                  <Eye class="h-3.5 w-3.5" />
                  <span>View</span>
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </section>
</template>
