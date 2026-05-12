<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Eye, EyeOff, LogIn, FlaskConical } from 'lucide-vue-next'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

// OTP Mechanics
const step = ref('CREDENTIALS') // CREDENTIALS | OTP
const otpCode = ref('')
const countdown = ref(0)
let timerInterval = null

function beginCountdown() {
  countdown.value = 60
  clearInterval(timerInterval)
  timerInterval = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) clearInterval(timerInterval)
  }, 1000)
}

async function requestAuth() {
  if (!form.email || !form.password) {
    error.value = 'Please enter your email and password.'
    return
  }
  loading.value = true
  error.value = ''
  
  // Simulate auth check before OTP
  setTimeout(() => {
    loading.value = false
    step.value = 'OTP'
    beginCountdown()
  }, 800)
}

async function verifyOtp() {
  if (otpCode.value.length < 6) {
    error.value = 'OTP must be 6 digits.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await auth.login(form.email, form.password)
    const redirect = route.query.redirect || '/dashboard'
    router.push(redirect)
  } catch (e) {
    error.value = e.message || 'Login failed.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex bg-clinical font-sans">
    <!-- Left Panel — Corporate Branding -->
    <div class="hidden lg:flex lg:w-1/2 flex-col justify-between bg-primary p-12 lg:p-20 border-r border-white/10 relative overflow-hidden">
      <!-- Minimalist geometric background accent -->
      <div class="absolute inset-0 opacity-5">
        <div class="absolute top-0 right-0 w-96 h-96 border border-white rounded-none -translate-y-1/2 translate-x-1/2 rotate-45"></div>
        <div class="absolute bottom-0 left-0 w-full h-[1px] bg-white opacity-20"></div>
      </div>

      <div class="flex items-center gap-3 relative z-10">
        <div class="w-10 h-10 border border-white/20 flex items-center justify-center bg-white/5">
          <FlaskConical class="w-5 h-5 text-white" />
        </div>
        <span class="text-white font-bold text-xl tracking-widest uppercase">SERMS</span>
      </div>

      <div class="max-w-lg relative z-10">
        <div class="w-12 h-1 bg-white/20 mb-8" />
        <h1 class="text-4xl xl:text-5xl font-bold text-white tracking-tight uppercase leading-tight mb-6">
          Smart Expense<br />
          <span class="text-white/50">& Reimbursement</span><br />
          <span class="text-white/20">Management System</span>
        </h1>
        <p class="text-white/60 text-base leading-relaxed font-medium">
          Modern workflow automation for enterprise finance teams. Streamlined, precise, and secure.
        </p>
      </div>

      <div class="flex items-center gap-4 pt-8 border-t border-white/10 mt-12 relative z-10">
        <div class="w-2 h-2 bg-success rounded-none"></div>
        <span class="text-white/40 text-[10px] font-bold uppercase tracking-widest">System Online — Connected to Core Services</span>
      </div>
    </div>

    <!-- Right Panel — Authentication Entry -->
    <div class="flex flex-1 items-center justify-center p-6 lg:p-12">
      <div class="w-full max-w-sm">
        <!-- Mobile logo -->
        <div class="flex items-center gap-2 mb-12 lg:hidden">
          <div class="w-8 h-8 bg-primary flex items-center justify-center">
            <FlaskConical class="w-4 h-4 text-white" />
          </div>
          <span class="text-primary font-bold text-base tracking-widest uppercase">SERMS</span>
        </div>

        <div class="mb-10 p-5 border border-slate-200 border-l-2 border-l-primary bg-white rounded-none shadow-none">
          <h2 class="text-sm font-bold text-primary uppercase tracking-widest">{{ step === 'OTP' ? 'MFA VERIFICATION' : 'Authentication Required' }}</h2>
          <p class="text-slate-500 text-xs mt-1.5 font-medium">
            {{ step === 'OTP' ? 'Enter the 6-digit secure code sent to your device.' : 'Please provide credentials to access the system console' }}
          </p>
        </div>

        <form @submit.prevent="step === 'OTP' ? verifyOtp() : requestAuth()" class="flex flex-col gap-6">
          <!-- Error banner -->
          <Transition name="fade">
            <div v-if="error" class="bg-danger/5 border border-danger/20 p-3 text-[11px] font-bold uppercase tracking-wider text-danger rounded-none">
              [SYSTEM ERROR]: {{ error }}
            </div>
          </Transition>

          <template v-if="step === 'CREDENTIALS'">
            <!-- Email -->
            <div class="input-wrapper">
              <label class="input-label" for="login-email">Console ID (Email)</label>
              <input
                id="login-email"
                v-model="form.email"
                type="email"
                autocomplete="email"
                class="input font-sans"
                placeholder="user@enterprise.net"
              />
            </div>

            <!-- Password -->
            <div class="input-wrapper mt-2">
              <div class="flex items-center justify-between">
                <label class="input-label" for="login-password">Access Key</label>
                <a href="#" class="text-[10px] font-bold uppercase tracking-widest text-primary hover:text-primary-600 transition-colors">Recovery?</a>
              </div>
              <div class="relative">
                <input
                  id="login-password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  class="input pr-10 font-sans"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-primary transition-colors"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
            </div>
          </template>

          <template v-else-if="step === 'OTP'">
            <!-- OTP Input -->
            <div class="input-wrapper">
              <label class="input-label" for="login-otp">SECURE OTP TOKEN</label>
              <input
                id="login-otp"
                v-model="otpCode"
                type="text"
                maxlength="6"
                class="input font-mono text-center tracking-[0.5em] text-lg py-3"
                placeholder="000000"
              />
            </div>
            <div class="flex justify-between items-center mt-1">
              <button 
                type="button" 
                class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-primary transition-colors"
                @click="step = 'CREDENTIALS'; otpCode = '';"
              >
                &larr; BACK
              </button>
              
              <button 
                type="button" 
                class="text-[10px] font-bold uppercase tracking-widest disabled:opacity-50 disabled:cursor-not-allowed"
                :class="countdown > 0 ? 'text-slate-400' : 'text-primary hover:text-primary-700'"
                :disabled="countdown > 0"
                @click="beginCountdown"
              >
                {{ countdown > 0 ? `RESEND IN ${countdown}S` : 'RESEND OTP' }}
              </button>
            </div>
          </template>

          <!-- Submit -->
          <button
            id="login-submit"
            type="submit"
            :disabled="loading"
            class="btn btn-primary w-full py-3 mt-4"
          >
            <svg v-if="loading" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <LogIn v-else class="w-4 h-4" />
            <span class="tracking-widest">{{ loading ? 'AUTHORIZING…' : (step === 'OTP' ? 'VERIFY TOKEN' : 'INITIALIZE SESSION') }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s linear; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
