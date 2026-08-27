// =============================================
// SAKURA FLOATING PETALS
// =============================================

(function() {
  const container = document.getElementById('petals');
  if (!container) return;

  const PETAL_SVG = `<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
    <path d="M10 2 C10 2, 14 6, 14 10 C14 14, 10 18, 10 18 C10 18, 6 14, 6 10 C6 6, 10 2, 10 2Z" opacity="0.7"/>
  </svg>`;

  function createPetal() {
    const petal = document.createElement('div');
    petal.className = 'petal';
    petal.innerHTML = PETAL_SVG;

    const size     = Math.random() * 10 + 6;
    const startX   = Math.random() * 100;
    const duration = Math.random() * 12 + 10;
    const delay    = Math.random() * 15;

    petal.style.cssText = `
      left: ${startX}vw;
      top: -20px;
      width: ${size}px;
      height: ${size}px;
      animation-duration: ${duration}s;
      animation-delay: ${delay}s;
    `;

    container.appendChild(petal);

    // Remove after animation
    setTimeout(() => petal.remove(), (duration + delay) * 1000 + 500);
  }

  // Create initial petals
  for (let i = 0; i < 8; i++) {
    setTimeout(createPetal, i * 800);
  }

  // Keep spawning petals
  setInterval(createPetal, 2500);
})();
