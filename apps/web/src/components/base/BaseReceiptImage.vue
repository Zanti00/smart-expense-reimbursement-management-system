<script setup>
import { ref, computed, watch } from "vue";
import { FileText, Image as ImageIcon } from "lucide-vue-next";

const props = defineProps({
  src: {
    type: String,
    default: null,
  },
  alt: {
    type: String,
    default: "Receipt",
  },
  fileType: {
    type: String,
    default: "",
  },
  imgClass: {
    type: String,
    default: "w-full h-full object-cover",
  },
  containerClass: {
    type: String,
    default: "w-full h-full flex items-center justify-center",
  },
  iconSizeClass: {
    type: String,
    default: "w-6 h-6",
  },
  badgeSizeClass: {
    type: String,
    default: "w-12 h-12 rounded-2xl",
  },
  showNoPreviewText: {
    type: Boolean,
    default: true,
  },
  noPreviewText: {
    type: String,
    default: "No Preview",
  },
});

const emit = defineEmits(["load", "error"]);

const imageError = ref(false);

watch(
  () => props.src,
  () => {
    imageError.value = false;
  },
);

const isPdf = computed(() => {
  const type = String(props.fileType || "").toLowerCase();
  if (type === "application/pdf" || type === "pdf") {
    return true;
  }
  const source = String(props.src || "").toLowerCase();
  return source.endsWith(".pdf") || source.includes(".pdf?");
});

const shouldShowImage = computed(() => {
  return Boolean(props.src) && !imageError.value && !isPdf.value;
});

function handleImageError(event) {
  imageError.value = true;
  emit("error", event);
}

function handleImageLoad(event) {
  emit("load", event);
}
</script>

<template>
  <div :class="['relative overflow-hidden', containerClass]">
    <img
      v-if="shouldShowImage"
      :src="src"
      :alt="alt"
      :class="imgClass"
      @error="handleImageError"
      @load="handleImageLoad"
    />
    <slot v-else name="placeholder">
      <div
        class="flex flex-col items-center justify-center w-full h-full gap-2 p-2 text-center select-none"
      >
        <div
          :class="[
            'flex items-center justify-center bg-primary/5',
            badgeSizeClass,
          ]"
        >
          <FileText
            v-if="isPdf"
            :class="['text-primary/40', iconSizeClass]"
          />
          <ImageIcon v-else :class="['text-primary/40', iconSizeClass]" />
        </div>
        <p
          v-if="showNoPreviewText"
          class="text-[10px] text-slate-300 font-semibold uppercase tracking-widest"
        >
          {{ noPreviewText }}
        </p>
      </div>
    </slot>
    <slot />
  </div>
</template>
