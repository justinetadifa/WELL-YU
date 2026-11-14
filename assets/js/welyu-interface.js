/* Shared presentation behavior. No network calls or database writes. */
(() => {
  'use strict';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const cards = document.querySelectorAll('.welyu-hero-copy, .welyu-discovery-strip');
  if (!reduce.matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          entry.target.classList.add('welyu-is-visible');
          observer.unobserve(entry.target);
        }
      }
    }, { threshold: 0.12 });
    cards.forEach(card => observer.observe(card));
  }
})();
