<script setup>
import { ref, computed } from 'vue'
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next'
import SkeletonLoader from './SkeletonLoader.vue'

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
  <div class="flex flex-col gap-2">
    <!-- Toolbar/Search -->
    <div class="flex items-end gap-2 px-1">
      <div class="input-wrapper max-w-xs flex-1">
        <label class="input-label">Search Records</label>
        <input
          v-model="search"
          placeholder="Filter data..."
          class="input"
          @input="currentPage = 1"
        />
      </div>
      <slot name="toolbar" />
    </div>

    <!-- Table Container -->
    <div class="card overflow-hidden border-b-0">
      <div class="overflow-x-auto max-h-[600px] scrollbar-thin">
        <table class="table-base">
          <thead>
            <tr>
              <th
                v-for="col in columns"
                :key="col.key"
                :class="[
                  col.sortable !== false ? 'cursor-pointer select-none' : '',
                  col.class
                ]"
                @click="col.sortable !== false && setSort(col.key)"
              >
                <div class="flex items-center justify-between gap-2">
                  <span>{{ col.label }}</span>
                  <template v-if="col.sortable !== false">
                    <ChevronUp v-if="sortKey === col.key && sortDir === 'asc'" class="w-3 h-3 text-primary" />
                    <ChevronDown v-else-if="sortKey === col.key && sortDir === 'desc'" class="w-3 h-3 text-primary" />
                    <ChevronsUpDown v-else class="w-3 h-3 text-slate-300" />
                  </template>
                </div>
              </th>
            </tr>
          </thead>
          <tbody :class="density === 'compact' ? '[&>tr>td]:!py-1.5 [&>tr>td]:!px-3' : ''">
            <tr v-if="loading">
              <td :colspan="columns.length" class="p-6 border-r-0 bg-slate-50">
                <SkeletonLoader variant="table" :rows="10" :columns="columns.length" />
              </td>
            </tr>
            <tr v-else-if="paginated.length === 0">
              <td :colspan="columns.length" class="py-12 text-center text-slate-400 italic border-r-0">
                No records found.
              </td>
            </tr>
            <tr
              v-else
              v-for="row in paginated"
              :key="row.id"
              class="group cursor-pointer"
              @click="emit('row-click', row)"
            >
              <td v-for="col in columns" :key="col.key" :class="col.cellClass">
                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                  {{ row[col.key] }}
                </slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination / Footer -->
    <div
      v-if="filtered.length > pageSize"
      class="flex items-center justify-between border border-black/5 border-t-0 bg-white/90 px-3 py-2 rounded-b-card shadow-sm"
    >
      <div class="text-[10px] font-bold uppercase tracking-widest text-slate-500">
        Record {{ Math.min((currentPage - 1) * pageSize + 1, filtered.length) }}–{{ Math.min(currentPage * pageSize, filtered.length) }}
        / Total: {{ filtered.length }}
      </div>
      <div class="flex gap-1">
        <button 
          class="btn btn-secondary btn-sm !px-1.5 !py-0.5" 
          :disabled="currentPage === 1" 
          @click="prevPage"
        >
          PREV
        </button>
        <div class="flex items-center px-2 text-[10px] font-mono border-x border-slate-100">
          PAGE {{ currentPage }} / {{ totalPages }}
        </div>
        <button 
          class="btn btn-secondary btn-sm !px-1.5 !py-0.5" 
          :disabled="currentPage === totalPages" 
          @click="nextPage"
        >
          NEXT
        </button>
      </div>
    </div>
  </div>
</template>
