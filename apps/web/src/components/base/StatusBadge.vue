<script setup>
defineProps({
  status: { type: String, required: true }
})

const config = {
  approved:     { bg: 'bg-emerald-600', label: 'Approved' },
  granted:      { bg: 'bg-emerald-600', label: 'Granted' },
  paid:         { bg: 'bg-emerald-600', label: 'Paid' },
  liquidated:   { bg: 'bg-emerald-600', label: 'Liquidated' },
  settled:      { bg: 'bg-emerald-600', label: 'Settled' },
  disbursed:    { bg: 'bg-blue-600',    label: 'Disbursed' },
  signed:       { bg: 'bg-blue-600',    label: 'Signed' },
  processing:   { bg: 'bg-blue-600',    label: 'Processing' },
  processed:    { bg: 'bg-primary',     label: 'Processed' },
  'under-review': { bg: 'bg-violet-600', label: 'Under Review' },
  pending:      { bg: 'bg-amber-500',   label: 'Pending' },
  incomplete:   { bg: 'bg-amber-500',   label: 'Incomplete' },
  unliquidated: { bg: 'bg-amber-500',   label: 'Unliquidated' },
  overpayment:  { bg: 'bg-blue-500',    label: 'Overpayment' },
  overdue:      { bg: 'bg-red-600',     label: 'Overdue' },
  revise:      { bg: 'bg-orange-500',  label: 'Needs Revision' },
  rejected:     { bg: 'bg-red-600',     label: 'Rejected' },
  reject:       { bg: 'bg-red-600',     label: 'Reject' },
  'automatic-rejected': { bg: 'bg-red-600', label: 'Auto Rejected' },
  'final-rejected': { bg: 'bg-red-700', label: 'Final Rejected' },
  'pending-admin-re-review': { bg: 'bg-accent', label: 'Pending Re-review' },
  draft:        { bg: 'bg-slate-500',   label: 'Draft' },
  flagged:      { bg: 'bg-red-600',     label: 'Flagged' },
}

function getConfig(status) {
  if (!status) return null
  const key = status.toLowerCase().replace(/\s+/g, '-')
  return config[key] || config[status] || null
}
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold text-white',
      getConfig(status)?.bg || 'bg-slate-400'
    ]"
  >
    {{ getConfig(status)?.label || status }}
  </span>
</template>
