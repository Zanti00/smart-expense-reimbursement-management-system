<script setup>
import { ref, computed } from 'vue'
import { ChevronUp, ChevronDown, ChevronsUpDown, Search } from 'lucide-vue-next'
import SkeletonLoader from './SkeletonLoader.vue'
import BaseButton from './BaseButton.vue'
import { cn } from '@/utils/cn'

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  pageSize: { type: Number, default: 10 },
  density: { type: String, default: 'standard', validator: (v) => ['standard', 'compact'].includes(v) }
})

const emit = defineEmits(['row-click'])

const sortKey = ref('')
const sortDir = ref('asc')
const currentPage = ref(1)
const search = ref('')

function setSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
  currentPage.value = 1
}

const filtered = computed(() => {
  let data = [...props.rows]
  if (search.value) {
    const q = search.value.toLowerCase()
    data = data.filter(row =>
      Object.values(row).some(v => String(v).toLowerCase().includes(q))
    )
  }
  if (sortKey.value) {
    data.sort((a, b) => {
      const av = a[sortKey.value], bv = b[sortKey.value]
      const cmp = av < bv ? -1 : av > bv ? 1 : 0
      return sortDir.value === 'asc' ? cmp : -cmp
    })
  }
  return data
})

const totalPages = computed(() => Math.ceil(filtered.value.length / props.pageSize) || 1)
const paginated = computed(() => {
  const start = (currentPage.value - 1) * props.pageSize
  return filtered.value.slice(start, start + props.pageSize)
})

function prevPage() { if (currentPage.value > 1) currentPage.value-- }
function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++ }
</script>

<template>
  <div class="flex flex-col gap-3 font-sans">
    <!-- Toolbar/Search -->
    <div class="flex items-center justify-between gap-3 px-1">
      <div class="relative max-w-xs flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
        <input
          v-model="search"
          placeholder="Filter data..."
          class="w-full h-9 pl-9 pr-3 text-sm font-sans rounded-lg bg-white border border-slate-200 outline-none focus:border-[#2E85D8] focus:ring-2 focus:ring-[#2E85D8]/20 transition-all shadow-sm"
          @input="currentPage = 1"
        />
      </div>
      <slot name="toolbar" />
    </div>

    <!-- Table Container (Section VI.10) -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="overflow-x-auto max-h-[600px] scrollbar-thin">
        <table class="w-full text-left border-collapse">
          <!-- Column Headers: Uppercase Poppins, bold, expanded letter tracking, Muted Slate #94A3B8 -->
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/80 sticky top-0 z-10">
              <th
                v-for="col in columns"
                :key="col.key"
                :class="[
                  'px-4 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-400 select-none font-sans',
                  col.sortable !== false ? 'cursor-pointer hover:text-slate-700 transition-colors' : '',
                  col.class
                ]"
                @click="col.sortable !== false && setSort(col.key)"
              >
                <div class="flex items-center justify-between gap-2">
                  <span>{{ col.label }}</span>
                  <template v-if="col.sortable !== false">
                    <ChevronUp v-if="sortKey === col.key && sortDir === 'asc'" class="w-3.5 h-3.5 text-primary" />
                    <ChevronDown v-else-if="sortKey === col.key && sortDir === 'desc'" class="w-3.5 h-3.5 text-primary" />
                    <ChevronsUpDown v-else class="w-3.5 h-3.5 text-slate-300" />
                  </template>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="columns.length" class="p-6 bg-slate-50/50">
                <SkeletonLoader variant="table" :rows="8" :columns="columns.length" />
              </td>
            </tr>
            <tr v-else-if="paginated.length === 0">
              <td :colspan="columns.length" class="py-12 text-center text-slate-400 italic">
                No records found.
              </td>
            </tr>
            <tr
              v-else
              v-for="row in paginated"
              :key="row.id"
              :class="[
                'group cursor-pointer transition-colors border-b border-slate-100 hover:bg-slate-50/80',
                density === 'compact' ? 'h-10' : 'min-h-[56px] h-14'
              ]"
              @click="emit('row-click', row)"
            >
              <td
                v-for="col in columns"
                :key="col.key"
                :class="cn('px-4 py-3 text-sm text-slate-700 font-sans align-middle', col.cellClass)"
              >
                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                  {{ row[col.key] }}
                </slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination / Footer -->
      <div
        v-if="filtered.length > pageSize"
        class="flex items-center justify-between border-t border-slate-100 bg-white px-4 py-3"
      >
        <div class="text-xs font-semibold text-slate-500 font-sans">
          Record {{ Math.min((currentPage - 1) * pageSize + 1, filtered.length) }}–{{ Math.min(currentPage * pageSize, filtered.length) }}
          of {{ filtered.length }}
        </div>
        <div class="flex items-center gap-1.5">
          <BaseButton 
            variant="secondary" 
            size="sm"
            :disabled="currentPage === 1" 
            @click="prevPage"
          >
            Previous
          </BaseButton>
          <div class="px-2 text-xs font-medium text-slate-600 font-sans">
            Page {{ currentPage }} of {{ totalPages }}
          </div>
          <BaseButton 
            variant="secondary" 
            size="sm"
            :disabled="currentPage === totalPages" 
            @click="nextPage"
          >
            Next
          </BaseButton>
        </div>
      </div>
    </div>
  </div>
</template>
