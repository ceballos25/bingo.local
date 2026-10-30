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
  urlPublicaComprar,
  copiarTexto,
} from '../../ui.js';
import { labelEstadoPartida, premioLineHtml } from './shell.js';

function sortPartidas(list) {
  return [...(list || [])].sort((a, b) => {
    const order = { en_curso: 0, pendiente: 1, finalizada: 2, cancelada: 3 };
    const oa = order[a.estado] ?? 9;
    const ob = order[b.estado] ?? 9;
    if (oa !== ob) return oa - ob;
    return String(b.fecha || '').localeCompare(String(a.fecha || ''));
  });
}

function cardHtml(p) {
  const mods = Array.isArray(p.modalidades) ? p.modalidades : [];
  const premios = mods.length
    ? `<div class="mesa-premios">${mods
        .map((m) => premioLineHtml(m.modalidad, m.premio, 'chip'))
        .join('')}</div>`
    : '<span class="small text-secondary">Sin premios</span>';

  return `
    <article class="card h-100 shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <div class="min-w-0">
            <h2 class="h5 card-title font-brand mb-1">${escapeHtml(p.nombre)}</h2>
            <p class="small text-secondary mb-0">
              N.º ${escapeHtml(String(p.numero))}
              <span class="mx-1">·</span>
              ${escapeHtml(formatFecha(p.fecha))}
            </p>
          </div>
          <div class="text-end">
            ${estadoBadge(p.estado)}
            <div class="small text-secondary mt-1">${escapeHtml(labelEstadoPartida(p.estado))}</div>
          </div>
        </div>
        <div class="d-flex align-items-baseline gap-2 mb-2">
          <span class="fs-4 fw-bold text-primary lh-1 font-brand">${formatMoney(p.precioCarton)}</span>
          <span class="text-secondary small">/ cartón${p.gratis ? ' · gratis' : ''}</span>
        </div>
        <p class="small text-secondary mb-1">
          Cartones: <strong>${escapeHtml(String(p.cartonesConfirmados ?? 0))}</strong>
          / ${escapeHtml(String(p.cantidadCartones ?? 0))}
        </p>
        <div class="mb-3">${premios}</div>
        <div class="d-grid gap-2">
          <a class="btn btn-outline-primary" href="#/mesa/partidas/${p.id}">
            <i class="bi bi-gear me-1" aria-hidden="true"></i>Gestionar
          </a>
          <button type="button" class="btn btn-outline-secondary" data-copiar-enlace="${escapeHtml(String(p.id))}">
            <i class="bi bi-clipboard me-1" aria-hidden="true"></i>Copiar enlace para jugadores
          </button>
          ${
            p.estado === 'en_curso'
              ? `<a class="btn btn-success" href="#/mesa/vivo/${p.id}">
                  <i class="bi bi-broadcast me-1" aria-hidden="true"></i>Abrir mesa en vivo
                </a>`
              : ''
          }
        </div>
      </div>
    </article>`;
}

function crearFormHtml() {
  return `
    <div class="border bg-white p-3 mb-4" id="mesaCrearBox">
      <h2 class="h5 font-brand mb-3">Nueva partida</h2>
      <form id="formCrearPartida">
        <div class="form-floating mb-2">
          <input name="nombre" class="form-control" id="crearNombre" placeholder="Nombre" required maxlength="120">
          <label for="crearNombre">Nombre del bingo</label>
        </div>
        <div class="row g-2">
          <div class="col-6">
            <div class="form-floating">
              <input name="numero" type="number" class="form-control" id="crearNumero" placeholder="Nº" required min="1" step="1">
              <label for="crearNumero">Número</label>
            </div>
          </div>
          <div class="col-6">
            <div class="form-floating">
              <input name="cantidadCartones" type="number" class="form-control" id="crearCupo" placeholder="Cupo" required min="1" max="500" step="1" value="50">
              <label for="crearCupo">Cupo cartones</label>
            </div>
          </div>
        </div>
        <div class="form-floating mt-2">
          <input name="fecha" type="datetime-local" class="form-control" id="crearFecha" required>
          <label for="crearFecha">Fecha y hora</label>
        </div>
        <div class="form-floating mt-2">
          <input name="precioCarton" type="number" class="form-control" id="crearPrecio" placeholder="Precio" required min="0" step="1000" value="10000">
          <label for="crearPrecio">Precio por cartón (COP)</label>
        </div>
        <div class="form-check form-switch mt-2 mb-2">
          <input class="form-check-input" type="checkbox" role="switch" id="crearGratis" name="gratis">
          <label class="form-check-label" for="crearGratis">Bingo gratis (precio 0)</label>
        </div>
        <p class="small text-secondary text-uppercase fw-semibold mb-1">Premios</p>
        <p class="small text-secondary mb-2">Llena solo lo que vas a jugar. Si lo dejas vacío, esa modalidad no va.</p>
        <div class="row g-2 mb-3">
          <div class="col-4">
            <div class="form-floating">
              <input name="premioLinea" type="number" class="form-control" id="premioLinea" placeholder="L" min="1000" step="1000">
              <label for="premioLinea">L</label>
            </div>
          </div>
          <div class="col-4">
            <div class="form-floating">
              <input name="premioEquis" type="number" class="form-control" id="premioEquis" placeholder="X" min="1000" step="1000">
              <label for="premioEquis">Equis</label>
            </div>
          </div>
          <div class="col-4">
            <div class="form-floating">
              <input name="premioCompleta" type="number" class="form-control" id="premioCompleta" placeholder="Bingo" min="1000" step="1000">
              <label for="premioCompleta">Bingo</label>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-success w-100" id="btnCrearPartida">
          <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear partida y cartones
        </button>
      </form>
    </div>`;
}

function toSqlDatetime(localValue) {
  const raw = String(localValue || '').trim();
  if (!raw) return '';
  // datetime-local → YYYY-MM-DDTHH:MM → space + :00
  const normalized = raw.replace('T', ' ');
  return normalized.length === 16 ? normalized + ':00' : normalized;
}

export async function renderMesaPartidas(root) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  root.innerHTML = loadingHtml('Cargando partidas…');

  try {
    const list = await api('/partidas/listar');
    const partidas = sortPartidas(list);

    root.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h1 class="h4 mb-0 font-brand">Partidas</h1>
          <p class="small text-secondary mb-0">Crea y gestiona los bingos de tu organización</p>
        </div>
        <span class="badge text-bg-light border text-secondary">${partidas.length}</span>
      </div>
      ${crearFormHtml()}
      ${
        partidas.length === 0
          ? emptyHtml('Aún no hay partidas. Crea la primera arriba.')
          : `<div class="row g-3">${partidas
              .map(
                (p) => `
            <div class="col-12 col-md-6">
              ${cardHtml(p)}
            </div>`
              )
              .join('')}</div>`
      }`;

    const gratisEl = root.querySelector('#crearGratis');
    const precioEl = root.querySelector('#crearPrecio');
    gratisEl?.addEventListener('change', () => {
      if (!precioEl) return;
      if (gratisEl.checked) {
        precioEl.value = '0';
        precioEl.disabled = true;
      } else {
        precioEl.disabled = false;
        if (Number(precioEl.value) === 0) precioEl.value = '10000';
      }
    });

    root.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('[data-copiar-enlace]');
      if (!btn || !root.contains(btn)) return;
      const partidaId = Number(btn.getAttribute('data-copiar-enlace'));
      const url = urlPublicaComprar(partidaId);
      const ok = await copiarTexto(url);
      toast(
        ok ? 'Enlace copiado. Ya puedes pegarlo en WhatsApp.' : 'No se pudo copiar',
        ok ? 'success' : 'warning'
      );
    });

    root.querySelector('#formCrearPartida')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const form = ev.target;
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      const btn = root.querySelector('#btnCrearPartida');
      const fd = new FormData(form);
      const gratis = !!root.querySelector('#crearGratis')?.checked;
      const precio = gratis ? 0 : Number(fd.get('precioCarton'));
      const modalidades = [];
      const premioLinea = String(fd.get('premioLinea') || '').trim();
      const premioEquis = String(fd.get('premioEquis') || '').trim();
      const premioCompleta = String(fd.get('premioCompleta') || '').trim();
      if (premioLinea !== '' && Number(premioLinea) > 0) {
        modalidades.push({ modalidad: 'linea', premio: Number(premioLinea) });
      }
      if (premioEquis !== '' && Number(premioEquis) > 0) {
        modalidades.push({ modalidad: 'equis', premio: Number(premioEquis) });
      }
      if (premioCompleta !== '' && Number(premioCompleta) > 0) {
        modalidades.push({ modalidad: 'completa', premio: Number(premioCompleta) });
      }
      if (!modalidades.length) {
        toast('Pon al menos un premio (L, Equis o Bingo)', 'warning');
        return;
      }
      const body = {
        nombre: String(fd.get('nombre') || '').trim(),
        numero: Number(fd.get('numero')),
        fecha: toSqlDatetime(fd.get('fecha')),
        precioCarton: precio,
        gratis,
        cantidadCartones: Number(fd.get('cantidadCartones')),
        modalidades,
      };
      setButtonBusy(btn, true, 'Creando…');
      try {
        const creada = await api('/partidas/crear', { method: 'POST', body });
        toast('Partida creada. Ahí puedes copiar el enlace para jugadores.', 'success');
        location.hash = `#/mesa/partidas/${creada.id}`;
      } catch (e) {
        toast(e.message || 'No se pudo crear');
        setButtonBusy(btn, false);
      }
    });
  } catch (e) {
    toast(e.message || 'Error al cargar');
    root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
  }
}
