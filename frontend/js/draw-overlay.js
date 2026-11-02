/**
 * Sorteo: overlay de revelado + bombo SVG en mesa.
 * Tiempos en CONFIG.sorteo (admin y jugadores alineados).
 * Cola: si llegan balotas mientras gira, no se pierden; se confirma UI y solo se anima la última.
 */
import { CONFIG } from './config.js';
import { renderBalotaHtml } from './balota.js';
import { sonarBalota, sonarMezcla } from './sound.js';

let sorteoEnCurso = false;
let ultimaBalotaAnimada = null;
/** @type {{ balota: object, onDone?: () => void, opts: object }[]} */
const cola = [];
/** @type {number[]} */
let timers = [];

function later(fn, ms) {
  const id = window.setTimeout(() => {
    timers = timers.filter((t) => t !== id);
    fn();
  }, ms);
  timers.push(id);
  return id;
}

function clearTimers() {
  timers.forEach((id) => window.clearTimeout(id));
  timers = [];
}

export function getUltimaBalotaAnimada() {
  return ultimaBalotaAnimada;
}

export function marcarBalotaYaVista(numero) {
  const n = Number(numero);
  if (Number.isFinite(n) && n > 0) {
    ultimaBalotaAnimada = n;
  }
}

export function cleanupDrawOverlay() {
  clearTimers();
  while (cola.length) {
    const item = cola.shift();
    try {
      item?.onDone?.();
    } catch (_) {
      /* ignore */
    }
  }
  document.getElementById('drawOverlay')?.remove();
  const cage = document.getElementById('mesaDomeCage');
  if (cage) {
    cage.classList.remove('bingo-cage--spin');
    cage.style.removeProperty('--cage-spin');
  }
  const eject = document.getElementById('mesaDomeEject');
  if (eject) {
    eject.classList.remove('bingo-cage__exit--out', 'bingo-cage__exit--rest');
    eject.innerHTML = '';
  }
  sorteoEnCurso = false;
}

/** Bombo SVG (sin texto debajo). */
export function renderBingoDomeHtml(lastBalota = null) {
  return `
    <div class="bingo-cage" id="mesaDome" aria-label="Bombolotera">
      <div class="bingo-cage__visual" id="mesaDomeCage">
        <svg class="bingo-cage__svg" viewBox="0 0 160 150" aria-hidden="true">
          <defs>
            <radialGradient id="cageShade" cx="35%" cy="30%" r="65%">
              <stop offset="0%" stop-color="#3a3a3a"/>
              <stop offset="100%" stop-color="#111"/>
            </radialGradient>
          </defs>
          <path d="M48 118 L40 148 M112 118 L120 148" stroke="#1a1a1a" stroke-width="3.5" stroke-linecap="round"/>
          <ellipse cx="80" cy="148" rx="42" ry="4" fill="rgba(0,0,0,.12)"/>
          <line x1="18" y1="72" x2="142" y2="72" stroke="#222" stroke-width="4" stroke-linecap="round"/>
          <path d="M142 72 h12 v-16" fill="none" stroke="#1a1a1a" stroke-width="3.5" stroke-linecap="round"/>
          <circle cx="154" cy="52" r="4.5" fill="#1a1a1a"/>
          <g class="bingo-cage__rotor">
            <circle cx="80" cy="72" r="46" fill="url(#cageShade)" stroke="#0a0a0a" stroke-width="3"/>
            <ellipse cx="80" cy="72" rx="46" ry="18" fill="none" stroke="#cfcfcf" stroke-width="1.4" opacity=".55"/>
            <ellipse cx="80" cy="72" rx="18" ry="46" fill="none" stroke="#cfcfcf" stroke-width="1.4" opacity=".55"/>
            <ellipse cx="80" cy="72" rx="38" ry="30" fill="none" stroke="#bdbdbd" stroke-width="1.1" opacity=".4" transform="rotate(35 80 72)"/>
            <ellipse cx="80" cy="72" rx="38" ry="30" fill="none" stroke="#bdbdbd" stroke-width="1.1" opacity=".4" transform="rotate(-35 80 72)"/>
            <circle cx="62" cy="78" r="7" fill="#003399"/>
            <circle cx="78" cy="70" r="8" fill="#e60000"/>
            <circle cx="94" cy="80" r="7" fill="#00994c"/>
            <circle cx="72" cy="88" r="6.5" fill="#ffcc00"/>
            <circle cx="88" cy="66" r="6" fill="#5b2c6f"/>
            <circle cx="84" cy="90" r="5.5" fill="#003399"/>
          </g>
          <path d="M68 112 h24 v8 a12 8 0 0 1 -24 0 z" fill="#1a1a1a" stroke="#000" stroke-width="1"/>
        </svg>
        <div class="bingo-cage__exit" id="mesaDomeEject" aria-hidden="true"></div>
      </div>
    </div>`;
}

/**
 * Misma duración/velocidad en admin y jugadores.
 * Si CONFIG.sorteo.variar: el número de la balota decide el matiz (no Math.random).
 */
function timingSorteo(numero) {
  const cfg = CONFIG.sorteo || {};
  let giroMs = Number(cfg.giroMs);
  let periodo = Number(cfg.periodoSeg);
  let holdMs = Number(cfg.holdMs);

  if (!Number.isFinite(giroMs) || giroMs <= 0) giroMs = 2800;
  if (!Number.isFinite(periodo) || periodo <= 0) periodo = 0.45;
  if (!Number.isFinite(holdMs) || holdMs <= 0) holdMs = 1400;

  if (cfg.variar !== false) {
    const n = Number(numero) || 1;
    const seed = (Math.abs(Math.trunc(n)) * 17) % 5; // 0..4, igual en todas las pantallas
    giroMs += (seed - 2) * 350;
    periodo += (seed - 2) * 0.06;
  }

  return {
    giroMs: Math.max(900, Math.round(giroMs)),
    periodo: Math.max(0.22, Math.min(1.05, Number(periodo.toFixed(2)))),
    holdMs: Math.max(600, Math.round(holdMs)),
  };
}

function drenarColaIntermedia() {
  while (cola.length > 1) {
    const prev = cola.shift();
    try {
      prev?.onDone?.();
    } catch (_) {
      /* ignore */
    }
  }
}

function terminarSorteo(onDone) {
  sorteoEnCurso = false;
  try {
    onDone?.();
  } catch (e) {
    console.warn('[sorteo] onDone', e);
  }
  drenarColaIntermedia();
  const next = cola.shift();
  if (next) {
    reproducirSorteo(next.balota, next.onDone, next.opts);
  }
}

/**
 * Jugador en vivo: siempre overlay a pantalla completa (xl).
 * Mesa: opts.inline → animación en el bombo; si no hay bombo, mismo overlay.
 * opts.silent → solo marca vista y llama onDone (rehidratación / catch-up).
 *
 * @param {object} balota
 * @param {() => void} [onDone]
 * @param {{ force?: boolean, inline?: boolean, silent?: boolean }} [opts]
 */
export function reproducirSorteo(balota, onDone, opts = {}) {
  const numero = Number(balota?.numero);
  if (!Number.isFinite(numero) || numero <= 0) {
    onDone?.();
    return;
  }

  if (opts.silent) {
    ultimaBalotaAnimada = numero;
    onDone?.();
    return;
  }

  if (!opts.force && ultimaBalotaAnimada === numero) {
    onDone?.();
    return;
  }

  if (sorteoEnCurso) {
    // No tirar eventos: confirmar UI de las intermedias y animar solo la última.
    while (cola.length) {
      const prev = cola.shift();
      try {
        prev?.onDone?.();
      } catch (_) {
        /* ignore */
      }
    }
    cola.push({ balota, onDone, opts });
    return;
  }

  const cage = document.getElementById('mesaDomeCage');
  const eject = document.getElementById('mesaDomeEject');
  const usarInline = opts.inline === true && !!cage && !!eject;

  sorteoEnCurso = true;
  const timing = timingSorteo(numero);

  if (usarInline) {
    animarDomoEnVivo(balota, numero, onDone, timing);
    return;
  }

  mostrarReveladoPantalla(balota, numero, onDone, {
    mezclaMs: timing.giroMs,
    holdMs: timing.holdMs,
    periodo: timing.periodo,
    conJaula: true,
  });
}

function mostrarReveladoPantalla(balota, numero, onDone, timing = {}) {
  const mezclaMs = timing.mezclaMs ?? 1800;
  const holdMs = timing.holdMs ?? 1400;
  const fadeMs = timing.fadeMs ?? 320;
  const periodo = timing.periodo ?? 0.45;
  const conJaula = timing.conJaula === true;
  const conSonidoMezcla = timing.sonarMezcla !== false;

  document.getElementById('drawOverlay')?.remove();

  const overlay = document.createElement('div');
  overlay.id = 'drawOverlay';
  overlay.className = 'draw-overlay';
  overlay.innerHTML = `
    <div class="draw-stage">
      ${
        conJaula
          ? `<div class="draw-cage draw-cage--spin" style="--cage-spin:${periodo}s" aria-hidden="true">
              <div class="draw-cage__ring"></div>
              <div class="draw-cage__ring draw-cage__ring--2"></div>
              <div class="draw-cage__balls draw-cage__balls--color"></div>
            </div>`
          : ''
      }
      <div class="draw-eject" id="drawEject"></div>
    </div>`;
  document.body.appendChild(overlay);

  if (conSonidoMezcla && mezclaMs > 0) {
    sonarMezcla(mezclaMs);
  }

  const revealAt = mezclaMs > 0 ? Math.max(200, Math.floor(mezclaMs * 0.72)) : 80;

  later(() => {
    const ejectEl = document.getElementById('drawEject');
    const cageEl = overlay.querySelector('.draw-cage');
    if (cageEl) {
      cageEl.classList.remove('draw-cage--spin');
      cageEl.remove();
    }
    if (ejectEl) {
      ejectEl.innerHTML = renderBalotaHtml(balota, 'xl', { actual: true, anim: true });
      ejectEl.classList.add('draw-eject--show');
    }
    sonarBalota();
    ultimaBalotaAnimada = numero;
  }, revealAt);

  const total = Math.max(mezclaMs, revealAt + 400) + holdMs;
  later(() => {
    overlay.classList.add('draw-overlay--out');
    later(() => {
      overlay.remove();
      terminarSorteo(onDone);
    }, fadeMs);
  }, total);
}

/** Mesa: gira el bombo con sonido; luego balota xl a pantalla completa. */
function animarDomoEnVivo(balota, numero, onDone, timing) {
  const cage = document.getElementById('mesaDomeCage');
  const eject = document.getElementById('mesaDomeEject');
  const t = timing || timingSorteo(numero);

  if (!cage || !eject) {
    mostrarReveladoPantalla(balota, numero, onDone, {
      mezclaMs: t.giroMs,
      holdMs: t.holdMs,
      conJaula: true,
    });
    return;
  }

  cage.style.setProperty('--cage-spin', `${t.periodo}s`);
  cage.classList.add('bingo-cage--spin');
  eject.classList.remove('bingo-cage__exit--out', 'bingo-cage__exit--rest');
  eject.innerHTML = '';

  sonarMezcla(t.giroMs);

  later(() => {
    cage.classList.remove('bingo-cage--spin');
    cage.style.removeProperty('--cage-spin');

    mostrarReveladoPantalla(balota, numero, onDone, {
      mezclaMs: 0,
      holdMs: t.holdMs,
      conJaula: false,
      sonarMezcla: false,
    });
  }, t.giroMs);
}
