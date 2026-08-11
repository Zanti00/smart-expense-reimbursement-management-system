<script setup>
import { UploadCloud, AlertTriangle } from "lucide-vue-next";

defineProps({
  receiptDrag: Boolean,
});

defineEmits(["drop", "dragover", "dragleave", "upload"]);

const isDropdownOpen = ref(false);
</script>

<template>
  <section class="p-6 card">
    <div
      class="flex flex-col gap-3 mb-5 sm:flex-row sm:items-center sm:justify-between"
    >
      <h2 class="text-lg font-bold font-heading text-primary">
        Upload Receipts
      </h2>
    </div>
    <div
      class="flex min-h-[320px] flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-colors cursor-pointer"
      :class="
        receiptDrag
          ? 'border-accent bg-accent/5'
          : 'border-slate-200 bg-slate-50/50 hover:border-accent/50'
      "
      @dragover.prevent="$emit('dragover')"
      @dragleave.prevent="$emit('dragleave')"
      @drop.prevent="$emit('drop', $event)"
      @click="$emit('click')"
    >
      <span
        class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-2xl bg-accent/10 text-accent"
      >
        <UploadCloud class="w-8 h-8" />
      </span>
      <h3 class="text-base font-bold font-heading text-slate-800">
        Drag and drop receipt images here, or click to browse
      </h3>
      <p class="mt-1 text-sm text-slate-400">
        Supports: JPG, PNG, PDF (Max 2MB per file)
      </p>
      <p class="flex items-center gap-2 mt-4 text-sm font-bold text-danger">
        <AlertTriangle class="w-4 h-4" />
        At least 1 receipt is required to proceed
      </p>
      <div class="mt-6">
        <div class="relative inline-block text-left" @click.stop>
          <div>
            <button
              @click="isDropdownOpen = !isDropdownOpen"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-accent px-6 text-sm font-bold text-white transition-colors hover:bg-accent-600 focus:outline-none"
            >
              <UploadCloud class="w-4 h-4" />
              Select Files
              <ChevronDown class="w-4 h-4 ml-1 -mr-1" aria-hidden="true" />
            </button>
          </div>

          <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
          >
            <div
              v-if="isDropdownOpen"
              class="absolute z-10 w-56 mt-2 origin-top right-1/2 translate-x-1/2 bg-white divide-y divide-gray-100 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
            >
              <div class="px-1 py-1">
                <button
                  class="group flex w-full items-center rounded-md px-2 py-2 text-sm font-medium text-gray-900 hover:bg-accent hover:text-white"
                  @click="$emit('upload', 'single'); isDropdownOpen = false"
                >
                  <UploadCloud class="mr-2 h-4 w-4 text-accent group-hover:text-white" aria-hidden="true" />
                  Single Upload
                </button>
                <button
                  class="group flex w-full items-center rounded-md px-2 py-2 text-sm font-medium text-gray-900 hover:bg-accent hover:text-white"
                  @click="$emit('upload', 'multi'); isDropdownOpen = false"
                >
                  <Layers class="mr-2 h-4 w-4 text-accent group-hover:text-white" aria-hidden="true" />
                  Multiple Upload
                </button>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>
  </section>
</template>
