import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './app/**/*.{js,ts,jsx,tsx,mdx}',
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './lib/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        athletica: {
          bg: '#0a0a0a',
          card: '#111111',
          surface: '#161616',
          hover: '#1a1a1a',
          input: '#101010',
          subtle: '#1e1e1e',
          border: '#262626',
          strong: '#333333',
          muted: '#888888',
          secondary: '#b0b0b0',
          white: '#ffffff',
          red: '#ef4444',
          green: '#10b981',
          amber: '#f59e0b',
          blue: '#3b82f6',
          whatsapp: '#25d366',
        },
      },
      fontFamily: {
        sans: ['var(--font-nunito-sans)', 'system-ui', 'sans-serif'],
      },
      keyframes: {
        marquee: {
          '0%': { transform: 'translateX(0%)' },
          '100%': { transform: 'translateX(-50%)' },
        },
      },
      animation: {
        marquee: 'marquee 25s linear infinite',
      },
    },
  },
  plugins: [],
};

export default config;
