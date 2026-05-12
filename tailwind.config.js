/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./templates/**/*.{html,twig}", "./public_html/**/*.{html,js}"],
  theme: {
    extend: {
      colors: {
        primary: '#25d366',
        dark: '#111111',
        darker: '#0a0a0a',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
      }
    },
  },
  plugins: [],
}