// Mobile drawer menu toggle
function toggleMobileMenu() {
  const menu = document.getElementById('topbarRight');
  const overlay = document.getElementById('menuOverlay');
  if (!menu || !overlay) return;
  const isOpen = menu.classList.toggle('open');
  overlay.classList.toggle('open', isOpen);
  document.body.style.overflow = isOpen ? 'hidden' : '';
}

function closeMobileMenu() {
  const menu = document.getElementById('topbarRight');
  const overlay = document.getElementById('menuOverlay');
  if (!menu || !overlay) return;
  menu.classList.remove('open');
  overlay.classList.remove('open');
  document.body.style.overflow = '';
}
