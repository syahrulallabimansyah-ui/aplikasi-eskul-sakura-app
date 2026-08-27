/* ─── SAKURA THEME TOGGLE ─── */
(function () {
  const KEY = 'sakura_theme';

  function applyTheme(theme) {
    if (theme === 'light') {
      document.body.classList.add('light-mode');
    } else {
      document.body.classList.remove('light-mode');
    }
    // update icon for all toggle buttons
    document.querySelectorAll('.theme-toggle').forEach(btn => {
      btn.textContent = theme === 'light' ? '🌙' : '☀️';
      btn.title = theme === 'light' ? 'Mode Gelap' : 'Mode Terang';
    });
  }

  function toggleTheme() {
    const current = localStorage.getItem(KEY) || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem(KEY, next);
    applyTheme(next);
  }

  // Apply saved theme immediately (before paint)
  const saved = localStorage.getItem(KEY) || 'dark';
  applyTheme(saved);

  // Expose globally
  window.toggleTheme = toggleTheme;

  // Re-apply after DOM ready (for button icons)
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme(localStorage.getItem(KEY) || 'dark');
  });
})();
