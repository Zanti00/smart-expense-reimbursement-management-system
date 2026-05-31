<script setup>
defineProps({
  isAdmin: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <tr class="whitespace-nowrap">
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
