<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { Calendar, ChevronLeft, ChevronRight, ChevronDown } from "lucide-vue-next";

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  cutoffPeriod: {
    type: String,
    default: "",
  },
  referenceDate: {
    type: [Date, String, Number],
    default: () => new Date(),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "update:cutoffPeriod"]);

const MONTH_NAMES = [
  "Jan", "Feb", "Mar", "Apr", "May", "Jun",
  "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
];

const WEEK_DAYS = ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"];

const isOpen = ref(false);
const containerRef = ref(null);

const activeValue = computed(() => props.cutoffPeriod || props.modelValue || "");

const refDate = computed(() => {
  if (!props.referenceDate) return new Date();
  const d = new Date(props.referenceDate);
  return isNaN(d.getTime()) ? new Date() : d;
});

const currentYear = computed(() => refDate.value.getFullYear());
const currentMonth = computed(() => refDate.value.getMonth());
const currentDay = computed(() => refDate.value.getDate());

const viewYear = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth());

function parseCutoffPeriod(str) {
  if (!str) return null;
  const trimmed = str.trim();

  // Match "Oct 01 - Oct 15, 2026" or "Oct 1 - 15, 2026" or "October 1 - October 15, 2026"
  const m = trimmed.match(/^([A-Za-z]{3,9})\s+(\d{1,2})\s*-\s*(?:[A-Za-z]{3,9}\s+)?(\d{1,2}),\s*(\d{4})$/);
  if (m) {
    const mIdx = MONTH_NAMES.findIndex(
      (nm) => nm.toLowerCase() === m[1].slice(0, 3).toLowerCase()
    );
    if (mIdx !== -1) {
      return {
        month: mIdx,
        startDay: parseInt(m[2], 10),
        endDay: parseInt(m[3], 10),
        year: parseInt(m[4], 10),
      };
    }
  }

  // Match "2026-06-A", "2026-06-B"
  const ym = trimmed.match(/^(\d{4})[-/](\d{1,2})(?:[-/]([abAB12]))?$/);
  if (ym) {
    const yr = parseInt(ym[1], 10);
    const mo = parseInt(ym[2], 10) - 1;
    const half = (ym[3] || "").toUpperCase();
    const lastD = new Date(yr, mo + 1, 0).getDate();
    return {
      month: mo,
      startDay: half === "B" || half === "2" ? 16 : 1,
      endDay: half === "B" || half === "2" ? lastD : 15,
      year: yr,
    };
  }

  return null;
}

const parsedActive = computed(() => parseCutoffPeriod(activeValue.value));

function initViewDate() {
  if (parsedActive.value) {
    viewYear.value = parsedActive.value.year;
    viewMonth.value = parsedActive.value.month;
  } else {
    viewYear.value = currentYear.value;
    viewMonth.value = currentMonth.value;
  }
}

watch(
  () => [props.referenceDate, activeValue.value],
  () => {
    initViewDate();
  },
  { immediate: true }
);

const daysInMonth = computed(() => {
  return new Date(viewYear.value, viewMonth.value + 1, 0).getDate();
});

const startWeekday = computed(() => {
  return new Date(viewYear.value, viewMonth.value, 1).getDay();
});

const canGoPrev = computed(() => {
  if (viewYear.value > currentYear.value) return true;
  if (viewYear.value === currentYear.value && viewMonth.value > currentMonth.value) {
    return true;
  }
  return false;
});

function prevMonth() {
  if (!canGoPrev.value) return;
  if (viewMonth.value === 0) {
    viewMonth.value = 11;
    viewYear.value -= 1;
  } else {
    viewMonth.value -= 1;
  }
}

function nextMonth() {
  if (viewMonth.value === 11) {
    viewMonth.value = 0;
    viewYear.value += 1;
  } else {
    viewMonth.value += 1;
  }
}

function isDaySelectable(day) {
  if (day !== 1 && day !== 16) return false;

  if (viewYear.value < currentYear.value) return false;
  if (viewYear.value > currentYear.value) return true;

  // Same year
  if (viewMonth.value < currentMonth.value) return false;
  if (viewMonth.value > currentMonth.value) return true;

  // Same year and same month
  if (day === 1) {
    // Current period ends on 15th
    return currentDay.value <= 15;
  }
  if (day === 16) {
    // Current period ends on end of month
    return currentDay.value <= daysInMonth.value;
  }

  return false;
}

function isDayInRange(day) {
  if (!parsedActive.value) return false;
  if (
    parsedActive.value.year === viewYear.value &&
    parsedActive.value.month === viewMonth.value
  ) {
    return day >= parsedActive.value.startDay && day <= parsedActive.value.endDay;
  }
  return false;
}

function isStartOrEndDay(day) {
  if (!parsedActive.value) return false;
  if (
    parsedActive.value.year === viewYear.value &&
    parsedActive.value.month === viewMonth.value
  ) {
    return day === parsedActive.value.startDay || day === parsedActive.value.endDay;
  }
  return false;
}

function selectCutoff(day) {
  if (!isDaySelectable(day)) return;

  const mName = MONTH_NAMES[viewMonth.value];
  let formatted = "";

  if (day === 1) {
    formatted = `${mName} 01 - ${mName} 15, ${viewYear.value}`;
  } else if (day === 16) {
    const lastDay = daysInMonth.value;
    formatted = `${mName} 16 - ${mName} ${lastDay}, ${viewYear.value}`;
  }

  emit("update:modelValue", formatted);
  emit("update:cutoffPeriod", formatted);
  isOpen.value = false;
}

function toggleOpen() {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    initViewDate();
  }
}

function handleClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    isOpen.value = false;
  }
}

onMounted(() => {
  if (typeof document !== "undefined") {
    document.addEventListener("click", handleClickOutside);
  }
});

onUnmounted(() => {
  if (typeof document !== "undefined") {
    document.removeEventListener("click", handleClickOutside);
  }
});
</script>

<template>
  <div ref="containerRef" class="relative w-full">
    <!-- Trigger input -->
    <button
      type="button"
      data-testid="cutoff-trigger"
      :disabled="disabled"
      class="input w-full flex items-center justify-between text-left cursor-pointer bg-white transition-colors"
      :class="[
        activeValue ? 'text-slate-700 font-medium' : 'text-slate-400',
        disabled ? 'opacity-60 cursor-not-allowed bg-slate-50' : 'hover:border-primary/50'
      ]"
      @click="toggleOpen"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
    >
      <div class="flex items-center gap-2.5 truncate">
        <Calendar class="w-4 h-4 text-slate-400 shrink-0" />
        <span class="truncate">{{ activeValue || "Select cutoff period" }}</span>
      </div>
      <ChevronDown
        class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
      />
    </button>

    <!-- Calendar Popover -->
    <div
      v-if="isOpen"
      data-testid="cutoff-calendar-popover"
      class="absolute left-0 top-full mt-2 z-50 w-72 sm:w-80 rounded-xl bg-white p-4 shadow-xl border border-slate-200/80 text-slate-700 animate-in fade-in zoom-in-95 duration-150"
    >
      <!-- Month Navigation Header -->
      <div class="flex items-center justify-between mb-3">
        <h4 class="font-bold text-sm text-slate-800">
          {{ MONTH_NAMES[viewMonth] }} {{ viewYear }}
        </h4>
        <div class="flex items-center gap-1">
          <button
            type="button"
            data-testid="prev-month-btn"
            :disabled="!canGoPrev"
            @click="prevMonth"
            class="p-1 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent"
            title="Previous month"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <button
            type="button"
            data-testid="next-month-btn"
            @click="nextMonth"
            class="p-1 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-100"
            title="Next month"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Quick helper note -->
      <div class="mb-3 rounded-md bg-slate-50 px-2.5 py-1.5 text-[11px] text-slate-500">
        Cutoff spans 15 days. Select either the <strong class="text-slate-700 font-semibold">1st</strong> (1st–15th) or <strong class="text-slate-700 font-semibold">16th</strong> (16th–end).
      </div>

      <!-- Weekday headers -->
      <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-slate-400 mb-1">
        <span v-for="wd in WEEK_DAYS" :key="wd" class="py-1">{{ wd }}</span>
      </div>

      <!-- Days Grid -->
      <div class="grid grid-cols-7 gap-1 text-center text-xs">
        <!-- Empty slots before month start -->
        <span
          v-for="blank in startWeekday"
          :key="`blank-${blank}`"
          class="h-8"
        />

        <!-- Day buttons -->
        <div
          v-for="day in daysInMonth"
          :key="`day-${day}`"
          class="relative flex items-center justify-center h-8"
          :class="[
            isDayInRange(day) ? 'bg-primary/10' : '',
            day === 1 || (parsedActive && day === parsedActive.startDay) ? 'rounded-l-lg' : '',
            day === daysInMonth || (parsedActive && day === parsedActive.endDay) ? 'rounded-r-lg' : '',
          ]"
        >
          <button
            type="button"
            :data-testid="`day-btn-${day}`"
            :disabled="!isDaySelectable(day)"
            @click="selectCutoff(day)"
            class="w-7 h-7 rounded-full flex items-center justify-center text-xs transition-all relative z-10"
            :class="[
              isStartOrEndDay(day)
                ? 'bg-primary text-white font-bold shadow-sm scale-105'
                : isDaySelectable(day)
                ? 'border border-primary/40 text-primary font-bold hover:bg-primary hover:text-white cursor-pointer hover:shadow-sm'
                : isDayInRange(day)
                ? 'text-primary font-medium cursor-default'
                : 'text-slate-300 cursor-not-allowed'
            ]"
          >
            {{ day }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
