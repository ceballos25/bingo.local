import { api } from '../../api.js';
import { requireMesa } from '../../auth.js';
import {
  escapeHtml,
  formatFecha,
  formatMoney,
  estadoBadge,
  toast,
  loadingHtml,
  emptyHtml,
  setButtonBusy,
  premioLineHtml,
  urlPublicaComprar,
  copiarTexto,
} from '../../ui.js';
import {
  labelEstadoPartida,
  setMesaVivoPartidaId,
} from './shell.js';

export async function renderMesaPartidaDetalle(root, partidaId) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  const id = Number(partidaId);
  if (!id) {
    root.innerHTML = emptyHtml('Partida no válida.');
    return;
  }

  root.innerHTML = loadingHtml('Cargando partida…');

  const paint = async () => {
    const [p, cartonesRes] = await Promise.all([
      api('/partidas/buscar?id=' + encodeURIComponent(id)),
      api('/cartones/listar?partidaId=' + encodeURIComponent(id)).catch(() => []),
    ]);
    const cartones = Array.isArray(cartonesRes) ? cartonesRes : [];
    const porEstado = (estado) =>
      cartones.filter((c) => String(c.estado) === estado).length;
    const vendidos = porEstado('vendido') || Number(p.cartonesConfirmados || 0);
    const reservados = porEstado('reservado');
    const disponibles = porEstado('disponible');
    const mods = Array.isArray(p.modalidades) ? p.modalidades : [];
    const pendiente = p.estado === 'pendiente';
    const enCurso = p.estado === 'en_curso';

    root.innerHTML = `
      <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="#/mesa/partidas">Partidas</a></li>
          <li class="breadcrumb-item active" aria-current="page">${escapeHtml(p.nombre)}</li>
        </ol>
      </nav>

      <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div class="min-w-0">
          <h1 class="h4 font-brand mb-1">${escapeHtml(p.nombre)}</h1>
          <p class="small text-secondary mb-0">
            N.º ${escapeHtml(String(p.numero))} · ${escapeHtml(formatFecha(p.fecha))}
          </p>
        </div>
        <div class="text-end">
          ${estadoBadge(p.estado)}
          <div class="small text-secondary mt-1">${escapeHtml(labelEstadoPartida(p.estado))}</div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
          <div class="border bg-white p-3 h-100">
            <div class="small text-secondary">Precio</div>
            <div class="fs-5 fw-bold text-primary">${formatMoney(p.precioCarton)}</div>
            ${p.gratis ? '<span class="badge text-bg-success">Gratis</span>' : ''}
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="border bg-white p-3 h-100">
            <div class="small text-secondary">Vendidos</div>
            <div class="fs-5 fw-bold text-success">${escapeHtml(String(vendidos))}
              <span class="fs-6 fw-normal text-secondary">/ ${escapeHtml(String(p.cantidadCartones ?? 0))}</span>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="border bg-white p-3 h-100">
            <div class="small text-secondary">Pendientes</div>
            <div class="fs-5 fw-bold text-warning">${escapeHtml(String(reservados))}</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="border bg-white p-3 h-100">
            <div class="small text-secondary">Disponibles</div>
            <div class="fs-5 fw-bold">${escapeHtml(String(disponibles))}</div>
          </div>
        </div>
        <div class="col-12">
          <div class="border bg-white p-3 h-100">
            <div class="small text-secondary mb-1">Premios</div>
            ${mods
              .map(
                (m) =>
                  `<div class="small py-1">${premioLineHtml(m.modalidad, m.premio, 'row')}</div>`
              )
              .join('') || '<span class="small text-secondary">—</span>'}
          </div>
        </div>
      </div>

      <div class="border bg-white p-3 mb-3">
        <h2 class="h6 font-brand mb-2">Enlace para jugadores</h2>
        <p class="small text-secondary mb-2">
          Compártelo por WhatsApp o redes. Abre la compra de cartones de este bingo.
        </p>
        <div class="input-group">
          <input type="text" class="form-control" id="inputEnlacePublico" readonly value="${escapeHtml(urlPublicaComprar(p.id))}" aria-label="Enlace público">
          <button type="button" class="btn btn-primary" id="btnCopiarEnlace">
            <i class="bi bi-clipboard me-1" aria-hidden="true"></i>Copiar enlace
          </button>
        </div>
      </div>

      <div class="border bg-white p-3 mb-3">
        <h2 class="h6 font-brand mb-3">Acciones de mesa</h2>
        <div class="d-grid gap-2">
          ${
            pendiente
              ? `<button type="button" class="btn btn-success btn-lg" id="btnIniciar">
                  <i class="bi bi-play-fill me-1" aria-hidden="true"></i>Iniciar partida
                </button>`
              : ''
          }
          ${
            enCurso
              ? `<a class="btn btn-success btn-lg" href="#/mesa/vivo/${p.id}" id="btnIrVivo">
                  <i class="bi bi-broadcast me-1" aria-hidden="true"></i>Ir a la mesa en vivo
                </a>
                <button type="button" class="btn btn-outline-danger" id="btnFinalizar">
                  <i class="bi bi-stop-fill me-1" aria-hidden="true"></i>Finalizar partida
                </button>`
              : ''
          }
        </div>
      </div>

      ${
        pendiente
          ? `<div class="border bg-white p-3 mb-3">
        <h2 class="h6 font-brand mb-2">Bingo gratis</h2>
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" role="switch" id="switchGratis" ${p.gratis ? 'checked' : ''}>
          <label class="form-check-label" for="switchGratis">Esta partida es gratis</label>
        </div>
        <div class="input-group" id="wrapPrecioGratis" ${p.gratis ? 'hidden' : ''}>
          <span class="input-group-text">Precio</span>
          <input type="number" class="form-control" id="inputPrecioGratis" min="1000" step="1000" value="${Number(p.precioCarton) || 10000}">
          <button type="button" class="btn btn-outline-primary" id="btnGuardarGratis">Guardar</button>
        </div>
      </div>`
          : ''
      }

      <div class="border bg-white p-3 mb-3">
        <h2 class="h6 font-brand mb-2">YouTube en vivo</h2>
        <p class="small text-secondary mb-2">Pega el enlace o el ID del video para que los jugadores lo vean.</p>
        <div class="input-group mb-2">
          <input type="text" class="form-control" id="inputYoutube" placeholder="https://youtube.com/watch?v=…" value="${escapeHtml(p.youtubeVideoId || '')}">
          <button type="button" class="btn btn-primary" id="btnYoutube">Guardar</button>
        </div>
        ${
          p.youtubeEmbedUrl
            ? `<div class="ratio ratio-16x9 rounded overflow-hidden bg-dark">
                <iframe src="${escapeHtml(p.youtubeEmbedUrl)}" title="Transmisión" allowfullscreen allow="autoplay; encrypted-media"></iframe>
              </div>`
            : ''
        }
      </div>

      <a class="btn btn-outline-secondary" href="#/mesa/partidas"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a la lista de bingos</a>`;

    root.querySelector('#btnCopiarEnlace')?.addEventListener('click', async () => {
      const url =
        root.querySelector('#inputEnlacePublico')?.value || urlPublicaComprar(id);
      const ok = await copiarTexto(url);
      toast(ok ? 'Enlace copiado. Ya puedes pegarlo en WhatsApp.' : 'No se pudo copiar', ok ? 'success' : 'warning');
    });

    root.querySelector('#btnIniciar')?.addEventListener('click', async () => {
      const btn = root.querySelector('#btnIniciar');
      setButtonBusy(btn, true, 'Iniciando…');
      try {
        await api('/partidas/iniciar', {
          method: 'POST',
          body: { partidaId: id },
        });
        setMesaVivoPartidaId(id);
        toast('Partida en vivo', 'success');
        location.hash = `#/mesa/vivo/${id}`;
      } catch (e) {
        toast(e.message || 'No se pudo iniciar');
        setButtonBusy(btn, false);
      }
    });

    root.querySelector('#btnFinalizar')?.addEventListener('click', async () => {
      if (!confirm('¿Finalizar esta partida? No se podrán sacar más balotas.')) return;
      const btn = root.querySelector('#btnFinalizar');
      setButtonBusy(btn, true, 'Finalizando…');
      try {
        await api('/partidas/finalizar', {
          method: 'POST',
          body: { partidaId: id },
        });
        toast('Partida finalizada', 'success');
        await paint();
      } catch (e) {
        toast(e.message || 'No se pudo finalizar');
        setButtonBusy(btn, false);
      }
    });

    root.querySelector('#btnIrVivo')?.addEventListener('click', () => {
      setMesaVivoPartidaId(id);
    });

    const switchGratis = root.querySelector('#switchGratis');
    const wrapPrecio = root.querySelector('#wrapPrecioGratis');
    switchGratis?.addEventListener('change', () => {
      if (wrapPrecio) wrapPrecio.hidden = switchGratis.checked;
    });

    root.querySelector('#btnGuardarGratis')?.addEventListener('click', async () => {
      const gratis = !!switchGratis?.checked;
      const precioCarton = Number(root.querySelector('#inputPrecioGratis')?.value || 0);
      const btn = root.querySelector('#btnGuardarGratis');
      setButtonBusy(btn, true, 'Guardando…');
      try {
        const body = { partidaId: id, gratis };
        if (!gratis) body.precioCarton = precioCarton;
        await api('/partidas/marcar-gratis', { method: 'POST', body });
        toast('Actualizado', 'success');
        await paint();
      } catch (e) {
        toast(e.message || 'No se pudo guardar');
        setButtonBusy(btn, false);
      }
    });

    root.querySelector('#btnYoutube')?.addEventListener('click', async () => {
      const youtubeVideoId = String(root.querySelector('#inputYoutube')?.value || '').trim();
      const btn = root.querySelector('#btnYoutube');
      setButtonBusy(btn, true, 'Guardando…');
      try {
        await api('/partidas/asignar-youtube', {
          method: 'POST',
          body: { partidaId: id, youtubeVideoId },
        });
        toast('YouTube actualizado', 'success');
        await paint();
      } catch (e) {
        toast(e.message || 'No se pudo guardar');
        setButtonBusy(btn, false);
      }
    });
  };

  try {
    await paint();
  } catch (e) {
    toast(e.message || 'Error');
    root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
  }
}
