<script setup>
import { Wallet, Activity, ShieldCheck, X } from "lucide-vue-next";
import BaseKpiCard from "@/components/base/BaseKpiCard.vue";
import { formatPeso } from "@/utils/formatters";

defineProps({
  metrics: {
    type: Object,
    required: true,
  },
  isAdmin: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <section class="grid grid-cols-1 gap-4 sm:grid-cols-2" :class="isAdmin ? 'xl:grid-cols-4' : 'xl:grid-cols-5'">
    <BaseKpiCard
      v-if="!isAdmin"
      label="Total Amount"
      :value="formatPeso(metrics.totalAmount)"
      theme="primary"
    >
      <template #icon>
        <Wallet class="h-5 w-5 text-blue-900/35" />
      </template>
    </BaseKpiCard>

    <BaseKpiCard
      :label="isAdmin ? 'Pending Advances' : 'Pending'"
      :value="metrics.pending"
      theme="warning"
    >
      <template #icon>
        <Activity class="h-5 w-5 text-amber-500/35" />
      </template>
    </BaseKpiCard>

    <BaseKpiCard
      :label="isAdmin ? 'Approved Advances' : 'Approved'"
      :value="metrics.approved"
      theme="success"
    >
      <template #icon>
        <ShieldCheck class="h-5 w-5 text-success/35" />
      </template>
    </BaseKpiCard>

    <BaseKpiCard
      :label="isAdmin ? 'Rejected Advances' : 'Rejected'"
      :value="metrics.rejected"
      theme="danger"
    >
      <template #icon>
        <X class="h-5 w-5 text-danger/35" />
      </template>
    </BaseKpiCard>

    <BaseKpiCard
      :label="isAdmin ? 'Total Outstanding Balance' : 'Outstanding Balance'"
      :value="formatPeso(metrics.outstanding)"
      theme="primary"
    >
      <template #icon>
        <Wallet class="h-5 w-5 text-blue-900/35" />
      </template>
      <template #subtext v-if="isAdmin">
        <p class="kpi-label mt-1 normal-case tracking-normal">
          (Total employees with outstanding balance:
          {{ metrics.outstandingEmployees }})
        </p>
      </template>
    </BaseKpiCard>
  </section>
</template>
