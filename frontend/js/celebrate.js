/**
 * Celebración full-screen al cantar L / Equis / Bingo.
 * Logo + confeti + texto épico.
 */

import { CONFIG } from './config.js';

const LABELS = {
  linea: '¡LÍNEA!',
  equis: '¡EQUIS!',
  completa: '¡BINGO!',
};

const SUBS = {
  linea: 'L · línea',
  equis: 'X · equis',
  completa: 'Tabla completa',
};

const COLORS = [
  '#ffcc00',
  '#e60000',
  '#003399',
  '#00994c',
  '#ffffff',
  '#ff6b00',
  '#ff4fd8',
];

let activeTimer = null;
/** Evita doble fiesta (quien cantó + SSE al resto de hosts). */
let ultimaCantadaCelebradaId = null;

function preferReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function spawnConfetti(layer, count) {
  const frag = document.createDocumentFragment();
  for (let i = 0; i < count; i++) {
    const p = document.createElement('span');
    p.className = 'bingo-celebrate__piece';
    const left = Math.random() * 100;
    const delay = Math.random() * 0.45;
    const dur = 1.6 + Math.random() * 1.4;
    const size = 6 + Math.random() * 10;
    const rot = Math.random() * 720 - 360;
    const drift = (Math.random() - 0.5) * 180;
    const shape = Math.random() > 0.55 ? 'rect' : 'circle';
    p.style.setProperty('--x', `${left}vw`);
    p.style.setProperty('--delay', `${delay}s`);
    p.style.setProperty('--dur', `${dur}s`);
    p.style.setProperty('--size', `${size}px`);
    p.style.setProperty('--rot', `${rot}deg`);
    p.style.setProperty('--drift', `${drift}px`);
    p.style.setProperty('--color', COLORS[i % COLORS.length]);
    p.dataset.shape = shape;
    frag.appendChild(p);
  }
  layer.appendChild(frag);
}

/**
 * @param {string} modalidad linea | equis | completa
 * @param {{ mesa?: boolean, sub?: string, reclamoId?: number|string, forzar?: boolean }} [opts]
 * @returns {boolean} true si se mostró (para disparar sonido en todos los hosts)
 */
export function celebrarCantada(modalidad, opts = {}) {
  const reclamoId = Number(opts.reclamoId) || 0;
  if (!opts.forzar && reclamoId > 0 && ultimaCantadaCelebradaId === reclamoId) {
    return false;
  }
  if (reclamoId > 0) {
    ultimaCantadaCelebradaId = reclamoId;
  }

  const key = String(modalidad || '').toLowerCase();
  const label = LABELS[key] || '¡BINGO!';
  const tipo = SUBS[key] || 'Cantada';
  const sub =
    opts.sub ||
    (opts.mesa ? `¡Cantaron ${tipo}!` : `¡${tipo} en vivo!`);
  const reduced = preferReducedMotion();

  document.getElementById('bingoCelebrate')?.remove();
  if (activeTimer) {
    clearTimeout(activeTimer);
    activeTimer = null;
  }

  const root = document.createElement('div');
  root.id = 'bingoCelebrate';
  root.className = `bingo-celebrate bingo-celebrate--${key || 'completa'}`;
  root.setAttribute('role', 'status');
  root.setAttribute('aria-live', 'assertive');
  root.innerHTML = `
    <div class="bingo-celebrate__flash" aria-hidden="true"></div>
    <div class="bingo-celebrate__confetti" aria-hidden="true"></div>
    <div class="bingo-celebrate__stage">
      <img class="bingo-celebrate__logo" src="${CONFIG.logoPath || './icons/logo.png'}" alt="" width="220" height="220">
      <p class="bingo-celebrate__label font-brand">${label}</p>
      <p class="bingo-celebrate__sub">${sub}</p>
    </div>
  `;

  document.body.appendChild(root);

  const confetti = root.querySelector('.bingo-celebrate__confetti');
  if (confetti && !reduced) {
    spawnConfetti(confetti, key === 'completa' ? 140 : key === 'equis' ? 110 : 90);
  }

  try {
    if (navigator.vibrate) {
      navigator.vibrate(key === 'completa' ? [40, 60, 40, 60, 120] : [30, 50, 80]);
    }
  } catch (_) {
    /* ignore */
  }

  void root.offsetWidth;
  root.classList.add('is-on');

  const ms = reduced ? 1600 : key === 'completa' ? 3800 : 3200;
  activeTimer = window.setTimeout(() => {
    root.classList.remove('is-on');
    root.classList.add('is-out');
    window.setTimeout(() => root.remove(), 380);
    activeTimer = null;
  }, ms);

  return true;
}
