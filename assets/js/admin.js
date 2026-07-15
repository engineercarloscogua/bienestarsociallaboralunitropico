// assets/js/admin.js — Admin Panel JS (minimal, no dependencies)

document.addEventListener('DOMContentLoaded', () => {
  // Auto-dismiss alerts after 4 seconds
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.4s ease';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 400);
    }, 4000);
  });
});
