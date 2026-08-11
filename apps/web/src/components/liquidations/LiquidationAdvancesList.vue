<script setup>
import {
  ChevronUp,
  ChevronDown,
  ChevronsUpDown,
} from "lucide-vue-next";
import SkeletonLoader from "@/components/base/SkeletonLoader.vue";
import StatusBadge from "@/components/base/StatusBadge.vue";
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
});

const emit = defineEmits(["update:sortKey", "update:sortDirection", "select"]);

function handleSortClick(optionValue) {
  if (props.sortKey === optionValue) {
    emit(
      "update:sortDirection",
      props.sortDirection === "asc" ? "desc" : "asc"
    );
  } else {
    emit("update:sortKey", optionValue);
    emit("update:sortDirection", "asc");
  }
}
</script>

<template>
  <div class="flex flex-col gap-3 xl:col-span-2">
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
      <p class="text-sm text-slate-400">
        No advances found.
      </p>
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
        v-for="adv in advances"
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
    </template>
  </div>
</template>
