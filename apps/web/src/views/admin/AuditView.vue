<script setup>
import BaseTable from '@/components/base/BaseTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { Download, Search, Filter } from 'lucide-vue-next'

const MOCK_LOGS = [
  { id: 10, timestamp: '2026-04-06 15:00:00', user: 'System',       action: 'CREATE_PENALTY', entity: 'POL-PENALTY', ip: 'internal',     role: 'system' },
  { id: 11, timestamp: '2026-04-06 14:45:12', user: 'Alex Reyes',   action: 'UPDATE_LIMIT', entity: 'POL-LAB-SUPP', ip: '192.168.1.10', role: 'admin' },
  { id: 1,  timestamp: '2026-04-06 14:32:11', user: 'Alex Reyes',   action: 'LIQUIDATED', entity: 'CA-2024-003', ip: '192.168.1.10', role: 'admin' },
  { id: 2,  timestamp: '2026-04-06 13:15:02', user: 'Maria Cruz',   action: 'SUBMITTED',  entity: 'CA-2024-001', ip: '192.168.1.22', role: 'manager' },
  { id: 3,  timestamp: '2026-04-05 11:00:40', user: 'John Santos',  action: 'OVERDUE',    entity: 'CA-2024-002', ip: 'internal',     role: 'system' },
  { id: 4,  timestamp: '2026-04-04 10:20:18', user: 'Alex Reyes',   action: 'REJECTED',   entity: 'CA-2024-004', ip: '192.168.1.10', role: 'admin' },
  { id: 5,  timestamp: '2026-04-03 17:45:55', user: 'System',       action: 'AUTO_FLAG',  entity: 'RM-2024-005', ip: 'internal',     role: 'system' },
]

const auditColumns = [
  { key: 'timestamp', label: 'DATE',         sortable: true, cellClass: 'text-slate-400 font-mono' },
  { key: 'user',      label: 'USER',         sortable: true, cellClass: 'font-bold text-slate-700' },
  { key: 'action',    label: 'ACTION',       sortable: true },
  { key: 'entity',    label: 'REFERENCE',    sortable: true, cellClass: 'font-mono' },
  { key: 'ip',        label: 'IP ADDRESS',   sortable: true, cellClass: 'text-slate-400 font-mono' },
]

const ACTION_MAP = {
  LIQUIDATED:     'text-success border-success/30 bg-success/5',
  REJECTED:       'text-danger border-danger/30 bg-danger/5',
  SUBMITTED:      'text-primary border-primary/30 bg-primary/5',
  OVERDUE:        'text-red-800 border-red-200 bg-red-50',
  AUTO_FLAG:      'text-amber-800 border-amber-200 bg-amber-50',
  CREATE_LIMIT:   'text-indigo-600 border-indigo-200 bg-indigo-50',
  UPDATE_LIMIT:   'text-indigo-600 border-indigo-200 bg-indigo-50',
  DELETE_LIMIT:   'text-indigo-600 border-indigo-200 bg-indigo-50',
  CREATE_PENALTY: 'text-purple-600 border-purple-200 bg-purple-50',
  TOGGLE_PENALTY: 'text-purple-600 border-purple-200 bg-purple-50',
}
</script>

<template>
  <div class="flex flex-col gap-6 font-sans">
    <!-- Page Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="min-w-0">

        <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
          Audit & Compliance
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          Immutable system activity logs and compliance reports
        </p>
      </div>
    </div>

    <!-- Audit Log -->
    <div class="flex items-center justify-between mb-2 mt-4">
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2 border-b border-slate-200 pb-1">
          <Search class="w-3 h-3 text-slate-700" />
          <input placeholder="Filter by user or ref..." class="bg-transparent border-none focus:ring-0 text-[11px] p-0 w-48 text-slate-900" />
        </div>
        <button class="flex items-center gap-1.5 text-[10px] font-bold text-slate-700 uppercase tracking-widest hover:text-primary">
          <Filter class="w-3 h-3" /> Filter Labels
        </button>
      </div>
      <BaseButton variant="secondary" class="!py-1 !text-[10px]">
        <Download class="w-3 h-3 mr-1.5" /> EXPORT_CSV
      </BaseButton>
    </div>

    <BaseTable :columns="auditColumns" :rows="MOCK_LOGS" :page-size="12">
      <template #cell-action="{ value }">
        <div :class="['text-[9px] font-bold uppercase py-0.5 px-1.5 border tracking-widest text-center', ACTION_MAP[value] || 'text-slate-400 border-slate-200 bg-slate-50']">
          {{ value }}
        </div>
      </template>
      <template #cell-entity="{ value }">
        <span class="text-[11px] font-bold text-primary tracking-tighter">{{ value }}</span>
      </template>
    </BaseTable>
  </div>
</template>
