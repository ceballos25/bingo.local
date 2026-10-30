/**
 * Dock y helpers del panel mesa (extraíble con el frontend).
 */
import { esMesa } from '../../auth.js';
import { moveNavPill } from '../../ui.js';

const VIVO_KEY = 'bingo_mesa_vivo_partida';

export function getMesaVivoPartidaId() {
  const n = Number(sessionStorage.getItem(VIVO_KEY) || 0);
  return n > 0 ? n : null;
}

export function setMesaVivoPartidaId(partidaId) {
  const n = Number(partidaId);
  if (n > 0) sessionStorage.setItem(VIVO_KEY, String(n));
}

export function labelEstadoPartida(estado) {
  const map = {
    pendiente: 'Próximo a jugar',
    en_curso: 'En vivo',
    finalizada: 'Finalizada',
    cancelada: 'Cancelada',
  };
  return map[String(estado || '')] || String(estado || '').replace('_', ' ');
}

export { labelModalidad, emojiModalidad, premioLineHtml } from '../../ui.js';

const DOCK_JUGADOR = `
  <li>
    <a class="nav-app__link" href="#/landing" data-route="bingos">
      <i class="bi bi-calendar-event" aria-hidden="true"></i>
      <span>Bingos</span>
    </a>
  </li>
  <li>
    <a class="nav-app__link" href="#/mis-cartones" data-route="mis-cartones">
      <i class="bi bi-play-circle" aria-hidden="true"></i>
      <span>Jugar</span>
    </a>
  </li>
  <li>
    <button type="button" class="nav-app__link nav-app__cart" id="navCartBtn" data-bs-toggle="offcanvas" data-bs-target="#carritoOffcanvas" aria-controls="carritoOffcanvas" aria-label="Carrito">
      <i class="bi bi-cart3" aria-hidden="true"></i>
      <span class="nav-app__label-wrap">
        <span>Carrito</span>
        <span class="nav-app-badge nav-cart-badge badge text-bg-danger rounded-pill" id="navCartBadge" hidden>0</span>
      </span>
    </button>
  </li>
  <li>
    <a class="nav-app__link" href="#/cuenta" data-route="cuenta">
      <i class="bi bi-person" aria-hidden="true"></i>
      <span>Mi cuenta</span>
    </a>
  </li>`;

function dockMesaHtml() {
  const vivoId = getMesaVivoPartidaId();
  const vivoHref = vivoId ? `#/mesa/vivo/${vivoId}` : '#/mesa/partidas';
  return `
  <li>
    <a class="nav-app__link" href="#/mesa" data-route="mesa-inicio">
      <i class="bi bi-speedometer2" aria-hidden="true"></i>
      <span>Inicio</span>
    </a>
  </li>
  <li>
    <a class="nav-app__link" href="#/mesa/partidas" data-route="mesa-partidas">
      <i class="bi bi-calendar2-week" aria-hidden="true"></i>
      <span>Partidas</span>
    </a>
  </li>
  <li>
    <a class="nav-app__link" href="${vivoHref}" data-route="mesa-vivo">
      <i class="bi bi-broadcast" aria-hidden="true"></i>
      <span>Vivo</span>
    </a>
  </li>
  <li>
    <a class="nav-app__link" href="#/mesa/pagos" data-route="mesa-pagos">
      <i class="bi bi-cash-stack" aria-hidden="true"></i>
      <span>Pagos</span>
    </a>
  </li>
  <li>
    <a class="nav-app__link" href="#/cuenta" data-route="cuenta">
      <i class="bi bi-person" aria-hidden="true"></i>
      <span>Cuenta</span>
    </a>
  </li>`;
}

/** Pinta dock jugador o mesa según rol. */
export function syncAppDock() {
  const list = document.querySelector('.nav-app__list');
  if (!list) return;
  const mesa = esMesa();
  const mode = mesa ? 'mesa' : 'jugador';
  if (list.getAttribute('data-dock-mode') === mode) {
    if (mesa) {
      const vivo = list.querySelector('[data-route="mesa-vivo"]');
      const vivoId = getMesaVivoPartidaId();
      if (vivo) vivo.setAttribute('href', vivoId ? `#/mesa/vivo/${vivoId}` : '#/mesa/partidas');
    }
    return;
  }
  list.setAttribute('data-dock-mode', mode);
  list.innerHTML = mesa ? dockMesaHtml() : DOCK_JUGADOR;
  document.body.classList.toggle('app-shell--mesa', mesa);
}

export function setMesaNavActive(route) {
  const pillIndex = {
    'mesa-inicio': 0,
    'mesa-partidas': 1,
    'mesa-vivo': 2,
    'mesa-pagos': 3,
    cuenta: 4,
  };
  document.querySelectorAll('.nav-app [data-route]').forEach((a) => {
    a.classList.toggle('active', a.getAttribute('data-route') === route);
  });
  moveNavPill(pillIndex[route] ?? 0, 5);
}
