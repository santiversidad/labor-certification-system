import type { Config } from 'tailwindcss';

export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: 'rgb(var(--color-primary) / <alpha-value>)',
        'primary-hover': 'rgb(var(--color-primary-hover) / <alpha-value>)',
        secondary: 'rgb(var(--color-secondary) / <alpha-value>)',
        success: 'rgb(var(--color-success) / <alpha-value>)',
        warning: 'rgb(var(--color-warning) / <alpha-value>)',
        error: 'rgb(var(--color-error) / <alpha-value>)',
        info: 'rgb(var(--color-info) / <alpha-value>)',
        background: 'rgb(var(--color-background) / <alpha-value>)',
        surface: 'rgb(var(--color-surface) / <alpha-value>)',
        'surface-muted': 'rgb(var(--color-surface-muted) / <alpha-value>)',
        border: 'rgb(var(--color-border) / <alpha-value>)',
        text: 'rgb(var(--color-text-primary) / <alpha-value>)',
        muted: 'rgb(var(--color-text-secondary) / <alpha-value>)',
        govBlue: 'rgb(var(--color-primary) / <alpha-value>)',
        govBlueDark: 'rgb(var(--color-primary-hover) / <alpha-value>)',
        villavoGreen: 'rgb(var(--color-success) / <alpha-value>)',
        villavoRed: 'rgb(var(--color-error) / <alpha-value>)',
        gold: 'rgb(var(--color-warning) / <alpha-value>)',
      },
      fontFamily: {
        sans: ['Work Sans', 'Inter', 'Segoe UI', 'Arial', 'sans-serif'],
      },
      borderRadius: {
        sm: 'var(--radius-sm)',
        DEFAULT: 'var(--radius-md)',
        md: 'var(--radius-md)',
        lg: 'var(--radius-lg)',
        xl: 'var(--radius-xl)',
      },
      boxShadow: {
        soft: 'var(--shadow-soft)',
        raised: 'var(--shadow-raised)',
      },
      maxWidth: {
        content: '90rem',
      },
      keyframes: {
        'fade-up': {
          from: { opacity: '0', transform: 'translateY(8px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
        'slide-in': {
          from: { opacity: '0', transform: 'translateX(-12px)' },
          to: { opacity: '1', transform: 'translateX(0)' },
        },
      },
      animation: {
        'fade-up': 'fade-up 320ms cubic-bezier(.2,.8,.2,1) both',
        'slide-in': 'slide-in 240ms cubic-bezier(.2,.8,.2,1) both',
      },
    },
  },
  plugins: [],
} satisfies Config;
