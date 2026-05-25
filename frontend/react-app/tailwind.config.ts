import type { Config } from 'tailwindcss';

export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        govBlue: '#004884',
        govBlueDark: '#003366',
        villavoGreen: '#168A45',
        villavoRed: '#C62828',
        gold: '#F8C630',
        background: '#F5F7FA',
        surface: '#FFFFFF',
        text: '#222222',
        muted: '#666666',
        border: '#DDE3EA',
      },
    },
  },
  plugins: [],
} satisfies Config;
