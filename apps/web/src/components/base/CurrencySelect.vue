<script setup>
import { ref, computed, watch } from "vue";
import { ChevronDown } from "lucide-vue-next";
import { SUPPORTED_CURRENCIES, getCurrencySymbol } from "@/utils/formatters";

const props = defineProps({
  modelValue: {
    type: String,
    default: "PHP",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  placeholder: {
    type: String,
    default: "Select currency",
  },
  selectClass: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue"]);

const isCustom = ref(false);
const customCode = ref("");
const selectedStandard = ref("PHP");

const standardCodes = computed(() => SUPPORTED_CURRENCIES.map((c) => c.code));

watch(
  () => props.modelValue,
  (newVal) => {
    const val = (newVal || "").trim().toUpperCase();
    if (!val || standardCodes.value.includes(val)) {
      isCustom.value = false;
      selectedStandard.value = val || "PHP";
    } else {
      isCustom.value = true;
      selectedStandard.value = "CUSTOM";
      customCode.value = val.slice(0, 3);
    }
  },
  { immediate: true },
);

function handleSelectChange(event) {
  const val = event.target.value;
  if (val === "CUSTOM") {
    isCustom.value = true;
    selectedStandard.value = "CUSTOM";
    if (customCode.value.length === 3) {
      emit("update:modelValue", customCode.value);
    }
  } else {
    isCustom.value = false;
    selectedStandard.value = val;
    emit("update:modelValue", val);
  }
}

function handleCustomInput(event) {
  // Allow exact 3 letters only (A-Z)
  let raw = event.target.value.toUpperCase().replace(/[^A-Z]/g, "");
  if (raw.length > 3) {
    raw = raw.slice(0, 3);
  }
  customCode.value = raw;
  event.target.value = raw;

  if (raw.length === 3) {
    emit("update:modelValue", raw);
  }
}

function switchToSelect() {
  isCustom.value = false;
  selectedStandard.value = standardCodes.value.includes(props.modelValue)
    ? props.modelValue
    : "PHP";
  emit("update:modelValue", selectedStandard.value);
}

const currentSymbol = computed(() => {
  const val = isCustom.value ? customCode.value : (props.modelValue || "PHP");
  return getCurrencySymbol(val);
});

const isValidCustomCode = computed(() => {
  return customCode.value.length === 3;
});
</script>

<template>
  <div class="flex items-center gap-2">
    <!-- Standard Dropdown Mode -->
    <template v-if="!isCustom">
      <div class="relative flex-1 min-w-[140px]">
        <select
          class="input appearance-none cursor-pointer pr-8 font-mono text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
          :class="selectClass"
          :value="selectedStandard"
          :disabled="disabled"
          @change="handleSelectChange"
        >
          <option
            v-for="c in SUPPORTED_CURRENCIES"
            :key="c.code"
            :value="c.code"
          >
            ({{ c.symbol }}) {{ c.code }} — {{ c.name }}
          </option>
          <option value="CUSTOM">+ Other (Enter 3-Letter Code)...</option>
        </select>
        <ChevronDown
          class="w-4 h-4 absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
        />
      </div>
    </template>

    <!-- Custom 3-Letter Currency Code Input Mode -->
    <template v-else>
      <div class="flex flex-col gap-1 flex-1 min-w-[150px]">
        <div class="flex items-center gap-1.5">
          <!-- Symbol preview badge -->
          <span
            class="inline-flex items-center justify-center min-w-[32px] px-2 py-1.5 rounded-lg bg-accent-50 border border-accent/20 text-xs font-black text-accent font-mono flex-shrink-0 shadow-xs"
            title="Currency Symbol Sign"
          >
            {{ isValidCustomCode ? currentSymbol : '?' }}
          </span>

          <!-- 3-character exact input field -->
          <div class="relative flex-1">
            <input
              type="text"
              class="input font-mono font-bold uppercase tracking-wider text-sm !py-1.5 !px-2.5 w-full disabled:opacity-50 disabled:cursor-not-allowed"
              :class="[
                selectClass,
                !isValidCustomCode && customCode.length > 0
                  ? '!border-amber-400 focus:!border-amber-500'
                  : isValidCustomCode
                    ? '!border-emerald-500 focus:!border-emerald-600'
                    : ''
              ]"
              :value="customCode"
              :disabled="disabled"
              maxlength="3"
              placeholder="CAD"
              @input="handleCustomInput"
            />
          </div>

          <!-- Switch back to standard dropdown -->
          <button
            type="button"
            class="btn btn-secondary !p-1.5 text-xs text-slate-500 hover:text-primary flex-shrink-0"
            title="Switch back to currency dropdown choices"
            :disabled="disabled"
            @click="switchToSelect"
          >
            <ChevronDown class="w-4 h-4" />
          </button>
        </div>

        <!-- Length validation helper -->
        <span
          v-if="customCode.length > 0 && customCode.length < 3"
          class="text-[10px] font-bold text-amber-600 tracking-tight"
        >
          Must be exactly 3 characters ({{ customCode.length }}/3)
        </span>
      </div>
    </template>
  </div>
</template>
