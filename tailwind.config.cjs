// Match the former v3 Play CDN configuration. Scan full PHP/JS tokens,
// including conditional status strings and classes in AJAX-rendered partials.
module.exports = {
  content: {
    relative: true,
    files: ['./*.php', './app/**/*.php', './includes/**/*.php', './api/**/*.php', './assets/js/**/*.js']
  },
  darkMode: 'class',
  theme: {
    extend: {
      colors: { paleco: {
        50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 500: '#22c55e',
        600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d'
      } },
      boxShadow: { card: '0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04)' }
    }
  },
  plugins: []
};
