<script setup>
import { computed } from "vue";

const props = defineProps({
  page: {
    type: Number,
    required: true,
  },
  pageSize: {
    type: Number,
    default: 10,
  },
  total: {
    type: Number,
    required: true,
  },
  label: {
    type: String,
    default: "records",
  },
});

const emit = defineEmits(["update:page"]);

const totalPages = computed(() =>
  Math.max(1, Math.ceil(props.total / props.pageSize)),
);
const start = computed(() =>
  props.total === 0 ? 0 : (props.page - 1) * props.pageSize + 1,
);
const end = computed(() => Math.min(props.page * props.pageSize, props.total));

function setPage(page) {
  emit("update:page", Math.min(Math.max(1, page), totalPages.value));
}
</script>

<template>
  <div class="flex flex-col gap-3 border-t border-slate-100 bg-white px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
      Showing {{ start }}-{{ end }} of {{ total }} {{ label }}
    </p>
    <div class="flex items-center gap-2">
      <button
        class="btn btn-secondary btn-sm"
        :disabled="page === 1"
        type="button"
        @click="setPage(page - 1)"
      >
        Prev
      </button>
      <span class="rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-bold text-slate-500">
        {{ page }} / {{ totalPages }}
      </span>
      <button
        class="btn btn-secondary btn-sm"
        :disabled="page === totalPages"
        type="button"
        @click="setPage(page + 1)"
      >
        Next
      </button>
    </div>
  </div>
</template>
