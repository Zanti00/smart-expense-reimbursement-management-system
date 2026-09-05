<script setup>
import { computed, ref, watch } from "vue";
import {
  ChevronUp,
  ChevronDown,
  ChevronsUpDown,
  PanelLeftClose,
  PanelLeftOpen,
} from "lucide-vue-next";
import SkeletonLoader from "@/components/base/SkeletonLoader.vue";
import StatusBadge from "@/components/base/StatusBadge.vue";
import BasePagination from "@/components/base/BasePagination.vue";
import { formatPeso } from "@/utils/formatters";

const props = defineProps({
  sortOptions: {
    type: Array,
    required: true,
  },
  sortKey: {
    type: String,
    required: true,
  },
  sortDirection: {
    type: String,
    required: true,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  advances: {
    type: Array,
    default: () => [],
  },
  selectedAdvance: {
    type: Object,
    default: null,
  },
  getBadgeStatus: {
    type: Function,
    required: true,
  },
  calculateAging: {
    type: Function,
    required: true,
  },
  collapsed: {
    type: Boolean,
    default: false,
  },
  pageSize: {
    type: Number,
    default: 8,
  },
});

const emit = defineEmits([
  "update:sortKey",
  "update:sortDirection",
  "select",
  "toggle-collapse",
]);

const currentPage = ref(1);

const totalPages = computed(() =>
  Math.max(1, Math.ceil((props.advances?.length || 0) / props.pageSize)),
);

const paginatedAdvances = computed(() => {
  const list = props.advances || [];
  const start = (currentPage.value - 1) * props.pageSize;
  return list.slice(start, start + props.pageSize);
});

watch(
  () => props.advances?.length,
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = Math.max(1, totalPages.value);
    }
  },
);

function handleSortClick(optionValue) {
  if (props.sortKey === optionValue) {
    emit(
      "update:sortDirection",
      props.sortDirection === "asc" ? "desc" : "asc",
    );
  } else {
    emit("update:sortKey", optionValue);
    emit("update:sortDirection", optionValue === "date" ? "desc" : "asc");
  }
}
</script>

<template>
  <div
    class="flex flex-col gap-3"
    :class="collapsed ? 'xl:col-span-1' : 'xl:col-span-2'"
  >
    <!-- Collapse Toggle -->
    <div class="hidden xl:flex items-center justify-between">
      <span v-if="!collapsed" class="text-xs text-slate-400"
        >{{ advances.length }} advances</span
      >
      <button
        class="p-1.5 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
        :title="collapsed ? 'Expand panel' : 'Collapse panel'"
        @click="$emit('toggle-collapse')"
      >
        <PanelLeftOpen v-if="collapsed" class="w-4 h-4" />
        <PanelLeftClose v-else class="w-4 h-4" />
      </button>
    </div>

    <!-- Collapsed State -->
    <template v-if="collapsed">
      <div
        v-for="adv in paginatedAdvances"
        :key="adv.id"
        :class="[
          'card px-3 py-2.5 cursor-pointer border',
          selectedAdvance?.id === adv.id
            ? 'border-primary bg-primary/[0.02]'
            : 'border-slate-200',
        ]"
        @click="$emit('select', adv)"
        :title="adv.purpose + ' — ' + formatPeso(adv.balance || 0)"
      >
        <div class="flex items-center justify-between gap-2">
          <p class="text-xs font-medium truncate text-slate-700 max-w-[80px]">
            {{ adv.purpose }}
          </p>
          <StatusBadge :status="getBadgeStatus(adv)" />
        </div>
        <p class="text-sm font-semibold text-primary mt-1">
          {{ formatPeso(adv.balance || 0) }}
        </p>
      </div>

      <!-- Collapsed Pagination Controls -->
      <div
        v-if="!isLoading && advances.length > pageSize"
        class="flex items-center justify-between gap-1 pt-1"
      >
        <button
          class="btn btn-secondary btn-sm px-2 py-1 text-xs"
          :disabled="currentPage === 1"
          type="button"
          @click="currentPage = Math.max(1, currentPage - 1)"
        >
          Prev
        </button>
        <span class="text-[10px] font-bold text-slate-500">
          {{ currentPage }} / {{ totalPages }}
        </span>
        <button
          class="btn btn-secondary btn-sm px-2 py-1 text-xs"
          :disabled="currentPage === totalPages"
          type="button"
          @click="currentPage = Math.min(totalPages, currentPage + 1)"
        >
          Next
        </button>
      </div>
    </template>

    <!-- Expanded State -->
    <template v-else>
      <div
        class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
      >
        <div
          class="grid grid-cols-[minmax(0,1fr)_6.5rem_6rem] border-b border-slate-200 bg-slate-50"
        >
          <button
            v-for="option in sortOptions"
            :key="option.value"
            class="flex min-h-10 items-center gap-1.5 px-4 text-xs font-medium transition-colors hover:text-accent"
            :class="[
              sortKey === option.value ? 'text-accent' : 'text-slate-500',
              option.value === 'amount'
                ? 'justify-end text-right'
                : option.value === 'status'
                  ? 'justify-center text-center'
                  : 'justify-start text-left',
            ]"
            type="button"
            @click="handleSortClick(option.value)"
          >
            <span>{{ option.label }}</span>
            <ChevronUp
              v-if="sortKey === option.value && sortDirection === 'asc'"
              class="h-3.5 w-3.5"
            />
            <ChevronDown
              v-else-if="sortKey === option.value"
              class="h-3.5 w-3.5"
            />
            <ChevronsUpDown v-else class="h-3.5 w-3.5 text-slate-300" />
          </button>
        </div>
      </div>

      <div
        v-if="!isLoading && advances.length === 0"
        class="flex items-center justify-center p-6 text-center border-dashed card min-h-32"
      >
        <p class="text-sm text-slate-400">No advances found.</p>
      </div>

      <template v-if="isLoading">
        <div
          v-for="i in 4"
          :key="`employee-advance-skeleton-${i}`"
          class="p-4 card"
        >
          <SkeletonLoader variant="card" />
        </div>
      </template>

      <template v-else>
        <div
          v-for="adv in paginatedAdvances"
          :key="adv.id"
          :class="[
            'card px-4 py-3 cursor-pointer transition-none group border',
            selectedAdvance?.id === adv.id
              ? 'border-primary bg-primary/[0.02]'
              : 'border-slate-200',
          ]"
          @click="$emit('select', adv)"
        >
          <div
            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
          >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium truncate text-slate-800">
                {{ adv.purpose }}
              </p>
            </div>
            <StatusBadge :status="getBadgeStatus(adv)" />
          </div>

          <div
            class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
          >
            <div>
              <p class="text-xs text-slate-400 mb-0.5">Balance</p>
              <p class="text-base font-semibold text-primary">
                {{ formatPeso(adv.balance || 0) }}
              </p>
            </div>
            <div class="sm:text-right">
              <p class="text-xs text-slate-400 mb-0.5">Aging</p>
              <div class="flex flex-col sm:items-end">
                <span
                  :class="[
                    'text-xs',
                    calculateAging(adv).isOverdue
                      ? 'font-medium text-danger'
                      : 'text-slate-500',
                  ]"
                >
                  Day {{ calculateAging(adv).daysSinceIssue }} of 7
                </span>
                <span
                  v-if="calculateAging(adv).isOverdue"
                  class="text-xs text-danger"
                >
                  Penalty: {{ formatPeso(calculateAging(adv).penalty) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <BasePagination
          v-if="advances.length > pageSize"
          v-model:page="currentPage"
          :page-size="pageSize"
          :total="advances.length"
          label="advances"
          class="rounded-xl border border-slate-200 shadow-sm"
        />
      </template>
    </template>
  </div>
</template>
