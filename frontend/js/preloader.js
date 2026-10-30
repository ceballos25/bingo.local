/**
 * Preloader de arranque y de cambio de ruta.
 */

let hideTimer = null;

export function showBootPreloader() {
  const el = document.getElementById('bootPreloader');
  if (!el) return;
  el.classList.remove('is-done', 'd-none');
  el.setAttribute('aria-busy', 'true');
}

export function hideBootPreloader() {
  const el = document.getElementById('bootPreloader');
  if (!el || el.classList.contains('is-done')) return;

  el.classList.add('is-done');
  el.setAttribute('aria-busy', 'false');

  window.clearTimeout(hideTimer);
  hideTimer = window.setTimeout(() => {
    el.classList.add('d-none');
  }, 420);
}

export function showRoutePreloader() {
  const el = document.getElementById('routePreloader');
  if (!el) return;
  el.classList.remove('d-none');
  el.setAttribute('aria-busy', 'true');
}

export function hideRoutePreloader() {
  const el = document.getElementById('routePreloader');
  if (!el) return;
  el.classList.add('d-none');
  el.setAttribute('aria-busy', 'false');
  el.setAttribute('aria-hidden', 'true');
}
