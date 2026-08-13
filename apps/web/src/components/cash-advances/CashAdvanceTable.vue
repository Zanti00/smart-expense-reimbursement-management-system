<script setup>
import { computed, ref, watch } from "vue";
import {
  ChevronDown,
  ChevronUp,
  ChevronsUpDown,
  Eye,
  Pencil,
  Trash2,
} from "lucide-vue-next";
import { formatPeso } from "@/utils/formatters";
import StatusBadge from "@/components/base/StatusBadge.vue";
import BasePagination from "@/components/base/BasePagination.vue";
import CashAdvanceTableSkeleton from "./CashAdvanceTableSkeleton.vue";
import ActionDropdownMenu from "@/components/base/ActionDropdownMenu.vue";

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

const emit = defineEmits(["view", "edit", "delete"]);

function getActions(row) {
  const status = String(row.status || "").toLowerCase();
  return [
    {
      label: "Edit",
      icon: Pencil,
      visible:
        !props.isAdmin && (status === "pending" || status === "rejected"),
      handler: () => emit("edit", row),
    },
    {
      label: "View",
      icon: Eye,
      visible: true,
      handler: () => emit("view", row),
    },
    {
      label: "Delete",
      icon: Trash2,
      visible: !props.isAdmin && status === "pending",
      variant: "danger",
      handler: () => emit("delete", row),
    },
  ];
}

const sortKey = ref("");
const sortDirection = ref("asc");
const pageSize = 10;
const currentPage = ref(1);

const columns = computed(() => [
  ...(props.isAdmin ? [{ key: "user", label: "User" }] : []),
  { key: "amount", label: "Amount", align: "right" },
  { key: "outstanding", label: "Outstanding", align: "right" },
  { key: "requested", label: "Date Requested" },
  { key: "dueDate", label: "Due Date" },
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

const totalPages = computed(() =>
  Math.max(1, Math.ceil(sortedRows.value.length / pageSize)),
);
const paginatedRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize;
  return sortedRows.value.slice(start, start + pageSize);
});

watch(
  () => props.rows,
  () => {
    currentPage.value = 1;
  },
);

watch(totalPages, (pages) => {
  if (currentPage.value > pages) currentPage.value = pages;
});

function getSortValue(row, key) {
  const value = row[key];
  if (["amount", "outstanding"].includes(key)) return Number(value || 0);
  if (["requested", "dueDate"].includes(key)) {
    const timestamp = new Date(value).getTime();
    return Number.isNaN(timestamp)
      ? String(value || "").toLowerCase()
      : timestamp;
  }
  return String(value || "").toLowerCase();
}

function toggleSort(column) {
  const key = column.sortKey || column.key;
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
    currentPage.value = 1;
    return;
  }
  sortKey.value = key;
  sortDirection.value = "asc";
  currentPage.value = 1;
}

function isSorted(column) {
  return sortKey.value === (column.sortKey || column.key);
}


</script>

<template>
  <section
    class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
  >
    <div
      class="flex flex-col gap-1 px-5 py-4 bg-white border-b border-slate-200 sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <h2
          class="text-base font-bold leading-tight font-heading text-slate-800"
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
        class="w-full text-left border-collapse"
        :class="isAdmin ? 'min-w-[920px]' : 'min-w-[640px]'"
      >
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50">
            <th
              v-for="column in columns"
              :key="column.key"
              class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em]"
              :class="[
                column.align === 'right'
                  ? 'text-right'
                  : column.align === 'center'
                    ? 'text-center'
                    : 'text-left',
                isSorted(column) ? 'text-accent' : 'text-slate-500',
              ]"
            >
              <button
                class="inline-flex items-center gap-1.5 transition-colors hover:text-accent"
                :class="
                  column.align === 'right'
                    ? 'justify-end'
                    : column.align === 'center'
                      ? 'justify-center'
                      : 'justify-start'
                "
                type="button"
                @click="toggleSort(column)"
              >
                <span>{{ column.label }}</span>
                <ChevronUp
                  v-if="isSorted(column) && sortDirection === 'asc'"
                  class="h-3.5 w-3.5"
                />
                <ChevronDown v-else-if="isSorted(column)" class="h-3.5 w-3.5" />
                <ChevronsUpDown v-else class="h-3.5 w-3.5 text-slate-300" />
              </button>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <template v-if="isLoading">
            <CashAdvanceTableSkeleton
              v-for="i in pageSize"
              :key="`skeleton-${i}`"
              :is-admin="isAdmin"
            />
          </template>
          <template v-else-if="sortedRows.length === 0">
            <tr>
              <td
                :colspan="isAdmin ? 7 : 5"
                class="px-5 py-8 text-sm text-center text-slate-500"
              >
                No cash advance records found.
              </td>
            </tr>
          </template>
          <template v-else>
            <tr
              v-for="row in paginatedRows"
              :key="row.id"
              class="transition-colors duration-200 ease-out group whitespace-nowrap hover:bg-slate-50/80"
            >
              <td v-if="isAdmin" class="px-5 py-5">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-slate-700">{{
                    row.user
                  }}</span>
                </div>
              </td>
              <td class="px-5 py-5 text-right text-sm font-bold text-primary">
                {{ formatPeso(row.amount) }}
              </td>
              <td
                class="px-5 py-5 text-right text-sm font-semibold text-slate-600"
              >
                {{ formatPeso(row.outstanding) }}
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">
                {{ row.requested }}
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">
                {{ row.dueDate }}
              </td>
              <td class="px-5 py-5 text-center">
                <StatusBadge :status="row.status" />
              </td>
              <td class="px-5 py-5 text-center">
                <ActionDropdownMenu :actions="getActions(row)" />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <BasePagination
      v-if="!isLoading && sortedRows.length > pageSize"
      v-model:page="currentPage"
      :page-size="pageSize"
      :total="sortedRows.length"
      label="records"
    />
  </section>
</template>
