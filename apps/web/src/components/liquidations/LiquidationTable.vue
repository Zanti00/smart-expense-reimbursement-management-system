<script setup>
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next';

defineProps({
  columns: {
    type: Array,
    required: true,
  },
  rows: {
    type: Array,
    required: true,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  pageSize: {
    type: Number,
    default: 10,
  },
  sortKey: {
    type: String,
    default: '',
  },
  sortDirection: {
    type: String,
    default: 'asc',
  },
});

defineEmits(['sort']);
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[980px] border-collapse text-left">
      <thead>
        <tr class="border-b border-slate-200 bg-slate-50">
          <th
            v-for="column in columns"
            :key="column.key"
            class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            :class="
              column.align === 'right'
                ? 'text-right'
                : column.align === 'center'
                  ? 'text-center'
                  : 'text-left'
            "
          >
            <button
              class="inline-flex items-center w-full gap-2 transition-colors hover:text-accent"
              :class="
                column.align === 'right'
                  ? 'justify-end'
                  : column.align === 'center'
                    ? 'justify-center'
                    : 'justify-start'
              "
              type="button"
              @click="$emit('sort', column)"
            >
              <span>{{ column.label }}</span>
              <ChevronUp
                v-if="sortKey === column.key && sortDirection === 'asc'"
                class="h-3.5 w-3.5 text-accent"
              />
              <ChevronDown
                v-else-if="sortKey === column.key"
                class="h-3.5 w-3.5 text-accent"
              />
              <ChevronsUpDown v-else class="h-3.5 w-3.5 text-slate-300" />
            </button>
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <template v-if="isLoading">
          <tr
            v-for="i in pageSize"
            :key="`liquidation-skeleton-${i}`"
            class="whitespace-nowrap"
          >
            <td
              v-for="col in columns.length"
              :key="col"
              class="px-5 py-5"
            >
              <div
                v-if="col === columns.length"
                class="mx-auto flex h-8 w-16 max-w-full animate-pulse items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 sm:h-9 sm:w-20 sm:gap-2"
              >
                <div
                  class="h-3 w-3 shrink-0 rounded bg-slate-200 sm:h-3.5 sm:w-3.5"
                ></div>
                <div class="h-2.5 w-5 rounded bg-slate-200 sm:w-7"></div>
              </div>
              <div
                v-else
                class="h-3.5 max-w-full animate-pulse rounded bg-slate-200"
                :class="[
                  col === 5 ? 'mx-auto h-5 w-20 rounded-full sm:w-24' : '',
                  col === 1 ? 'w-24 sm:w-32' : '',
                  col === 2 ? 'w-24 sm:w-32' : '',
                  [3, 4].includes(col) ? 'ml-auto w-20 sm:w-24' : '',
                  ![1, 2, 3, 4, 5, 6].includes(col) ? 'w-20 sm:w-28' : '',
                ]"
              ></div>
            </td>
          </tr>
        </template>
        <tr v-else-if="rows.length === 0">
          <td
            :colspan="columns.length"
            class="px-5 py-10 text-sm font-semibold text-center text-slate-400"
          >
            No liquidation reports found.
          </td>
        </tr>
        <template v-else>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="transition-colors duration-200 ease-out whitespace-nowrap hover:bg-slate-50/80"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              class="px-5 py-5"
              :class="
                col.align === 'right'
                  ? 'text-right'
                  : col.align === 'center'
                    ? 'text-center'
                    : 'text-left'
              "
            >
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                <span
                  class="text-sm"
                  :class="[
                    col.key === 'requestorName' ? 'font-semibold text-slate-700' : 'text-slate-500'
                  ]"
                >
                  {{ row[col.key] }}
                </span>
              </slot>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
