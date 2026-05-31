<template>
  <div class="bg-white rounded-xl overflow-hidden flex flex-col border border-slate-100 shadow-md">
    <!-- Expense Image Preview Skeleton -->
    <div class="aspect-square w-full bg-slate-50 flex-shrink-0 border-b border-slate-100 flex flex-col items-center justify-center gap-2">
      <div class="w-12 h-12 rounded-2xl bg-slate-200 animate-pulse"></div>
      <div class="h-2.5 w-16 bg-slate-200 rounded animate-pulse mt-1"></div>
    </div>

    <!-- Card Body Skeleton -->
    <div class="p-4 flex flex-col flex-1">
      <!-- File / Merchant Info Skeleton -->
      <div class="mb-3">
        <div class="h-3.5 bg-slate-200 rounded w-3/4 animate-pulse"></div>
        <div class="h-2.5 bg-slate-200 rounded w-1/2 animate-pulse mt-2"></div>
        <div class="h-2.5 bg-slate-200 rounded w-2/3 animate-pulse mt-1.5"></div>
      </div>

      <!-- Category + Amount Skeleton -->
      <div class="mt-auto flex items-center justify-between mb-3 pt-2">
        <div class="h-6 w-16 bg-slate-200 rounded-md animate-pulse"></div>
        <div class="h-4 w-20 bg-slate-200 rounded animate-pulse"></div>
      </div>

      <!-- Action Buttons Skeleton -->
      <div class="flex gap-2">
        <div class="h-[34px] bg-slate-200 rounded-lg flex-1 animate-pulse"></div>
        <div class="h-[34px] w-[38px] bg-slate-200 rounded-lg animate-pulse"></div>
      </div>
    </div>
  </div>
</template>
