/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: '#1a1a1a',
        secondary: '#d4af37',
        accent: '#8b0000',
        'bg-dark': '#000000',
        'bg-light': '#ffffff',
        'text-primary': '#ffffff',
        'text-secondary': '#e0e0e0',
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        gold: {
          300: '#e8c84a',
          400: '#d4af37',
          500: '#c9a82e',
        },
      },
      fontFamily: {
        body: ['Georgia', 'serif'],
        headings: ['"Playfair Display"', 'Georgia', 'serif'],
      },
    },
  },
  plugins: [],
};
