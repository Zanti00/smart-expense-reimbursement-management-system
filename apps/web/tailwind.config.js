/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}'
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#252578',
          50: '#F8FAFC', // Sterile neutral
          100: '#E8EBF5',
          200: '#C9D1E6',
          300: '#9FB0D1',
          400: '#6B8BB8',
          500: '#252578',
          600: '#1D1D61',
          700: '#16164A',
          800: '#0E0E33',
          900: '#07071D',
        },
        clinical: '#F8FAFC',
        sterile: '#FFFFFF',
        slate: {
          300: '#94A3B8', // Previously 400
          400: '#64748B', // Previously 500
          500: '#475569', // Previously 600
          600: '#334155', // Previously 700
          700: '#1E293B', // Previously 800 (Deep Charcoal)
          800: '#0F172A', // Previously 900
          900: '#020617', // Near Black
        },
        success: '#059669',
        warning: '#D97706',
        danger: '#DC2626',
        ocr: {
          alert: '#FEF9C3',
        },
        neutral: '#334155',
        'slate-600': '#334155', // Match definition
      },
      fontFamily: {
        sans: ['Inter', 'Public Sans', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },
      boxShadow: {
        sm: '0 1px 2px 0 rgba(37,37,120,0.05)',
        card: '0 1px 3px 0 rgba(37,37,120,0.08)',
        'card-hover': '0 2px 8px 0 rgba(37,37,120,0.1)',
      },
      borderRadius: {
        card: '0.125rem', // sm
        sm: '0.125rem',
      }
    }
  },
  plugins: []
}
