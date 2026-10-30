import { api } from '../api.js';
import { isLoggedIn } from '../auth.js';
import { getOrganizacionId } from '../org-context.js';
import {
  formatFecha,
  formatMoney,
  estadoBadge,
  escapeHtml,
  toast,
  loadingHtml,
  emptyHtml,
  premiosListHtml,
} from '../ui.js';

function premiosHtml(modalidades) {
  return premiosListHtml(modalidades, {
    emptyHtml: `<p class="small text-secondary mb-0 px-3 py-2">Premios por confirmar</p>`,
    ulClass: 'list-group list-group-flush',
    liClass: 'list-group-item px-3',
  });
}

function ctaComprar(p, { tieneCarton }) {
  return `
    <div class="d-grid gap-2">
      <a class="btn btn-success w-100" href="#/comprar/${p.id}">
        <i class="bi bi-cart3 me-1" aria-hidden="true"></i>Elegir cartones
      </a>
      ${
        tieneCarton
          ? `<a class="btn btn-outline-secondary btn-sm w-100" href="#/mis-cartones"><i class="bi bi-play-circle me-1" aria-hidden="true"></i>Ir a Jugar</a>`
          : ''
      }
    </div>`;
}

function cardPartida(p, { tieneCarton }) {
  return `
    <div class="card h-100 shadow-sm">
      <div class="card-header bg-white border-bottom-0 pb-0 pt-3 px-3">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-w-0">
            <h2 class="h5 card-title font-brand mb-1">${escapeHtml(p.nombre)}</h2>
            <p class="card-subtitle text-secondary small mb-0">
              N.º ${escapeHtml(String(p.numero))}
              <span class="mx-1">·</span>
              <i class="bi bi-calendar3" aria-hidden="true"></i>
              ${escapeHtml(formatFecha(p.fecha))}
            </p>
          </div>
          ${estadoBadge(p.estado)}
        </div>
      </div>

      <div class="card-body px-3 pt-3 pb-2">
        <div class="d-flex align-items-baseline gap-2 mb-3">
          <span class="fs-3 fw-bold text-primary lh-1">${formatMoney(p.precioCarton)}</span>
          <span class="text-secondary small">por cartón</span>
        </div>

        <p class="small text-secondary text-uppercase fw-semibold mb-2 letter-spacing">Premios</p>
        <div class="border rounded overflow-hidden mb-0">
          ${premiosHtml(p.modalidades)}
        </div>

        ${
          tieneCarton
            ? `<div class="alert alert-success py-2 small mb-0 mt-3" role="status">
                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Ya pagaste tu cartón en este bingo
              </div>`
            : ''
        }
      </div>

      <div class="card-footer bg-white border-top-0 pt-2 pb-3 px-3">
        ${ctaComprar(p, { tieneCarton })}
      </div>
    </div>`;
}

export async function renderBingos(root) {
  root.innerHTML = loadingHtml('Cargando bingos…');

  try {
    let partidas;
    let partidasConCartonVendido = new Set();

    if (isLoggedIn()) {
      const [privadas, cartones] = await Promise.all([
        api('/partidas/listar'),
        api('/cartones/listar'),
      ]);
      partidas = privadas;
      partidasConCartonVendido = new Set(
        (cartones || [])
          .filter((c) => c.estado === 'vendido' && c.partidaId)
          .map((c) => Number(c.partidaId))
      );
    } else {
      const orgId = getOrganizacionId();
      if (!orgId) {
        root.innerHTML = emptyHtml(
          'Entra con el enlace del bingo que te compartieron.',
          '<a class="btn btn-success" href="#/landing">Ir al inicio</a>'
        );
        return;
      }
      partidas = await api(
        '/partidas/publicas?organizacionId=' + encodeURIComponent(orgId)
      );
    }

    const activas = (partidas || [])
      .filter((p) => ['pendiente', 'en_curso'].includes(p.estado))
      .sort((a, b) => {
        if (a.estado === 'en_curso' && b.estado !== 'en_curso') return -1;
        if (b.estado === 'en_curso' && a.estado !== 'en_curso') return 1;
        return String(a.fecha || '').localeCompare(String(b.fecha || ''));
      });

    if (activas.length === 0) {
      root.innerHTML = emptyHtml('No hay bingos abiertos en este momento.');
      return;
    }

    const unoSolo = activas.length === 1;

    root.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0 font-brand">Bingos</h1>
        <span class="badge text-bg-light border text-secondary">${activas.length}</span>
      </div>
      <div class="row g-3 ${unoSolo ? 'justify-content-center' : ''}">
        ${activas
          .map(
            (p) => `
          <div class="${unoSolo ? 'col-12 col-md-10 col-lg-8' : 'col-12 col-md-6'}">
            ${cardPartida(p, {
              tieneCarton: partidasConCartonVendido.has(Number(p.id)),
            })}
          </div>`
          )
          .join('')}
      </div>`;
  } catch (e) {
    toast(e.message || 'No se pudieron cargar los bingos');
    root.innerHTML = `<div class="alert alert-danger mb-0" role="alert">${escapeHtml(e.message)}</div>`;
  }
}
