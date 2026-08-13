<script setup>
import { Eye } from "lucide-vue-next";
import { formatPeso } from "@/utils/formatters";
import StatusBadge from "@/components/base/StatusBadge.vue";
import ActionDropdownMenu from "@/components/base/ActionDropdownMenu.vue";

defineProps({
  rows: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits(["view"]);

function getActions(row) {
  return [
    {
      label: "View",
      icon: Eye,
      visible: true,
      handler: () => emit("view", row),
    },
  ];
}

function displayStatus(status) {
  const normalized = String(status || "")
    .trim()
    .toLowerCase()
    .replace(/[-_]+/g, " ")
    .replace(/\s+/g, " ");

  return normalized === "under review" ? "under review" : status;
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
          Cash Advance Management
        </h2>
        <p class="mt-0.5 text-xs text-slate-400">Administrative review queue</p>
      </div>
      <span class="kpi-label text-slate-400"
        >Showing {{ rows.length }} records</span
      >
    </div>
    <div class="overflow-x-auto">
      <table class="w-full min-w-[1080px] border-collapse text-left">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50">
            <th
              class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              Date Requested
            </th>
            <th
              class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              Due Date
            </th>
            <th
              class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              Amount
            </th>
            <th
              class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              Outstanding
            </th>
            <th
              class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              User
            </th>
            <th
              class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              Status
            </th>
            <th
              class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="row in rows"
            :key="row.id"
            class="whitespace-nowrap transition-colors duration-200 ease-out hover:bg-slate-50/80"
          >
            <td class="px-5 py-5 text-sm text-slate-500">
              {{ row.requested }}
            </td>
            <td class="px-5 py-5 text-sm text-slate-500">{{ row.dueDate }}</td>
            <td class="px-5 py-5 text-right text-sm font-bold text-primary">
              {{ formatPeso(row.amount) }}
            </td>
            <td
              class="px-5 py-5 text-right text-sm font-semibold text-slate-600"
            >
              {{ formatPeso(row.outstanding) }}
            </td>
            <td class="px-5 py-5">
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
              <StatusBadge :status="row.status" />
            </td>
            <td class="px-5 py-5 text-center">
              <ActionDropdownMenu :actions="getActions(row)" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
