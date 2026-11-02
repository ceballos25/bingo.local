import { api } from '../api.js';
import { requireAuth } from '../auth.js';
import {
  escapeHtml,
  estadoBadge,
  formatFecha,
  renderCartonHtml,
  toast,
  loadingHtml,
  emptyHtml,
} from '../ui.js';

function partidaTerminada(partida) {
  const e = partida?.estado || '';
  return e === 'finalizada' || e === 'cancelada';
}

/** Badge útil: pago / jugado / estado de la partida (no “vendido”). */
function badgeCarton(c, partida) {
  if (c.estado === 'reservado') {
    if (c.pagoCanal === 'comprobante') {
      return '<span class="badge text-bg-warning">En revisión</span>';
    }
    return '<span class="badge text-bg-warning">Pago pendiente</span>';
  }
  if (partidaTerminada(partida)) {
    if (partida.estado === 'cancelada') {
      return '<span class="badge text-bg-secondary">Cancelada</span>';
    }
    return '<span class="badge text-bg-secondary">Jugado</span>';
  }
  if (partida?.estado) {
    return estadoBadge(partida.estado);
  }
  return '';
}

function accionCarton(c, partida) {
  if (c.estado === 'reservado') {
    if (c.pagoCanal === 'comprobante') {
      return `<p class="small text-warning mb-0 mt-2">
        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
        Estamos revisando tu comprobante. Una vez aprobado podrás participar.
      </p>`;
    }
    return '<p class="small text-warning mb-0 mt-2"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Completa el pago para jugar</p>';
  }
  if (c.estado !== 'vendido' || !c.partidaId) return '';

  const estado = partida?.estado || '';
  if (estado === 'finalizada') {
    return `<p class="small text-secondary mb-0 mt-2"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Esta partida ya se jugó</p>`;
  }
  if (estado === 'cancelada') {
    return `<p class="small text-secondary mb-0 mt-2"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Partida cancelada</p>`;
  }
  if (estado === 'pendiente') {
    const cuando = partida?.fecha ? formatFecha(partida.fecha) : 'la hora programada';
    return `<p class="small text-secondary mb-0 mt-2"><i class="bi bi-clock me-1" aria-hidden="true"></i>En vivo a las ${escapeHtml(cuando)}</p>`;
  }
  // en_curso o sin dato de partida: botón bien visible debajo del cartón
  return `<a class="btn btn-bingo w-100 mt-2" href="#/vivo/${c.partidaId}"><i class="bi bi-play-fill me-1" aria-hidden="true"></i>Jugar</a>`;
}

/** Activos primero; jugados/cancelados al final. */
function ordenCartones(a, b, porId) {
  const pa = porId.get(Number(a.partidaId));
  const pb = porId.get(Number(b.partidaId));
  const ta = partidaTerminada(pa) ? 1 : 0;
  const tb = partidaTerminada(pb) ? 1 : 0;
  if (ta !== tb) return ta - tb;
  return Number(b.id) - Number(a.id);
}

export async function renderMisCartones(root) {
  if (!requireAuth()) {
    root.innerHTML = emptyHtml(
      'Inicia sesión para ver tus cartones.',
      '<a class="btn btn-primary" href="#/cuenta"><i class="bi bi-person me-1" aria-hidden="true"></i>Ir a cuenta</a>'
    );
    return;
  }

  root.innerHTML = loadingHtml();

  try {
    const [cartones, partidas] = await Promise.all([
      api('/cartones/listar'),
      api('/partidas/listar'),
    ]);
    const porId = new Map((partidas || []).map((p) => [Number(p.id), p]));
    const mios = (cartones || [])
      .filter((c) => ['reservado', 'vendido'].includes(c.estado))
      .sort((a, b) => ordenCartones(a, b, porId));

    if (mios.length === 0) {
      root.innerHTML = emptyHtml(
        'Aún no tienes cartones para jugar.',
        '<a class="btn btn-primary" href="#/landing"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Ver bingos</a>'
      );
      return;
    }

    root.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <div class="min-w-0">
          <h1 class="h4 mb-0 font-brand">Jugar</h1>
          <p class="small text-secondary mb-0">Activos arriba · los ya jugados aparecen en gris</p>
        </div>
        <span class="text-secondary small flex-shrink-0">${mios.length}</span>
      </div>
      <div class="row g-3">
        ${mios
          .map((c) => {
            const partida = porId.get(Number(c.partidaId));
            const jugado = partidaTerminada(partida);
            return `
          <div class="col-12 col-sm-6 col-lg-4">
            <div class="card h-100 mis-carton-card${jugado ? ' mis-carton-card--jugado' : ''}">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                  <span class="fw-semibold">Cartón #${c.id}</span>
                  ${badgeCarton(c, partida)}
                </div>
                <p class="small text-secondary mb-3">${
                  partida
                    ? escapeHtml(partida.nombre)
                    : `Partida ${c.partidaId ?? '—'}`
                }</p>
                ${renderCartonHtml(c.numeros, new Set(), { cartonId: c.id })}
                ${accionCarton(c, partida)}
              </div>
            </div>
          </div>`;
          })
          .join('')}
      </div>`;
  } catch (e) {
    toast(e.message || 'Error');
    root.innerHTML = `<div class="alert alert-danger mb-0" role="alert">${escapeHtml(e.message)}</div>`;
  }
}
