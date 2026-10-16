import animate from 'tailwindcss-animate'

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}'
  ],
  theme: {
    extend: {
      colors: {
        border: '#E2E8F0',
        input: '#E2E8F0',
        ring: '#2E85D8',
        background: '#F8F9FA',
        foreground: '#1E293B',
        primary: {
          DEFAULT: '#252578',
          foreground: '#FFFFFF',
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
          DEFAULT: '#2E85D8',
          foreground: '#FFFFFF',
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
        brand: {
          deep: '#3A3A85',
          'deep-border': '#4E4E91',
        },
        destructive: {
          DEFAULT: '#EF4444',
          foreground: '#FFFFFF',
        },
        success: {
          DEFAULT: '#10B981',
          foreground: '#FFFFFF',
        },
        warning: {
          DEFAULT: '#F59E0B',
          foreground: '#FFFFFF',
        },
        muted: {
          DEFAULT: '#F1F5F9',
          foreground: '#8D8B8F',
        },
        accent: {
          DEFAULT: '#2E85D8',
          foreground: '#FFFFFF',
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
        card: {
          DEFAULT: '#FFFFFF',
          foreground: '#1E293B',
        },
        popover: {
          DEFAULT: '#FFFFFF',
          foreground: '#1E293B',
        },
        clinical: '#F8F9FA',
        sterile: '#FFFFFF',
        slate: {
          300: '#94A3B8',
          400: '#64748B',
          500: '#475569',
          600: '#334155',
          700: '#1E293B',
          800: '#0F172A',
          900: '#020617',
        },
        danger: '#EF4444',
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
        card: '14px',
      },
      boxShadow: {
        sm: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
        md: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06)',
        lg: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05)',
        '2xl': '0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.08)',
        'xl-soft': '0 24px 72px -48px rgba(37,37,120,0.5)',
      },
      fontFamily: {
        sans: ['Poppins', 'Montserrat', 'Open Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        heading: ['Poppins', 'Montserrat', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Montserrat', 'Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      }
    }
  },
  plugins: [animate]
}
