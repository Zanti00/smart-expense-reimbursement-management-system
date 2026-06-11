<script setup>
defineProps({
  status: { type: String, required: true }
})

const config = {
  'under-review': { color: 'bg-primary',   label: 'Under Review' },
  approved:     { color: 'bg-emerald-600', label: 'Approved' },
  reject:       { color: 'bg-red-600',     label: 'Reject' },
  rejected:     { color: 'bg-red-600',     label: 'Rejected' },
  paid:         { color: 'bg-emerald-600', label: 'Paid' },
  draft:        { color: 'bg-slate-400',   label: 'Draft' },
  liquidated:   { color: 'bg-emerald-600', label: 'Liquidated' },
  unliquidated: { color: 'bg-amber-600',   label: 'Unliquidated' },
  overdue:      { color: 'bg-red-600',     label: 'Overdue' },
  pending:      { color: 'bg-amber-600',   label: 'Pending' }
  ,
  'automatic-rejected': { color: 'bg-red-600', label: 'Automatic Rejected' },
  'pending-admin-re-review': { color: 'bg-accent', label: 'Pending Admin Re-review' },
  'final-rejected': { color: 'bg-red-700', label: 'Final Rejected' },
  Processed: { color: 'bg-primary', label: 'Processed' },
  Flagged: { color: 'bg-red-600', label: 'Flagged' }
}
</script>

<template>
  <span class="badge">
    <span :class="['w-1.5 h-1.5 rounded-full block', config[status]?.color || 'bg-slate-400']" />
    {{ config[status]?.label || status }}
  </span>
</template>
