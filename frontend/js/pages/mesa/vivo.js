import { api } from '../../api.js';
import { getToken, requireMesa, esAdministrador } from '../../auth.js';
import { CONFIG } from '../../config.js';
import {
  sliceHistorial,
  datosBalota,
  renderBalotasPanelHtml,
} from '../../balota.js';
import {
  cleanupDrawOverlay,
  marcarBalotaYaVista,
  getUltimaBalotaAnimada,
  reproducirSorteo,
  renderBingoDomeHtml,
} from '../../draw-overlay.js';
import { bindLiveResume } from '../../live-resume.js';
import { unlockAudio, sonarCantar } from '../../sound.js';
import { celebrarCantada } from '../../celebrate.js';
import { renderTablero75Html } from '../../tablero75.js';
import {
  escapeHtml,
  toast,
  loadingHtml,
  emptyHtml,
  estadoBadge,
  formatMoney,
  normalizarBalotas,
  setButtonBusy,
  mountLiveClock,
  renderCartonHtml,
  setNumerosSorteados,
} from '../../ui.js';
import { labelModalidad, premioLineHtml, setMesaVivoPartidaId } from './shell.js';

let eventSource = null;
let mesaBloqueada = false;
let stopLiveClock = null;
/** @type {{ reclamoId: number, cartonId: number, numeros: object } | null} */
let revisionActiva = null;
let mesaValidarOffcanvas = null;
let mesaVivoActivo = false;
let disposeMesaResume = null;
let mesaSseRetryTimer = null;

function cerrarSse() {
  if (mesaSseRetryTimer) {
    window.clearTimeout(mesaSseRetryTimer);
    mesaSseRetryTimer = null;
  }
  if (eventSource) {
    eventSource.close();
    eventSource = null;
  }
}

function getMesaValidarOffcanvas() {
  const el = document.getElementById('mesaValidarOffcanvas');
  if (!el || !window.bootstrap?.Offcanvas) return null;
  if (!mesaValidarOffcanvas) {
    mesaValidarOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(el, {
      backdrop: false,
      scroll: true,
    });
  }
  return mesaValidarOffcanvas;
}

function cerrarRevisionCantada() {
  revisionActiva = null;
  try {
    getMesaValidarOffcanvas()?.hide();
  } catch (_) {
    /* ignore */
  }
}

export function cleanupMesaVivo() {
  mesaVivoActivo = false;
  disposeMesaResume?.();
  disposeMesaResume = null;
  stopLiveClock?.();
  stopLiveClock = null;
  cerrarRevisionCantada();
  cerrarSse();
  cleanupDrawOverlay();
}

function renderPremios(partida) {
  const mods = Array.isArray(partida.modalidades) ? partida.modalidades : [];
  if (!mods.length) {
    return `<p class="small text-secondary mb-0">Sin premios configurados.</p>`;
  }
  return `
    <div class="mesa-premios" aria-label="Premios">
      ${mods.map((m) => premioLineHtml(m.modalidad, m.premio, 'chip')).join('')}
    </div>`;
}

function renderCantadasHtml(pendientes, otros, puedeValidar) {
  return `
    <div class="mesa-cantadas">
      <div class="mesa-panel__head">
        <span class="fw-semibold small">Cantadas</span>
        <span class="badge text-bg-warning">${pendientes.length}</span>
      </div>
      <div id="mesaReclamos" class="mesa-cantadas__list">
        ${
          pendientes.length === 0 && otros.length === 0
            ? `<p class="small text-secondary mb-0">Nadie ha cantado.</p>`
            : `${pendientes.map((r) => renderReclamoCard(r, { puedeValidar })).join('')}
               ${otros.map((r) => renderReclamoCard(r, { puedeValidar: false })).join('')}`
        }
      </div>
    </div>`;
}

function renderReclamoCard(r, { puedeValidar }) {
  const pendiente = r.estado === 'pendiente';
  const jugador = r.jugador || {};
  const nombre = [jugador.nombre, jugador.apellido].filter(Boolean).join(' ') || 'Jugador';
  return `
    <div class="mesa-cantadas__card ${pendiente ? 'is-pendiente' : ''}" data-reclamo-id="${r.id}">
      <div class="mesa-cantadas__card-top">
        <div class="min-w-0">
          <div class="fw-semibold small text-truncate">${escapeHtml(labelModalidad(r.modalidad))} · #${escapeHtml(String(r.cartonId))}</div>
          <div class="small text-secondary text-truncate">${escapeHtml(nombre)}</div>
        </div>
        <span class="badge ${pendiente ? 'text-bg-warning' : r.estado === 'aprobado' ? 'text-bg-success' : 'text-bg-secondary'}">${escapeHtml(r.estado)}</span>
      </div>
      <div class="small">Premio: <strong class="text-success">${formatMoney(r.premio)}</strong></div>
      <div class="small text-secondary text-truncate">${escapeHtml(lugarJugador(jugador))}</div>
      ${
        pendiente && puedeValidar
          ? `<div class="d-flex gap-1 mt-1">
              <button type="button" class="btn btn-success flex-fill btn-sm py-0" data-revisar="${r.id}">Validar</button>
              <button type="button" class="btn btn-outline-danger flex-fill btn-sm py-0" data-rechazar="${r.id}">No</button>
            </div>`
          : pendiente && !puedeValidar
            ? `<p class="small text-secondary mb-0">Solo admin valida.</p>`
            : ''
      }
    </div>`;
}

function hintModalidad(modalidad) {
  const key = String(modalidad || '').toLowerCase();
  if (key === 'linea') return 'Revisa si hay una línea completa (fila o columna) con las balotas cantadas.';
  if (key === 'equis') return 'Revisa si las dos diagonales (equis) están completas con las balotas cantadas.';
  if (key === 'completa') return 'Revisa si todo el cartón está cubierto con las balotas cantadas.';
  return 'Revisa el cartón contra las balotas cantadas en vivo.';
}

function normalizarNumerosCarton(raw) {
  if (!raw) return null;
  if (typeof raw === 'string') {
    try {
      raw = JSON.parse(raw);
    } catch (_) {
      return null;
    }
  }
  if (typeof raw !== 'object' || Array.isArray(raw)) return null;
  const cols = ['B', 'I', 'N', 'G', 'O'];
  const out = {};
  let ok = false;
  cols.forEach((c) => {
    const col = raw[c] ?? raw[c.toLowerCase()];
    if (Array.isArray(col) && col.length) {
      out[c] = col.map((n) => Number(n));
      ok = true;
    }
  });
  return ok ? out : null;
}

/** Siempre desde API (fuente del sistema) para validar en vivo. */
async function cargarNumerosCarton(reclamo) {
  const cartonId = Number(reclamo.cartonId || reclamo?.carton?.id);
  if (!cartonId) {
    throw new Error('La cantada no tiene cartón asociado.');
  }
  const carton = await api('/cartones/buscar?id=' + encodeURIComponent(cartonId));
  const numeros = normalizarNumerosCarton(carton?.numeros);
  if (!numeros) {
    const fallback = normalizarNumerosCarton(reclamo?.carton?.numeros);
    if (fallback) return { cartonId, numeros: fallback };
    throw new Error('El cartón no trae números para revisar.');
  }
  return { cartonId, numeros };
}

function pintarCartonRevision(balotas) {
  const host = document.getElementById('mesaValidarCarton');
  const meta = document.getElementById('mesaValidarBalotasMeta');
  if (!host || !revisionActiva?.numeros) return;
  const list = balotas || [];
  const marcados = setNumerosSorteados(list);
  host.innerHTML = renderCartonHtml(revisionActiva.numeros, marcados, {
    cartonId: revisionActiva.cartonId,
  });
  if (meta) {
    meta.textContent = `${marcados.size} balotas cantadas en vivo · cartón #${revisionActiva.cartonId}`;
  }
}

function lugarJugador(jugador = {}) {
  const ciudad = jugador.ciudad || jugador.ciudadNombre || '';
  const depto = jugador.departamento || jugador.departamentoNombre || '';
  if (ciudad && depto) return `${ciudad}, ${depto}`;
  return ciudad || depto || 'Sin ciudad';
}

function abrirRevisionCantada(reclamo, { onConfirmar, onRechazar }) {
  const body = document.getElementById('mesaValidarBody');
  const oc = getMesaValidarOffcanvas();
  if (!body || !oc) {
    toast('No se pudo abrir el panel de validación');
    return null;
  }

  const jugador = reclamo.jugador || {};
  const nombre = [jugador.nombre, jugador.apellido].filter(Boolean).join(' ') || 'Jugador';
  const lugar = lugarJugador(jugador);

  body.innerHTML = `
    <div class="mesa-validar__meta mb-2">
      <div><span class="text-secondary">Modalidad</span><strong>${escapeHtml(labelModalidad(reclamo.modalidad))}</strong></div>
      <div><span class="text-secondary">Cartón</span><strong>#${escapeHtml(String(reclamo.cartonId))}</strong></div>
      <div><span class="text-secondary">Premio</span><strong class="text-success">${formatMoney(reclamo.premio)}</strong></div>
      <div><span class="text-secondary">Jugador</span><strong>${escapeHtml(nombre)}</strong></div>
      <div class="mesa-validar__ciudad">
        <span class="text-secondary">Ciudad</span>
        <strong>${escapeHtml(lugar)}</strong>
      </div>
    </div>
    <p class="small mb-1">${escapeHtml(hintModalidad(reclamo.modalidad))}</p>
    <p class="small text-secondary mb-2" id="mesaValidarBalotasMeta">Cargando cartón del sistema…</p>
    <div class="mesa-validar__carton flex-grow-1" id="mesaValidarCarton">${loadingHtml('Cargando cartón…')}</div>
    <div class="mesa-validar__actions mt-auto pt-2 border-top">
      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Volver</button>
      <button type="button" class="btn btn-outline-danger btn-sm" data-rechazar-revision="${reclamo.id}">Rechazar</button>
      <button type="button" class="btn btn-success btn-sm" data-confirmar-ganador="${reclamo.id}">
        Confirmar ganador
      </button>
    </div>`;

  body.querySelector('[data-confirmar-ganador]')?.addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    setButtonBusy(btn, true, 'Confirmando…');
    try {
      await onConfirmar(Number(reclamo.id));
      cerrarRevisionCantada();
    } catch (e) {
      toast(e.message || 'No se pudo confirmar');
      setButtonBusy(btn, false);
    }
  });
  body.querySelector('[data-rechazar-revision]')?.addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    setButtonBusy(btn, true, '…');
    try {
      await onRechazar(Number(reclamo.id));
      cerrarRevisionCantada();
    } catch (e) {
      toast(e.message || 'No se pudo rechazar');
      setButtonBusy(btn, false);
    }
  });

  const el = document.getElementById('mesaValidarOffcanvas');
  el?.addEventListener(
    'hidden.bs.offcanvas',
    () => {
      revisionActiva = null;
    },
    { once: true }
  );

  oc.show();
  return body;
}

function pushBalota(balotas, d) {
  if (!d || balotas.some((b) => Number(b.numero) === d.numero)) {
    return balotas;
  }
  const next = balotas.concat([
    {
      numero: d.numero,
      letra: d.letra,
      etiqueta: d.etiqueta,
      orden: d.orden || balotas.length + 1,
    },
  ]);
  return normalizarBalotas(next);
}

export async function renderMesaVivo(root, partidaId) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  const id = Number(partidaId);
  if (!id) {
    root.innerHTML = emptyHtml('Partida no válida.', '<a class="btn btn-primary" href="#/mesa/partidas">Partidas</a>');
    return;
  }

  setMesaVivoPartidaId(id);
  cerrarSse();
  mesaBloqueada = false;
  root.innerHTML = loadingHtml('Abriendo mesa…');

  const puedeValidar = esAdministrador();

  try {
    const [partida, balotasRes, reclamosRes] = await Promise.all([
      api('/partidas/buscar?id=' + encodeURIComponent(id)),
      api('/balotas/listar?partidaId=' + encodeURIComponent(id)),
      api('/reclamos/listar?partidaId=' + encodeURIComponent(id)).catch(() => ({ reclamos: [] })),
    ]);

    if (partida.estado === 'pendiente') {
      root.innerHTML = `
        <div class="border bg-white p-4 text-center">
          <h1 class="h5 font-brand mb-2">${escapeHtml(partida.nombre)}</h1>
          <p class="text-secondary mb-3">La partida aún no está en curso.</p>
          <a class="btn btn-success" href="#/mesa/partidas/${id}">Ir a gestionar e iniciar</a>
        </div>`;
      return;
    }

    if (partida.estado !== 'en_curso') {
      root.innerHTML = `
        <div class="alert alert-secondary">
          <div class="fw-semibold mb-1">Partida no está en vivo</div>
          <p class="small mb-2">Estado: ${escapeHtml(String(partida.estado))}</p>
          <a class="btn btn-outline-primary btn-sm" href="#/mesa/partidas/${id}">Ver partida</a>
        </div>`;
      return;
    }

    let balotas = normalizarBalotas(balotasRes);
    let reclamos = Array.isArray(reclamosRes?.reclamos)
      ? reclamosRes.reclamos
      : Array.isArray(reclamosRes)
        ? reclamosRes
        : [];

    if (balotas.length) {
      marcarBalotaYaVista(balotas[balotas.length - 1].numero);
    }

    const ctx = {
      repaint() {
        const pendientes = reclamos.filter((r) => r.estado === 'pendiente');
        const otros = reclamos.filter((r) => r.estado !== 'pendiente').slice(0, 8);
        const { actual } = sliceHistorial(balotas);
        stopLiveClock?.();
        root.innerHTML = `
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="min-w-0 flex-grow-1">
              <h1 class="h5 mb-0 font-brand text-truncate">${escapeHtml(partida.nombre)}</h1>
              <div class="small text-secondary">Mesa en vivo · #${escapeHtml(String(partida.numero))}</div>
            </div>
            <div class="vivo-head-meta">
              <time class="live-clock" data-live-clock aria-live="off" aria-label="Fecha y hora"></time>
              ${estadoBadge(partida.estado)}
            </div>
          </div>

          <div class="border bg-white p-2 mb-2">
            <div class="small text-secondary mb-1">Premios</div>
            ${renderPremios(partida)}
          </div>

          ${
            mesaBloqueada
              ? `<div class="alert alert-warning py-2" role="alert">
                  <i class="bi bi-pause-circle me-1" aria-hidden="true"></i>
                  Mesa bloqueada: hay una cantada por validar.
                </div>`
              : ''
          }

          <div class="mesa-vivo-tablero mb-2">
            ${renderTablero75Html(balotas)}
          </div>

          <div class="mesa-vivo-fila" aria-label="Rombo, anteriores y cantadas">
            <section class="mesa-vivo-fila__rombo" aria-label="Bombo">
              ${renderBingoDomeHtml(actual)}
              <button type="button" class="btn btn-success btn-sm" id="btnSacarBalota" ${mesaBloqueada || balotas.length >= 75 ? 'disabled' : ''}>
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>${balotas.length >= 75 ? 'Sin balotas' : 'Girar'}
              </button>
            </section>
            <section class="mesa-vivo-fila__anteriores" aria-label="Balotas">
              ${renderBalotasPanelHtml(balotas, { id: 'mesaPanelBalotas', bare: true })}
            </section>
            <section class="mesa-vivo-fila__cantadas" aria-label="Cantadas">
              ${renderCantadasHtml(pendientes, otros, puedeValidar)}
            </section>
          </div>`;

        stopLiveClock = mountLiveClock(root);
        bindActions();
      },
    };

    function bindActions() {
      root.querySelector('#btnSacarBalota')?.addEventListener('click', async () => {
        if (mesaBloqueada) {
          toast('Valida la cantada pendiente antes de seguir', 'warning');
          return;
        }
        unlockAudio();
        const btn = root.querySelector('#btnSacarBalota');
        setButtonBusy(btn, true, 'Girando…');
        try {
          const res = await api('/balotas/sacar', {
            method: 'POST',
            body: { partidaId: id },
          });
          const raw = res.balota || res;
          const d = datosBalota(raw);
          if (res.mesa?.botonesBloqueados != null) {
            mesaBloqueada = !!res.mesa.botonesBloqueados;
          }
          const cerrada =
            !!res.partidaFinalizada ||
            res.partida?.estado === 'finalizada' ||
            Number(res.restantes) === 0;
          if (cerrada) {
            partida.estado = 'finalizada';
          }
          if (d) {
            balotas = pushBalota(balotas, {
              ...d,
              orden: d.orden || balotas.length + 1,
            });
            const payload = {
              numero: d.numero,
              letra: d.letra,
              etiqueta: d.etiqueta,
              orden: d.orden || balotas.length,
              partidaId: id,
            };
            reproducirSorteo(
              payload,
              () => {
                if (cerrada) {
                  toast('Se sacaron las 75 balotas. Partida finalizada.', 'success');
                  cleanupMesaVivo();
                  renderMesaVivo(root, id);
                  return;
                }
                ctx.repaint();
              },
              {
                force: true,
                inline: true,
              }
            );
          } else if (cerrada) {
            toast('Se sacaron las 75 balotas. Partida finalizada.', 'success');
            cleanupMesaVivo();
            renderMesaVivo(root, id);
          } else {
            ctx.repaint();
          }
        } catch (e) {
          const msg = e.message || 'No se pudo sacar';
          if (/75 balotas|finalizada/i.test(msg)) {
            toast(msg, 'info');
            cleanupMesaVivo();
            renderMesaVivo(root, id);
            return;
          }
          toast(msg);
          setButtonBusy(btn, false);
        }
      });

      root.querySelectorAll('[data-revisar]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const reclamoId = Number(btn.getAttribute('data-revisar'));
          setButtonBusy(btn, true, 'Cargando…');
          try {
            await refreshReclamos();
          } catch (_) {
            /* keep local */
          }
          const reclamo = reclamos.find((r) => Number(r.id) === reclamoId);
          if (!reclamo) {
            toast('No se encontró la cantada');
            setButtonBusy(btn, false);
            return;
          }
          abrirRevisionCantada(reclamo, {
            async onConfirmar(idReclamo) {
              await api('/reclamos/aprobar', {
                method: 'POST',
                body: { reclamoId: idReclamo },
              });
              toast('Ganador confirmado', 'success');
              await refreshReclamos();
              mesaBloqueada = reclamos.some((r) => r.estado === 'pendiente');
              ctx.repaint();
            },
            async onRechazar(idReclamo) {
              await api('/reclamos/rechazar', {
                method: 'POST',
                body: { reclamoId: idReclamo },
              });
              toast('Cantada rechazada', 'info');
              await refreshReclamos();
              mesaBloqueada = reclamos.some((r) => r.estado === 'pendiente');
              ctx.repaint();
            },
          });
          try {
            const loaded = await cargarNumerosCarton(reclamo);
            revisionActiva = {
              reclamoId,
              cartonId: loaded.cartonId,
              numeros: loaded.numeros,
            };
            pintarCartonRevision(balotas);
            setButtonBusy(btn, false);
          } catch (e) {
            const host = document.querySelector('#mesaValidarCarton');
            if (host) {
              host.innerHTML = `<div class="alert alert-danger mb-0 py-2 small">${escapeHtml(e.message || 'No se pudo cargar el cartón')}</div>`;
            }
            toast(e.message || 'No se pudo cargar el cartón');
            setButtonBusy(btn, false);
          }
        });
      });

      root.querySelectorAll('[data-rechazar]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const reclamoId = Number(btn.getAttribute('data-rechazar'));
          setButtonBusy(btn, true, '…');
          try {
            await api('/reclamos/rechazar', {
              method: 'POST',
              body: { reclamoId },
            });
            toast('Cantada rechazada', 'info');
            await refreshReclamos();
            mesaBloqueada = reclamos.some((r) => r.estado === 'pendiente');
            ctx.repaint();
          } catch (e) {
            toast(e.message || 'No se pudo rechazar');
            setButtonBusy(btn, false);
          }
        });
      });
    }

    async function refreshReclamos() {
      try {
        const res = await api('/reclamos/listar?partidaId=' + encodeURIComponent(id));
        reclamos = Array.isArray(res?.reclamos)
          ? res.reclamos
          : Array.isArray(res)
            ? res
            : [];
      } catch (_) {
        /* keep */
      }
    }

    mesaBloqueada = reclamos.some((r) => r.estado === 'pendiente');
    root.addEventListener('pointerdown', () => unlockAudio(), { once: true });
    ctx.repaint();
    mesaVivoActivo = true;

    const token = getToken();
    const sseKey = `bingo_sse_${id}`;
    let ultimoEventoId = Number(sessionStorage.getItem(sseKey) || 0);

    function guardarCursor(eid) {
      const n = Number(eid) || 0;
      if (n > ultimoEventoId) {
        ultimoEventoId = n;
        sessionStorage.setItem(sseKey, String(n));
      }
    }

    async function resyncMesa() {
      const [balotasRes, reclamosRes] = await Promise.all([
        api('/balotas/listar?partidaId=' + encodeURIComponent(id)),
        api('/reclamos/listar?partidaId=' + encodeURIComponent(id)).catch(() => ({
          reclamos: [],
        })),
      ]);
      balotas = normalizarBalotas(balotasRes);
      if (balotas.length) {
        marcarBalotaYaVista(balotas[balotas.length - 1].numero);
      }
      reclamos = Array.isArray(reclamosRes?.reclamos)
        ? reclamosRes.reclamos
        : Array.isArray(reclamosRes)
          ? reclamosRes
          : [];
      mesaBloqueada = reclamos.some((r) => r.estado === 'pendiente');
      ctx.repaint();
      sessionStorage.setItem(sseKey, '0');
      ultimoEventoId = 0;
    }

    function conectar(desdeId) {
      if (!mesaVivoActivo) return;
      cerrarSse();
      const url =
        CONFIG.apiBase +
        '/partidas/eventos?partidaId=' +
        encodeURIComponent(id) +
        '&desdeId=' +
        encodeURIComponent(desdeId) +
        '&token=' +
        encodeURIComponent(token);

      eventSource = new EventSource(url);

      eventSource.addEventListener('mesa', (ev) => {
        try {
          const data = JSON.parse(ev.data);
          if (data.ultimoEventoId != null) guardarCursor(data.ultimoEventoId);
          if (ev.lastEventId) guardarCursor(ev.lastEventId);
        } catch (e) {
          console.warn('[mesa-vivo] mesa', e);
        }
      });

      eventSource.addEventListener('balota', (ev) => {
        try {
          const data = JSON.parse(ev.data);
          const raw = {
            numero: data.numero ?? data.balota?.numero,
            letra: data.letra ?? data.balota?.letra,
            etiqueta: data.etiqueta ?? data.balota?.etiqueta,
            orden: data.orden ?? data.balota?.orden ?? balotas.length + 1,
          };
          const d = datosBalota(raw);
          if (!d) return;

          const yaTenía = balotas.some((b) => Number(b.numero) === d.numero);
          const yaAnimada = getUltimaBalotaAnimada() === d.numero;
          balotas = pushBalota(balotas, d);

          if (data.mesa?.botonesBloqueados != null) {
            mesaBloqueada = !!data.mesa.botonesBloqueados;
          }

          if (revisionActiva) {
            pintarCartonRevision(balotas);
          }

          if (!yaTenía && !yaAnimada) {
            reproducirSorteo(
              {
                numero: d.numero,
                letra: d.letra,
                etiqueta: d.etiqueta,
                orden: d.orden,
                partidaId: id,
              },
              () => ctx.repaint(),
              { force: true, inline: true }
            );
          } else if (!yaTenía) {
            ctx.repaint();
          }

          if (ev.lastEventId) guardarCursor(ev.lastEventId);
        } catch (e) {
          console.warn('[mesa-vivo] balota', e);
        }
      });

      const onReclamo = async (ev) => {
        try {
          const data = JSON.parse(ev.data);
          if (data.mesa?.botonesBloqueados != null) {
            mesaBloqueada = !!data.mesa.botonesBloqueados;
          }
          await refreshReclamos();
          mesaBloqueada =
            mesaBloqueada || reclamos.some((r) => r.estado === 'pendiente');
          ctx.repaint();
          if (ev.lastEventId) guardarCursor(ev.lastEventId);
        } catch (e) {
          console.warn('[mesa-vivo] reclamo', e);
        }
      };

      eventSource.addEventListener('reclamo_pendiente', (ev) => {
        (async () => {
          try {
            const data = JSON.parse(ev.data);
            const modalidad = data.reclamo?.modalidad || data.modalidad || '';
            const reclamoId = data.reclamo?.id || data.id;
            const estado = String(data.reclamo?.estado || 'pendiente');
            if (estado === 'pendiente') {
              await unlockAudio();
              celebrarCantada(modalidad, { mesa: true, reclamoId });
              await sonarCantar({ reclamoId });
            }
          } catch (e) {
            console.warn('[mesa-vivo] cantada', e);
          }
        })();
        onReclamo(ev);
      });
      eventSource.addEventListener('reclamo_aprobado', onReclamo);
      eventSource.addEventListener('reclamo_rechazado', onReclamo);

      eventSource.addEventListener('partida_finalizada', (ev) => {
        try {
          if (ev.lastEventId) guardarCursor(ev.lastEventId);
        } catch (e) {
          console.warn('[mesa-vivo] finalizada', e);
        }
        partida.estado = 'finalizada';
        toast('Partida finalizada', 'success');
        cleanupMesaVivo();
        renderMesaVivo(root, id);
      });

      eventSource.addEventListener('timeout', (ev) => {
        try {
          const data = JSON.parse(ev.data);
          if (data.reconectarConDesdeId != null) {
            guardarCursor(data.reconectarConDesdeId);
          }
        } catch (e) {
          console.warn('[mesa-vivo] timeout', e);
        }
        conectar(ultimoEventoId);
      });

      eventSource.onerror = () => {
        if (!mesaVivoActivo || !eventSource) return;
        if (eventSource.readyState !== EventSource.CLOSED) return;
        if (mesaSseRetryTimer) window.clearTimeout(mesaSseRetryTimer);
        mesaSseRetryTimer = window.setTimeout(() => {
          mesaSseRetryTimer = null;
          if (!mesaVivoActivo || document.visibilityState === 'hidden') return;
          conectar(ultimoEventoId);
        }, 1200);
      };
    }

    disposeMesaResume?.();
    disposeMesaResume = bindLiveResume({
      isActive: () => mesaVivoActivo,
      resync: resyncMesa,
      reconnectLive: () => conectar(0),
    });

    conectar(ultimoEventoId);
  } catch (e) {
    toast(e.message || 'No se pudo abrir la mesa');
    root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
  }
}
