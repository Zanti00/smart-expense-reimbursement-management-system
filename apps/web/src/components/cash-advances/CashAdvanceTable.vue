<script setup>
import { Eye } from "lucide-vue-next";
import { formatPeso } from "@/utils/formatters";

defineProps({
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
        <template v-else>Showing {{ rows.length }} records</template>
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
              v-if="isAdmin"
              class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              ID
            </th>
            <th
              v-if="isAdmin"
              class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              File Description
            </th>
            <th
              class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
            >
              Purpose
            </th>
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
              v-if="isAdmin"
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
          <template v-if="isLoading">
            <tr v-for="i in 5" :key="`skeleton-${i}`" class="whitespace-nowrap">
              <td v-if="isAdmin" class="px-5 py-5">
                <div class="h-5 w-6 animate-pulse rounded bg-slate-200"></div>
              </td>
              <td v-if="isAdmin" class="px-5 py-5">
                <div class="h-4 w-24 animate-pulse rounded bg-slate-200"></div>
              </td>
              <td class="px-5 py-5">
                <div class="h-4 w-32 animate-pulse rounded bg-slate-200"></div>
              </td>
              <td class="px-5 py-5">
                <div class="h-4 w-20 animate-pulse rounded bg-slate-200"></div>
              </td>
              <td class="px-5 py-5">
                <div class="h-4 w-20 animate-pulse rounded bg-slate-200"></div>
              </td>
              <td class="px-5 py-5 text-right">
                <div
                  class="ml-auto h-5 w-20 animate-pulse rounded bg-slate-200"
                ></div>
              </td>
              <td class="px-5 py-5 text-right">
                <div
                  class="ml-auto h-4 w-16 animate-pulse rounded bg-slate-200"
                ></div>
              </td>
              <td v-if="isAdmin" class="px-5 py-5">
                <div class="flex items-center gap-2">
                  <div
                    class="h-7 w-7 shrink-0 animate-pulse rounded-full bg-slate-200"
                  ></div>
                  <div
                    class="h-4 w-24 animate-pulse rounded bg-slate-200"
                  ></div>
                </div>
              </td>
              <td class="px-5 py-5 text-center">
                <div
                  class="mx-auto h-6 w-20 animate-pulse rounded-full bg-slate-200"
                ></div>
              </td>
              <td class="px-5 py-5 text-center">
                <div
                  class="mx-auto h-9 w-9 animate-pulse rounded-full bg-slate-200"
                ></div>
              </td>
            </tr>
          </template>
          <template v-else-if="rows.length === 0">
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
              v-for="row in rows"
              :key="row.id"
              class="group whitespace-nowrap transition-colors duration-200 ease-out hover:bg-slate-50/80"
            >
              <td v-if="isAdmin" class="px-5 py-5 font-mono text-sm font-bold text-slate-900">
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
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-accent/15 bg-accent/5 text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.04] focus:outline-none"
                  title="View cash advance"
                  @click="$emit('view', row)"
                >
                  <span
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-accent/30"
                    ><Eye class="h-3.5 w-3.5"
                  /></span>
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </section>
</template>
