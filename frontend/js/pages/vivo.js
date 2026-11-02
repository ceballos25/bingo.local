import { api } from '../api.js';
import { getToken, getUsuario, requireAuth } from '../auth.js';
import { CONFIG } from '../config.js';
import { loadDaubs, toggleDaub } from '../daubs.js';
import {
  renderBalotasPanelHtml,
  datosBalota,
} from '../balota.js';
import {
  unlockAudio,
  isMuted,
  toggleMuted,
  sonarCorrediza,
  sonarCantar,
  sonarError,
} from '../sound.js';
import { celebrarCantada } from '../celebrate.js';
import {
  cleanupDrawOverlay,
  marcarBalotaYaVista,
  reproducirSorteo,
} from '../draw-overlay.js';
import { bindLiveResume } from '../live-resume.js';
import {
  escapeHtml,
  mountLiveClock,
  renderCartonHtml,
  toast,
  loadingHtml,
  emptyHtml,
  estadoBadge,
  formatFecha,
  normalizarBalotas,
  premioLineHtml,
} from '../ui.js';

let eventSource = null;
let cartonActivoId = null;
let stopLiveClock = null;
/** Transmisión grande (overlay). Se conserva entre repaints. */
let streamGrande = false;
/** Preferencia de sonido de la transmisión (botón Silencio). */
let streamConSonido = true;
let ytPlayer = null;
let ytApiPromise = null;
/** Sesión viva activa (para resume al volver a la pestaña). */
let vivoActivo = false;
let disposeResume = null;
let sseRetryTimer = null;

function cerrarSse() {
  if (sseRetryTimer) {
    window.clearTimeout(sseRetryTimer);
    sseRetryTimer = null;
  }
  if (eventSource) {
    eventSource.close();
    eventSource = null;
  }
}

function destroyYtPlayer() {
  try {
    ytPlayer?.destroy?.();
  } catch (_) {
    /* ignore */
  }
  ytPlayer = null;
}

export function cleanupVivo() {
  vivoActivo = false;
  disposeResume?.();
  disposeResume = null;
  stopLiveClock?.();
  stopLiveClock = null;
  streamGrande = false;
  destroyYtPlayer();
  cerrarSse();
  cleanupDrawOverlay();
}

function modalidadesActivas(partida) {
  return (Array.isArray(partida?.modalidades) ? partida.modalidades : [])
    .map((m) => String(m.modalidad || '').toLowerCase())
    .filter((m) => m === 'linea' || m === 'equis' || m === 'completa');
}

/** Solo botones de las modalidades configuradas en la partida. */
function renderCantarBar(cartonId, partida) {
  const activas = new Set(modalidadesActivas(partida));
  const botones = [];

  if (activas.has('linea')) {
    botones.push(`
      <button type="button" class="btn btn-outline-primary" data-cantar="linea" data-carton-id="${cartonId}" title="Cantar L">
        <i class="bi bi-megaphone-fill" aria-hidden="true"></i>
        <span>L</span>
      </button>`);
  }
  if (activas.has('equis')) {
    botones.push(`
      <button type="button" class="btn btn-outline-primary" data-cantar="equis" data-carton-id="${cartonId}" title="Cantar Equis">
        <i class="bi bi-megaphone-fill" aria-hidden="true"></i>
        <span>Equis</span>
      </button>`);
  }
  if (activas.has('completa')) {
    botones.push(`
      <button type="button" class="btn btn-bingo" data-cantar="completa" data-carton-id="${cartonId}" title="Cantar Bingo">
        <i class="bi bi-megaphone-fill" aria-hidden="true"></i>
        <span>Bingo</span>
      </button>`);
  }

  if (!botones.length) return '';

  return `
    <div class="vivo-cantar" role="group" aria-label="Cantar cartón ${cartonId}">
      <p class="vivo-cantar__hint mb-0">Si ganaste, toca:</p>
      <div class="btn-group btn-group-sm vivo-cantar__btns" role="group">
        ${botones.join('')}
      </div>
    </div>`;
}

function youtubeVideoId(partida) {
  const id = String(partida.youtubeVideoId || '').trim();
  if (/^[A-Za-z0-9_-]{11}$/.test(id)) return id;
  const embed = String(partida.youtubeEmbedUrl || '');
  const m = embed.match(/\/embed\/([A-Za-z0-9_-]{11})/);
  return m ? m[1] : '';
}

function loadYtApi() {
  if (window.YT?.Player) return Promise.resolve();
  if (ytApiPromise) return ytApiPromise;
  ytApiPromise = new Promise((resolve) => {
    const prev = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = () => {
      try {
        prev?.();
      } catch (_) {
        /* ignore */
      }
      resolve();
    };
    if (!document.getElementById('youtubeIframeApi')) {
      const s = document.createElement('script');
      s.id = 'youtubeIframeApi';
      s.src = 'https://www.youtube.com/iframe_api';
      document.head.appendChild(s);
    }
  });
  return ytApiPromise;
}

function syncStreamButtons(root) {
  const playBtn = root.querySelector('#btnStreamPlay');
  const muteBtn = root.querySelector('#btnStreamMute');
  if (!playBtn && !muteBtn) return;

  let playing = false;
  try {
    const st = ytPlayer?.getPlayerState?.();
    playing = st === window.YT?.PlayerState?.PLAYING || st === window.YT?.PlayerState?.BUFFERING;
  } catch (_) {
    playing = false;
  }

  if (playBtn) {
    playBtn.innerHTML = playing
      ? '<i class="bi bi-pause-fill" aria-hidden="true"></i>'
      : '<i class="bi bi-play-fill" aria-hidden="true"></i>';
    playBtn.setAttribute('aria-label', playing ? 'Pausa' : 'Play');
    playBtn.setAttribute('title', playing ? 'Pausa' : 'Play');
    playBtn.setAttribute('aria-pressed', playing ? 'true' : 'false');
  }
  if (muteBtn) {
    muteBtn.innerHTML = streamConSonido
      ? '<i class="bi bi-volume-up-fill" aria-hidden="true"></i>'
      : '<i class="bi bi-volume-mute-fill" aria-hidden="true"></i>';
    muteBtn.setAttribute('aria-label', streamConSonido ? 'Silencio' : 'Sonido');
    muteBtn.setAttribute('title', streamConSonido ? 'Silencio' : 'Sonido');
    muteBtn.setAttribute('aria-pressed', streamConSonido ? 'false' : 'true');
  }
}

function aplicarSonidoYt() {
  if (!ytPlayer) return;
  try {
    if (streamConSonido) {
      ytPlayer.unMute();
      ytPlayer.setVolume(100);
    } else {
      ytPlayer.mute();
    }
  } catch (_) {
    /* ignore */
  }
}

async function mountYtPlayer(root, videoId) {
  const host = root.querySelector('#vivoYtPlayer');
  if (!host || !videoId) return;
  destroyYtPlayer();
  await loadYtApi();
  if (!root.querySelector('#vivoYtPlayer')) return;

  ytPlayer = new window.YT.Player('vivoYtPlayer', {
    width: '100%',
    height: '100%',
    videoId,
    playerVars: {
      playsinline: 1,
      rel: 0,
      modestbranding: 1,
      controls: 0,
      disablekb: 1,
      fs: 0,
      autoplay: 1,
      mute: streamConSonido ? 0 : 1,
      origin: location.origin,
    },
    events: {
      onReady: (ev) => {
        aplicarSonidoYt();
        try {
          ev.target.playVideo();
        } catch (_) {
          /* ignore */
        }
        syncStreamButtons(root);
      },
      onStateChange: () => syncStreamButtons(root),
    },
  });
}

function renderStream(_partida) {
  // Transmisión pausada de momento (YouTube en vivo se reactivará después).
  return '';
}

function renderPremios(partida) {
  const mods = Array.isArray(partida.modalidades) ? partida.modalidades : [];
  if (!mods.length) return '';
  return `
    <div class="vivo-premios" aria-label="Premios">
      <span class="vivo-premios__title">Premios</span>
      ${mods.map((m) => premioLineHtml(m.modalidad, m.premio, 'chip')).join('')}
    </div>`;
}

function renderCartonCard(partidaId, c, partida) {
  return `
    <article class="vivo-carton" data-carton-wrap="${c.id}">
      ${renderCartonHtml(c.numeros, loadDaubs(partidaId, c.id), {
        interactivo: true,
        cartonId: c.id,
      })}
      ${renderCantarBar(c.id, partida)}
    </article>`;
}

/** 2+ cartones → pestañas compactas + un cartón visible. */
function renderCartonesBlock(partidaId, mios, partida) {
  const total = mios.length;
  const activo = mios.find((c) => c.id === cartonActivoId) || mios[0];
  const selector =
    total > 1
      ? `<div class="vivo-carton-tabs" role="tablist" aria-label="Elige tu cartón">
          ${mios
            .map((c) => {
              const on = c.id === activo.id;
              return `<button type="button" role="tab" class="vivo-carton-tabs__btn ${on ? 'is-on' : ''}"
                data-select-carton="${c.id}" aria-selected="${on ? 'true' : 'false'}">
                <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
                Cartón #${c.id}
              </button>`;
            })
            .join('')}
        </div>`
      : '';

  return `
    <section class="vivo-cartones" aria-label="Tus cartones">
      ${selector}
      ${renderCartonCard(partidaId, activo, partida)}
    </section>`;
}

function syncStreamToggleLabel(root) {
  const btn = root.querySelector('#btnStreamToggle');
  if (!btn) return;
  btn.innerHTML = streamGrande
    ? '<i class="bi bi-fullscreen-exit" aria-hidden="true"></i>'
    : '<i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>';
  btn.setAttribute('aria-label', streamGrande ? 'Volver' : 'Grande');
  btn.setAttribute('title', streamGrande ? 'Volver' : 'Grande');
}

function bindCartonesUi(root, partidaId, mios, ctx) {
  root.querySelectorAll('.carton .cell-tap').forEach((cell) => {
    const tap = async () => {
      await unlockAudio();
      const cartonEl = cell.closest('.carton');
      const cartonId = Number(cartonEl?.getAttribute('data-carton-id'));
      const n = Number(cell.getAttribute('data-n'));
      if (!cartonId || !n) return;
      const set = toggleDaub(partidaId, cartonId, n);
      const on = set.has(n);
      cell.classList.toggle('tachado', on);
      cell.setAttribute('aria-pressed', on ? 'true' : 'false');
      if (on) sonarCorrediza();
    };
    cell.addEventListener('click', tap);
    cell.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        tap();
      }
    });
  });

  root.querySelectorAll('[data-cantar]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      await unlockAudio();
      const modalidad = btn.getAttribute('data-cantar');
      const cartonId = Number(btn.getAttribute('data-carton-id'));
      if (!cartonId) {
        sonarError();
        toast('Cartón no válido', 'warning');
        return;
      }
      btn.disabled = true;
      try {
        const res = await api('/reclamos/cantar', {
          method: 'POST',
          body: { cartonId, modalidad },
        });
        cartonActivoId = cartonId;
        celebrarCantada(modalidad, {
          reclamoId: res?.id,
          sub: '¡Cantada enviada!',
        });
        await sonarCantar({ reclamoId: res?.id });
        const etiqueta =
          modalidad === 'linea'
            ? 'L'
            : modalidad === 'equis'
              ? 'Equis'
              : 'Bingo';
        toast(`¡${etiqueta} cantada en #${cartonId}! El operador validará tu cantada.`, 'success');
      } catch (e) {
        sonarError();
        toast(e.message || 'No se pudo cantar');
      } finally {
        btn.disabled = false;
      }
    });
  });

  root.querySelectorAll('[data-select-carton]').forEach((btn) => {
    btn.addEventListener('click', () => {
      cartonActivoId = Number(btn.getAttribute('data-select-carton'));
      ctx.updateCartones();
    });
  });
}

function bindStreamUi(root, ctx) {
  root.querySelector('#btnStreamToggle')?.addEventListener('click', async () => {
    await unlockAudio();
    ctx.toggleStreamGrande();
  });

  root.querySelector('#btnStreamPlay')?.addEventListener('click', async () => {
    await unlockAudio();
    if (!ytPlayer?.getPlayerState) return;
    try {
      const st = ytPlayer.getPlayerState();
      const playing =
        st === window.YT.PlayerState.PLAYING || st === window.YT.PlayerState.BUFFERING;
      if (playing) ytPlayer.pauseVideo();
      else {
        aplicarSonidoYt();
        ytPlayer.playVideo();
      }
    } catch (_) {
      /* ignore */
    }
    syncStreamButtons(root);
  });

  root.querySelector('#btnStreamMute')?.addEventListener('click', async () => {
    await unlockAudio();
    streamConSonido = !streamConSonido;
    aplicarSonidoYt();
    syncStreamButtons(root);
  });
}

function bindUi(root, partidaId, mios, ctx, { mountYt = true } = {}) {
  root.querySelector('#btnMute')?.addEventListener('click', async () => {
    await unlockAudio();
    const muted = toggleMuted();
    const btn = root.querySelector('#btnMute');
    if (btn) {
      btn.innerHTML = `<i class="bi ${muted ? 'bi-volume-mute' : 'bi-volume-up'}" aria-hidden="true"></i>`;
    }
  });

  bindStreamUi(root, ctx);
  bindCartonesUi(root, partidaId, mios, ctx);

  if (mountYt) {
    const ytId = root.querySelector('.vivo-stream-wrap')?.getAttribute('data-yt-id');
    if (ytId) mountYtPlayer(root, ytId);
  }
}

export async function renderVivo(root, partidaId) {
  if (!requireAuth()) {
    root.innerHTML = emptyHtml(
      'Inicia sesión para jugar en vivo.',
      '<a class="btn btn-primary" href="#/cuenta"><i class="bi bi-person me-1" aria-hidden="true"></i>Ir a cuenta</a>'
    );
    return;
  }

  cerrarSse();
  root.innerHTML = loadingHtml('Conectando mesa…');

  try {
    const [partida, cartones, balotasRes] = await Promise.all([
      api('/partidas/buscar?id=' + encodeURIComponent(partidaId)),
      api('/cartones/listar?partidaId=' + encodeURIComponent(partidaId)),
      api('/balotas/listar?partidaId=' + encodeURIComponent(partidaId)),
    ]);

    let balotas = normalizarBalotas(balotasRes);
    const miId = Number(getUsuario()?.id || 0);
    const mios = (cartones || []).filter((c) => {
      if (c.estado !== 'vendido') return false;
      if (!miId) return true;
      return Number(c.usuarioId) === miId;
    });

    if (mios.length === 0) {
      root.innerHTML = `
        <div class="alert alert-warning" role="alert">
          <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Aún no tienes cartón en esta partida</div>
          <p class="mb-2 small mb-3">Para entrar a la mesa necesitas <strong>haber pagado tu cartón</strong>.</p>
          <a class="btn btn-primary btn-sm" href="#/comprar/${partidaId}"><i class="bi bi-cart3 me-1" aria-hidden="true"></i>Comprar cartón</a>
        </div>`;
      return;
    }

    if (partida.estado === 'pendiente') {
      root.innerHTML = `
        <div class="border bg-white p-4 text-center">
          <i class="bi bi-clock-history display-6 text-primary mb-2" aria-hidden="true"></i>
          <h1 class="h5 font-brand mb-1">${escapeHtml(partida.nombre)}</h1>
          <p class="text-secondary mb-3">El bingo aún no arranca. Vuelve a la hora programada.</p>
          <div class="fs-5 fw-semibold text-primary mb-3">
            <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>${escapeHtml(formatFecha(partida.fecha))}
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="#/mis-cartones"><i class="bi bi-play-circle me-1" aria-hidden="true"></i>Jugar</a>
        </div>`;
      return;
    }

    if (partida.estado !== 'en_curso') {
      const jugada = partida.estado === 'finalizada';
      root.innerHTML = `
        <div class="alert alert-secondary" role="alert">
          <div class="fw-semibold mb-1">${jugada ? 'Este bingo ya se jugó' : 'Esta partida no está en vivo'}</div>
          <p class="mb-2 small">Estado: ${escapeHtml(String(partida.estado).replace('_', ' '))}</p>
          <a class="btn btn-outline-secondary btn-sm" href="#/mis-cartones"><i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i>Ver mis cartones</a>
        </div>`;
      return;
    }

    cartonActivoId = mios[0].id;
    if (balotas.length) {
      marcarBalotaYaVista(balotas[balotas.length - 1].numero);
    }

    const ctx = {
      /** Solo al entrar o si falta el layout. No usar al sacar balota (reinicia YouTube). */
      repaint({ animActual = false } = {}) {
        stopLiveClock?.();
        destroyYtPlayer();
        root.innerHTML = `
          <div class="vivo-play">
            <header class="vivo-play__head">
              <h1 class="vivo-play__name font-brand text-truncate mb-0">${escapeHtml(partida.nombre)}</h1>
              ${estadoBadge(partida.estado)}
              <time class="live-clock vivo-play__clock" data-live-clock aria-live="off" aria-label="Fecha y hora"></time>
              <button type="button" class="btn btn-sm btn-outline-secondary vivo-play__mute" id="btnMute" title="Sonido" aria-label="Sonido">
                <i class="bi ${isMuted() ? 'bi-volume-mute' : 'bi-volume-up'}" aria-hidden="true"></i>
              </button>
            </header>
            <div class="vivo-top-row">
              <div class="vivo-balotas">
                ${renderBalotasPanelHtml(balotas, { animActual, bare: true })}
              </div>
              ${renderStream(partida)}
            </div>
            ${renderCartonesBlock(partidaId, mios, partida)}
            ${renderPremios(partida)}
          </div>`;

        stopLiveClock = mountLiveClock(root);
        bindUi(root, partidaId, mios, ctx, { mountYt: true });
      },

      /** Tras una balota: solo el panel, el video sigue. */
      updateBalotas(animActual = false) {
        const box = root.querySelector('.vivo-balotas');
        if (!box) {
          this.repaint({ animActual });
          return;
        }
        box.innerHTML = renderBalotasPanelHtml(balotas, { animActual, bare: true });
      },

      /** Cambiar de cartón sin tocar la transmisión. */
      updateCartones() {
        const sec = root.querySelector('.vivo-cartones');
        if (!sec) {
          this.repaint();
          return;
        }
        const tmp = document.createElement('div');
        tmp.innerHTML = renderCartonesBlock(partidaId, mios, partida);
        const next = tmp.firstElementChild;
        if (!next) return;
        sec.replaceWith(next);
        bindCartonesUi(root, partidaId, mios, ctx);
      },

      /** Mini ↔ grande solo con CSS (no reinicia el iframe). */
      toggleStreamGrande() {
        streamGrande = !streamGrande;
        const wrap = root.querySelector('.vivo-stream-wrap');
        if (!wrap) {
          this.repaint();
          return;
        }
        wrap.classList.toggle('is-grande', streamGrande);
        wrap.classList.toggle('is-mini', !streamGrande);
        syncStreamToggleLabel(root);
      },
    };

    root.addEventListener('pointerdown', () => unlockAudio(), { once: true });

    ctx.repaint();
    vivoActivo = true;

    const token = getToken();
    const sseKey = `bingo_sse_${partidaId}`;
    let ultimoEventoId = Number(sessionStorage.getItem(sseKey) || 0);

    function guardarCursor(id) {
      const n = Number(id) || 0;
      if (n > ultimoEventoId) {
        ultimoEventoId = n;
        sessionStorage.setItem(sseKey, String(n));
      }
    }

    async function resyncBalotas() {
      const balotasRes = await api(
        '/balotas/listar?partidaId=' + encodeURIComponent(partidaId)
      );
      balotas = normalizarBalotas(balotasRes);
      if (balotas.length) {
        marcarBalotaYaVista(balotas[balotas.length - 1].numero);
      }
      ctx.updateBalotas(false);
      // Cursor al día: el próximo SSE con desdeId=0 arranca en el último evento.
      sessionStorage.setItem(sseKey, '0');
      ultimoEventoId = 0;
    }

    function conectar(desdeId) {
      if (!vivoActivo) return;
      cerrarSse();
      const url =
        CONFIG.apiBase +
        '/partidas/eventos?partidaId=' +
        encodeURIComponent(partidaId) +
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
          console.warn('[vivo] mesa', e);
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

          if (!balotas.some((b) => Number(b.numero) === d.numero)) {
            const nueva = {
              numero: d.numero,
              letra: d.letra,
              etiqueta: d.etiqueta,
              orden: d.orden,
            };
            balotas.push(nueva);
            balotas = normalizarBalotas(balotas);

            // Solo actualiza balotas: un repaint completo reiniciaba YouTube.
            reproducirSorteo(nueva, () => {
              ctx.updateBalotas(true);
            });
          }

          if (data.partidaFinalizada || data.partida?.estado === 'finalizada') {
            toast('Partida finalizada', 'success');
            cerrarSse();
            setTimeout(() => renderVivo(root, partidaId), 400);
          }

          if (ev.lastEventId) guardarCursor(ev.lastEventId);
        } catch (e) {
          console.warn('[vivo] balota', e);
        }
      });

      eventSource.addEventListener('partida_finalizada', (ev) => {
        try {
          if (ev.lastEventId) guardarCursor(ev.lastEventId);
        } catch (e) {
          console.warn('[vivo] partida_finalizada', e);
        }
        toast('Partida finalizada', 'success');
        cerrarSse();
        renderVivo(root, partidaId);
      });

      // Cantada en vivo → todos los hosts (jugadores) ven/oyen lo mismo
      eventSource.addEventListener('reclamo_pendiente', async (ev) => {
        try {
          const data = JSON.parse(ev.data);
          const modalidad = data.reclamo?.modalidad || data.modalidad || '';
          const reclamoId = data.reclamo?.id || data.id;
          // No festejar cantadas ya resueltas (historial SSE)
          const estado = String(data.reclamo?.estado || 'pendiente');
          if (estado === 'pendiente') {
            await unlockAudio();
            celebrarCantada(modalidad, { reclamoId });
            await sonarCantar({ reclamoId });
          }
          if (ev.lastEventId) guardarCursor(ev.lastEventId);
        } catch (e) {
          console.warn('[vivo] reclamo', e);
        }
      });

      eventSource.addEventListener('timeout', (ev) => {
        try {
          const data = JSON.parse(ev.data);
          if (data.reconectarConDesdeId != null) {
            guardarCursor(data.reconectarConDesdeId);
          }
        } catch (e) {
          console.warn('[vivo] timeout', e);
        }
        conectar(ultimoEventoId);
      });

      eventSource.onerror = () => {
        if (!vivoActivo || !eventSource) return;
        // CONNECTING = reintento nativo; CLOSED = hay que abrir de nuevo con cursor fresco.
        if (eventSource.readyState !== EventSource.CLOSED) return;
        if (sseRetryTimer) window.clearTimeout(sseRetryTimer);
        sseRetryTimer = window.setTimeout(() => {
          sseRetryTimer = null;
          if (!vivoActivo || document.visibilityState === 'hidden') return;
          conectar(ultimoEventoId);
        }, 1200);
      };
    }

    disposeResume?.();
    disposeResume = bindLiveResume({
      isActive: () => vivoActivo,
      resync: resyncBalotas,
      reconnectLive: () => conectar(0),
    });

    conectar(ultimoEventoId);
  } catch (e) {
    if (e.status === 403) {
      root.innerHTML = `
        <div class="alert alert-warning" role="alert">
          <div class="fw-semibold mb-1"><i class="bi bi-lock me-1" aria-hidden="true"></i>Acceso restringido</div>
          <p class="mb-3 small">${escapeHtml(e.message)}</p>
          <a class="btn btn-primary btn-sm" href="#/comprar/${partidaId}"><i class="bi bi-cart3 me-1" aria-hidden="true"></i>Comprar cartón</a>
        </div>`;
      return;
    }
    toast(e.message || 'No se pudo abrir el vivo');
    root.innerHTML = `<div class="alert alert-danger mb-0" role="alert">${escapeHtml(e.message)}</div>`;
  }
}
