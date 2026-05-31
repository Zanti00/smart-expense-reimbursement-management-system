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
          50: '#F4F5FF',
          100: '#E6E8FF',
          200: '#C8CCFF',
          300: '#9EA5F8',
          400: '#676DCD',
          500: '#252578',
          600: '#20206C',
          700: '#1A1A59',
          800: '#11113F',
          900: '#090923',
        },
        secondary: {
          DEFAULT: '#2F2F7E',
          50: '#F4F4FF',
          100: '#E8E8FF',
          200: '#CBCBFF',
          300: '#A2A2F4',
          400: '#6C6CCD',
          500: '#2F2F7E',
          600: '#28286F',
          700: '#20205B',
          800: '#171746',
          900: '#0B0B26',
        },
        accent: {
          DEFAULT: '#2E85D8',
          50: '#EFF7FF',
          100: '#DCEEFF',
          200: '#B9DCFA',
          300: '#86C2F4',
          400: '#54A5EA',
          500: '#2E85D8',
          600: '#1F6EBB',
          700: '#185695',
          800: '#153F6C',
          900: '#102A47',
        },
        clinical: '#F7F9FD',
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
        sans: ['Open Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        heading: ['Poppins', 'Montserrat', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Montserrat', 'Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },
      boxShadow: {
        sm: '0 1px 2px 0 rgba(0,0,0,0.05)',
        'xl-soft': '0 24px 72px -48px rgba(37,37,120,0.5)',
      },
      borderRadius: {
        card: '0.5rem',
        sm: '0.375rem',
      }
    }
  },
  plugins: []
}
