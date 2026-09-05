<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from "vue";
import {
  X,
  ZoomIn,
  ZoomOut,
  RotateCw,
  RotateCcw,
  Maximize2,
} from "lucide-vue-next";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  isOpen: {
    type: Boolean,
    default: undefined,
  },
  src: {
    type: String,
    required: true,
  },
  alt: {
    type: String,
    default: "Image preview",
  },
  title: {
    type: String,
    default: "",
  },
  minZoom: {
    type: Number,
    default: 0.5,
  },
  maxZoom: {
    type: Number,
    default: 4.0,
  },
  zoomStep: {
    type: Number,
    default: 0.25,
  },
  initialZoom: {
    type: Number,
    default: 1.0,
  },
  zIndexClass: {
    type: String,
    default: "z-[100]",
  },
});

const emit = defineEmits(["update:modelValue", "close"]);

const isVisible = computed(() =>
  props.isOpen !== undefined ? props.isOpen : props.modelValue,
);

const viewportRef = ref(null);
const zoom = ref(props.initialZoom);
const rotation = ref(0);
const pan = ref({ x: 0, y: 0 });
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const panStart = ref({ x: 0, y: 0 });

const zoomPercentage = computed(() => `${Math.round(zoom.value * 100)}%`);

function clampZoom(val) {
  return Math.min(Math.max(val, props.minZoom), props.maxZoom);
}

function zoomToPoint(targetZoom, clientX, clientY) {
  const oldZoom = zoom.value;
  const clampedTarget = clampZoom(targetZoom);
  if (oldZoom === clampedTarget) return;

  if (
    viewportRef.value &&
    clientX !== undefined &&
    clientY !== undefined &&
    oldZoom > 0
  ) {
    const rect = viewportRef.value.getBoundingClientRect();
    const viewportCenterX = rect.left + rect.width / 2;
    const viewportCenterY = rect.top + rect.height / 2;
    const scaleFactor = clampedTarget / oldZoom;

    pan.value = {
      x:
        pan.value.x -
        (clientX - (viewportCenterX + pan.value.x)) * (scaleFactor - 1),
      y:
        pan.value.y -
        (clientY - (viewportCenterY + pan.value.y)) * (scaleFactor - 1),
    };
  }
  zoom.value = clampedTarget;
}

function zoomIn() {
  zoomToPoint(+(zoom.value + props.zoomStep).toFixed(2));
}

function zoomOut() {
  zoomToPoint(+(zoom.value - props.zoomStep).toFixed(2));
}

function resetZoom() {
  zoom.value = props.initialZoom;
  rotation.value = 0;
  pan.value = { x: 0, y: 0 };
}

function rotateClockwise() {
  rotation.value = (rotation.value + 90) % 360;
}

function handleWheel(e) {
  e.preventDefault();
  const nextZoom = +(
    zoom.value + (e.deltaY < 0 ? props.zoomStep : -props.zoomStep)
  ).toFixed(2);
  zoomToPoint(nextZoom, e.clientX, e.clientY);
}

function toggleZoomClick(e) {
  if (hasMoved.value) return;
  if (zoom.value <= 1.05) {
    const clientX = e?.clientX ?? e?.touches?.[0]?.clientX;
    const clientY = e?.clientY ?? e?.touches?.[0]?.clientY;
    zoomToPoint(2.0, clientX, clientY);
  } else {
    resetZoom();
  }
}

const hasMoved = ref(false);

function startDrag(e) {
  // Only drag on primary mouse button or touch
  if (e.type === "mousedown" && e.button !== 0) return;
  isDragging.value = true;
  hasMoved.value = false;
  const clientX = e.type.startsWith("touch") ? e.touches[0].clientX : e.clientX;
  const clientY = e.type.startsWith("touch") ? e.touches[0].clientY : e.clientY;
  dragStart.value = { x: clientX, y: clientY };
  panStart.value = { x: pan.value.x, y: pan.value.y };
}

function onDrag(e) {
  if (!isDragging.value) return;
  const clientX = e.type.startsWith("touch") ? e.touches[0].clientX : e.clientX;
  const clientY = e.type.startsWith("touch") ? e.touches[0].clientY : e.clientY;
  const dist = Math.hypot(
    clientX - dragStart.value.x,
    clientY - dragStart.value.y,
  );
  if (dist > 5) {
    hasMoved.value = true;
  }
  pan.value = {
    x: panStart.value.x + (clientX - dragStart.value.x),
    y: panStart.value.y + (clientY - dragStart.value.y),
  };
}

function stopDrag() {
  isDragging.value = false;
}

function close() {
  emit("update:modelValue", false);
  emit("close");
}

function handleKeyDown(e) {
  if (!isVisible.value) return;
  if (e.key === "Escape") {
    close();
  } else if (e.key === "+" || e.key === "=") {
    e.preventDefault();
    zoomIn();
  } else if (e.key === "-" || e.key === "_") {
    e.preventDefault();
    zoomOut();
  } else if (e.key === "0" || e.key === "r" || e.key === "R") {
    e.preventDefault();
    resetZoom();
  }
}

// Reset state whenever modal opens or closes or src changes
watch([isVisible, () => props.src], ([visible]) => {
  if (visible) {
    resetZoom();
  }
});

onMounted(() => {
  window.addEventListener("keydown", handleKeyDown);
  window.addEventListener("mousemove", onDrag);
  window.addEventListener("mouseup", stopDrag);
  window.addEventListener("touchmove", onDrag, { passive: false });
  window.addEventListener("touchend", stopDrag);
});

onUnmounted(() => {
  window.removeEventListener("keydown", handleKeyDown);
  window.removeEventListener("mousemove", onDrag);
  window.removeEventListener("mouseup", stopDrag);
  window.removeEventListener("touchmove", onDrag);
  window.removeEventListener("touchend", stopDrag);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="preview-modal">
      <div
        v-if="isVisible"
        :class="[
          'fixed inset-0 select-none overflow-hidden flex flex-col justify-between items-center bg-black/90 backdrop-blur-md transition-opacity duration-200',
          zIndexClass,
        ]"
        role="dialog"
        aria-modal="true"
        :aria-label="title || alt"
        @click="close"
      >
        <!-- Top Bar: Title & Close Button -->
        <div
          class="w-full flex items-center justify-between px-6 py-4 z-20 pointer-events-auto"
          @click.stop
        >
          <!-- Optional Image Title / Filename -->
          <div class="flex items-center gap-2 min-w-0 pr-4">
            <span
              v-if="title"
              class="text-sm font-medium text-white/90 truncate max-w-[60vw]"
              :title="title"
            >
              {{ title }}
            </span>
          </div>

          <!-- Close Button Top-Right -->
          <button
            type="button"
            class="flex items-center justify-center w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 active:bg-white/30 text-white/80 hover:text-white transition-colors duration-150 backdrop-blur-md shadow-lg border border-white/10 ml-auto focus:outline-none focus:ring-2 focus:ring-white/40"
            title="Close (Esc)"
            aria-label="Close preview"
            @click="close"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Center Image Viewport (Click on container background closes modal) -->
        <div
          ref="viewportRef"
          class="relative w-full flex-1 flex items-center justify-center p-4 md:p-8 overflow-hidden cursor-default"
          @wheel="handleWheel"
          @click.self="close"
        >
          <div
            class="relative inline-flex items-center justify-center select-none"
            :class="
              isDragging
                ? 'transition-none'
                : 'transition-transform duration-200 ease-out'
            "
            :style="{
              transform: `translate3d(${pan.x}px, ${pan.y}px, 0) scale(${zoom}) rotate(${rotation}deg)`,
              cursor: isDragging
                ? 'grabbing'
                : zoom <= 1.05
                  ? 'zoom-in'
                  : 'grab',
            }"
            @mousedown="startDrag"
            @touchstart="startDrag"
            @click.stop="toggleZoomClick($event)"
          >
            <img
              :src="src"
              :alt="alt"
              class="max-w-full max-h-[75vh] w-auto h-auto object-contain rounded-lg shadow-2xl pointer-events-none select-none ring-1 ring-white/10"
              draggable="false"
            />
          </div>
        </div>

        <!-- Bottom Floating Control Toolbar -->
        <div
          class="pb-6 z-20 pointer-events-auto flex items-center justify-center"
          @click.stop
        >
          <div
            class="flex items-center gap-1.5 sm:gap-2 px-4 py-2 rounded-full bg-slate-900/80 backdrop-blur-xl border border-white/15 shadow-2xl text-white"
          >
            <!-- Zoom Out Button -->
            <button
              type="button"
              class="p-2 rounded-full hover:bg-white/15 active:bg-white/25 transition-colors disabled:opacity-35 disabled:cursor-not-allowed text-white/90 hover:text-white focus:outline-none"
              title="Zoom out (-)"
              aria-label="Zoom out"
              :disabled="zoom <= minZoom"
              @click="zoomOut"
            >
              <ZoomOut class="w-4 h-4" />
            </button>

            <!-- Zoom Percentage Display (Click to reset) -->
            <button
              type="button"
              class="px-2.5 py-1 text-xs font-mono font-semibold text-white/90 hover:text-white rounded-md hover:bg-white/15 transition-colors min-w-[54px] text-center focus:outline-none"
              title="Click to reset zoom (0)"
              aria-label="Reset zoom percentage"
              @click="resetZoom"
            >
              {{ zoomPercentage }}
            </button>

            <!-- Zoom In Button -->
            <button
              type="button"
              class="p-2 rounded-full hover:bg-white/15 active:bg-white/25 transition-colors disabled:opacity-35 disabled:cursor-not-allowed text-white/90 hover:text-white focus:outline-none"
              title="Zoom in (+)"
              aria-label="Zoom in"
              :disabled="zoom >= maxZoom"
              @click="zoomIn"
            >
              <ZoomIn class="w-4 h-4" />
            </button>

            <div class="h-4 w-px bg-white/20 mx-0.5 sm:mx-1"></div>

            <!-- Rotate Clockwise Button -->
            <button
              type="button"
              class="p-2 rounded-full hover:bg-white/15 active:bg-white/25 transition-colors text-white/90 hover:text-white focus:outline-none"
              title="Rotate 90°"
              aria-label="Rotate clockwise"
              @click="rotateClockwise"
            >
              <RotateCw class="w-4 h-4" />
            </button>

            <!-- Reset Zoom & Position Button -->
            <button
              type="button"
              class="p-2 rounded-full hover:bg-white/15 active:bg-white/25 transition-colors text-white/90 hover:text-white focus:outline-none"
              title="Reset view (R)"
              aria-label="Reset view"
              @click="resetZoom"
            >
              <Maximize2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.preview-modal-enter-active,
.preview-modal-leave-active {
  transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.preview-modal-enter-from,
.preview-modal-leave-to {
  opacity: 0;
}
</style>
