import { api } from '../../api.js';
import { requireMesa, getUsuario, esAdministrador } from '../../auth.js';
import {
  escapeHtml,
  formatMoney,
  loadingHtml,
  emptyHtml,
  toast,
} from '../../ui.js';
import { getMesaVivoPartidaId } from './shell.js';

function card(label, value, hint, tone = '') {
  const toneClass = tone ? ` text-${tone}` : '';
  return `
    <div class="col-6 col-md-4 col-lg-3">
      <div class="border bg-white p-3 h-100">
        <div class="small text-secondary">${escapeHtml(label)}</div>
        <div class="fs-4 fw-bold${toneClass}">${value}</div>
        ${hint ? `<div class="small text-secondary">${hint}</div>` : ''}
      </div>
    </div>`;
}

export async function renderMesaDashboard(root) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  root.innerHTML = loadingHtml('Cargando panel…');

  try {
    const resumen = await api('/organizaciones/resumen');
    const u = getUsuario() || {};
    const vivoId = getMesaVivoPartidaId();
    const c = resumen.cartones || {};
    const p = resumen.partidas || {};
    const pagos = resumen.pagos || {};

    root.innerHTML = `
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
          <h1 class="h4 font-brand mb-1">Panel de mesa</h1>
          <p class="small text-secondary mb-0">
            ${escapeHtml(resumen.nombre || 'Organización')}
            ${u.nombre ? ' · ' + escapeHtml(u.nombre) : ''}
          </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          ${
            vivoId
              ? `<a class="btn btn-success" href="#/mesa/vivo/${vivoId}">
                  <i class="bi bi-broadcast me-1" aria-hidden="true"></i>Continuar vivo
                </a>`
              : ''
          }
          <a class="btn btn-primary" href="#/mesa/partidas">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Partidas
          </a>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-3">
          <a href="#/mesa/clientes" class="border bg-white p-3 h-100 d-block text-decoration-none text-body">
            <div class="small text-secondary">Clientes</div>
            <div class="fs-4 fw-bold">${escapeHtml(String(resumen.clientes ?? 0))}</div>
            <div class="small text-secondary">Jugadores activos · ver lista</div>
          </a>
        </div>
        ${card('Bingos', String(p.total ?? 0), `${p.enCurso ?? 0} en vivo · ${p.proximas ?? 0} próximos`)}
        ${card('Vendidos', String(c.vendidos ?? 0), 'Cartones pagados', 'success')}
        ${card('Pendientes', String(c.reservados ?? 0), 'Por aprobar / pago', 'warning')}
        ${card('Disponibles', String(c.disponibles ?? 0), 'Sin vender')}
        ${card('Recaudado', formatMoney(pagos.recaudado ?? 0), `${pagos.pagosPendientes ?? 0} pagos en revisión`, 'primary')}
      </div>

      <div class="border bg-white p-3 mb-3">
        <h2 class="h6 font-brand mb-3">Accesos rápidos</h2>
        <div class="d-grid gap-2 d-sm-flex flex-wrap">
          <a class="btn btn-outline-primary" href="#/mesa/partidas">
            <i class="bi bi-calendar2-week me-1" aria-hidden="true"></i>Gestionar partidas
          </a>
          <a class="btn btn-outline-primary" href="#/mesa/pagos">
            <i class="bi bi-cash-stack me-1" aria-hidden="true"></i>Revisar pagos
          </a>
          <a class="btn btn-outline-primary" href="#/mesa/pagos?tab=cuentas">
            <i class="bi bi-bank me-1" aria-hidden="true"></i>Cuentas transferencia
          </a>
          <a class="btn btn-outline-primary" href="#/mesa/clientes">
            <i class="bi bi-people me-1" aria-hidden="true"></i>Clientes y equipo
          </a>
          <a class="btn btn-outline-primary" href="#/mesa/organizaciones">
            <i class="bi bi-building me-1" aria-hidden="true"></i>${esAdministrador() ? 'Organizaciones' : 'Mi organización'}
          </a>
        </div>
      </div>

      <p class="small text-secondary mb-0">
        Solo <strong>${escapeHtml(resumen.nombre || 'tu organización')}</strong>. El detalle de cartones por bingo está en cada partida.
      </p>`;
  } catch (e) {
    toast(e.message || 'No se pudo cargar el panel');
    root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message || 'Error')}</div>`;
  }
}
