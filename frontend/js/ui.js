/** Paleta fija para avatares (índice por hash del usuario, no aleatorio). */
const AVATAR_COLORS = [
  '#003399',
  '#00994c',
  '#c40000',
  '#7a5c00',
  '#5b2c6f',
  '#1a5276',
  '#117a65',
  '#922b21',
];

/** Iniciales estables: nombre + apellido (máx. 2 letras). */
export function inicialesUsuario(usuario) {
  const n = String(usuario?.nombre || '').trim();
  const a = String(usuario?.apellido || '').trim();
  if (n && a) return (n[0] + a[0]).toUpperCase();
  if (n.length >= 2) return n.slice(0, 2).toUpperCase();
  if (n) return (n[0] + n[0]).toUpperCase();
  return '?';
}

/** Color determinista según id o nombre (mismo usuario = mismo color). */
export function colorAvatarUsuario(usuario) {
  const key = String(usuario?.id ?? `${usuario?.nombre || ''}|${usuario?.apellido || ''}`);
  let hash = 0;
  for (let i = 0; i < key.length; i++) {
    hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
  }
  return AVATAR_COLORS[hash % AVATAR_COLORS.length];
}

export function toast(message, type = 'danger') {
  const host = document.getElementById('toastHost');
  if (!host || typeof bootstrap === 'undefined') {
    console.warn(message);
    return;
  }

  // Variantes oficiales Bootstrap (Toast + text-bg-*)
  const map = {
    danger: { bg: 'text-bg-danger', close: 'btn-close-white' },
    success: { bg: 'text-bg-success', close: 'btn-close-white' },
    warning: { bg: 'text-bg-warning', close: 'btn-close' },
    info: { bg: 'text-bg-primary', close: 'btn-close-white' },
  };
  const conf = map[type] || map.danger;

  const el = document.createElement('div');
  el.className = `toast align-items-center ${conf.bg} border-0`;
  el.setAttribute('role', 'alert');
  el.setAttribute('aria-live', 'assertive');
  el.setAttribute('aria-atomic', 'true');
  el.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">${escapeHtml(message)}</div>
      <button type="button" class="btn-close ${conf.close} me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
    </div>`;
  host.appendChild(el);
  const instance = bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 });
  el.addEventListener('hidden.bs.toast', () => el.remove());
  instance.show();
}

export function formatMoney(n) {
  return new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
  }).format(Number(n) || 0);
}

/**
 * Enlace público para jugadores (misma org del deploy / CONFIG).
 * Ej: https://bingo.local/app/#/comprar/12
 */
export function urlPublicaComprar(partidaId) {
  const id = Number(partidaId);
  if (!id) return '';
  const path = String(location.pathname || '/').replace(/index\.html$/i, '');
  const base = path.endsWith('/') ? path : `${path}/`;
  return `${location.origin}${base}#/comprar/${id}`;
}

export async function copiarTexto(texto) {
  const value = String(texto || '');
  if (!value) return false;
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value);
      return true;
    }
  } catch {
    /* fallback abajo */
  }
  try {
    const ta = document.createElement('textarea');
    ta.value = value;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    const ok = document.execCommand('copy');
    ta.remove();
    return ok;
  } catch {
    return false;
  }
}

export function modalidadKey(m) {
  return String(m || '').toLowerCase();
}

/** Etiqueta fija: L · Equis · Completa */
export function labelModalidad(m) {
  const map = { linea: 'L', equis: 'Equis', completa: 'Completa' };
  const key = modalidadKey(m);
  return map[key] || String(m || '').replace(/_/g, ' ');
}

export function emojiModalidad(m) {
  const map = { linea: '🔥', equis: '✨', completa: '🏆' };
  return map[modalidadKey(m)] || '🎯';
}

/**
 * Apariencia unificada de premio.
 * Ejemplo: 🔥 L 💰 $ 1.000.000
 * @param {'inline'|'row'|'chip'} variant
 */
export function premioLineHtml(modalidad, premio, variant = 'inline') {
  const emoji = emojiModalidad(modalidad);
  const label = escapeHtml(labelModalidad(modalidad));
  const money = escapeHtml(formatMoney(premio));
  const cash = `<span class="premio-line__cash">💰 ${money}</span>`;
  const mod = `<span class="premio-line__mod">${emoji} ${label}</span>`;

  if (variant === 'row') {
    return `<span class="premio-line premio-line--row">${mod}${cash}</span>`;
  }
  if (variant === 'chip') {
    return `<span class="premio-line premio-line--chip">${mod} ${cash}</span>`;
  }
  return `<span class="premio-line">${mod} ${cash}</span>`;
}

/** Lista de premios con la misma apariencia en landing / carrito / mesa. */
export function premiosListHtml(modalidades, opts = {}) {
  const list = Array.isArray(modalidades) ? modalidades : [];
  const empty =
    opts.emptyHtml ||
    `<p class="small text-secondary mb-0">Premios por confirmar</p>`;
  if (!list.length) return empty;

  const ulClass = opts.ulClass || 'list-unstyled mb-0';
  const liClass = opts.liClass || 'py-1';
  return `
    <ul class="${ulClass}">
      ${list
        .map(
          (m) =>
            `<li class="${liClass}">${premioLineHtml(m.modalidad, m.premio, 'row')}</li>`
        )
        .join('')}
    </ul>`;
}

export function formatFecha(fecha) {
  if (!fecha) return '';
  const d = new Date(fecha.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return fecha;
  return d.toLocaleString('es-CO', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

/** Fecha + hora con segundos (reloj en vivo). */
export function formatRelojEnVivo(date = new Date(), compact = false) {
  const d = date instanceof Date ? date : new Date();
  const fecha = compact
    ? d.toLocaleDateString('es-CO', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
      })
    : d.toLocaleDateString('es-CO', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      });
  const hora = d.toLocaleTimeString('es-CO', {
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
  });
  return { fecha, hora, iso: d.toISOString() };
}

/**
 * Actualiza un <time data-live-clock> cada segundo.
 * @returns {() => void} stop
 */
export function mountLiveClock(root, selector = '[data-live-clock]') {
  const el = root?.querySelector?.(selector);
  if (!el) return () => {};

  const tick = () => {
    const compact =
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(max-width: 767.98px)').matches;
    const { fecha, hora, iso } = formatRelojEnVivo(new Date(), compact);
    el.dateTime = iso;
    el.innerHTML =
      `<span class="live-clock__date">${fecha}</span>` +
      `<span class="live-clock__time">${hora}</span>`;
  };

  tick();
  const id = window.setInterval(tick, 1000);
  return () => window.clearInterval(id);
}

export function estadoBadge(estado) {
  // Clases Bootstrap nativas (contraste OK sobre hero azul y cards blancas)
  const map = {
    pendiente: {
      className: 'badge text-bg-warning',
      label: 'Pendiente',
    },
    en_curso: {
      className: 'badge bg-light text-success border border-success',
      label: 'En curso',
    },
    finalizada: {
      className: 'badge text-bg-secondary',
      label: 'Finalizada',
    },
    cancelada: {
      className: 'badge text-bg-dark',
      label: 'Cancelada',
    },
    disponible: {
      className: 'badge text-bg-primary',
      label: 'Disponible',
    },
    reservado: {
      className: 'badge text-bg-warning',
      label: 'Reservado',
    },
    vendido: {
      className: 'badge bg-success-subtle text-success-emphasis border border-success',
      label: 'Vendido',
    },
    anulado: {
      className: 'badge text-bg-dark',
      label: 'Anulado',
    },
  };
  const item = map[estado] || {
    className: 'badge text-bg-secondary',
    label: String(estado || '').replace('_', ' '),
  };
  return `<span class="${item.className}">${escapeHtml(item.label)}</span>`;
}

export function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function loadingHtml(msg = 'Cargando…') {
  return `
    <div class="d-flex justify-content-center align-items-center py-5 text-secondary gap-2">
      <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
      <span>${escapeHtml(msg)}</span>
    </div>`;
}

export function emptyHtml(msg, actionHtml = '') {
  return `
    <div class="text-center py-5 px-3">
      <p class="text-secondary mb-3">${escapeHtml(msg)}</p>
      ${actionHtml}
    </div>`;
}

/** Evita doble clic: deshabilita el botón y muestra spinner Bootstrap. */
export function setButtonBusy(btn, busy, label = 'Espera…') {
  if (!btn) return;
  if (busy) {
    if (!btn.dataset.idleHtml) btn.dataset.idleHtml = btn.innerHTML;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>${escapeHtml(label)}`;
    return;
  }
  btn.disabled = false;
  btn.removeAttribute('aria-busy');
  if (btn.dataset.idleHtml) {
    btn.innerHTML = btn.dataset.idleHtml;
    delete btn.dataset.idleHtml;
  }
}

/**
 * Cartón tradicional con corrediza. El jugador tacha a mano (sin pistas de coincidencia).
 * @param {object} numeros
 * @param {Set<number>} tachados
 * @param {{ interactivo?: boolean, cartonId?: number }} opts
 */
export function renderCartonHtml(numeros, tachados = new Set(), opts = {}) {
  const cols = ['B', 'I', 'N', 'G', 'O'];
  const interactivo = !!opts.interactivo;
  const idNum = opts.cartonId != null ? Number(opts.cartonId) : null;
  const cartonId = idNum != null ? ` data-carton-id="${idNum}"` : '';
  // Solo letras B-I-N-G-O (sin banner "BINGO" duplicado).
  let html = `<div class="carton"${cartonId}><div class="carton-grid" role="table" aria-label="Cartón de bingo">`;
  cols.forEach((c) => {
    html += `<div class="head">${c}</div>`;
  });
  for (let row = 0; row < 5; row++) {
    cols.forEach((c) => {
      const col = numeros[c] || [];
      if (c === 'N' && col.length === 4 && row === 2) {
        html += `
          <div class="cell free tachado" title="Espacio libre">
            <span class="cell-num cell-free-label">FREE</span>
            <span class="cell-shutter" aria-hidden="true"></span>
          </div>`;
        return;
      }
      let idx = row;
      if (c === 'N' && col.length === 4 && row > 2) idx = row - 1;
      const n = col[idx];
      if (n == null) {
        html += '<div class="cell"></div>';
        return;
      }
      const tachado = tachados.has(Number(n));
      const cls = [
        'cell',
        tachado ? 'tachado' : '',
        interactivo ? 'cell-tap' : '',
      ]
        .filter(Boolean)
        .join(' ');
      const attrs = interactivo
        ? ` role="button" tabindex="0" data-n="${n}" aria-pressed="${tachado ? 'true' : 'false'}"`
        : ` data-n="${n}"`;
      html += `
        <div class="${cls}"${attrs}>
          <span class="cell-num">${n}</span>
          <span class="cell-shutter" aria-hidden="true"></span>
        </div>`;
    });
  }
  html += '</div></div>';
  return html;
}

/** Columnas densas en vivo: más cartones visibles, menos aire vacío. */
export function cartonColClass(total) {
  if (total <= 1) return 'col-10 col-sm-7 col-md-5 col-lg-6 mx-auto';
  if (total === 2) return 'col-6';
  if (total === 3) return 'col-4';
  if (total <= 5) return 'col-6 col-md-4';
  return 'col-6 col-md-4 col-lg-3';
}

/** Activa tab. Dock = Bingos | Cartones | Carrito | Cuenta (pill salta el carrito). */
export function setNavActive(route) {
  const pillIndex = {
    bingos: 0,
    'mis-cartones': 1,
    cuenta: 3,
  };
  document.querySelectorAll('.nav-app [data-route]').forEach((a) => {
    a.classList.toggle('active', a.getAttribute('data-route') === route);
  });
  moveNavPill(pillIndex[route] ?? 0, 4);
}

/** Pastilla deslizante: solo cambia --nav-i (estable al cambiar de vista). */
export function moveNavPill(index = 0, total = 4) {
  const dock = document.querySelector('.nav-app__dock');
  const pill = document.getElementById('navPill');
  if (!dock || !pill) return;
  const n = Math.max(1, total);
  const i = Math.min(Math.max(0, index), n - 1);
  dock.style.setProperty('--nav-count', String(n));
  dock.style.setProperty('--nav-i', String(i));
  pill.style.opacity = '1';
}

/** Normaliza lista de balotas del API (array o { balotas }). Orden de sorteo. */
export function normalizarBalotas(res) {
  const list = Array.isArray(res) ? res : res?.balotas || [];
  return list
    .slice()
    .sort((a, b) => Number(a.orden || 0) - Number(b.orden || 0));
}

export function setNumerosSorteados(balotas) {
  return new Set(
    balotas
      .map((b) => Number(b.numero))
      .filter((n) => Number.isFinite(n) && n > 0)
  );
}

/** 6 cajas form-control (patrón OTP Bootstrap). */
export function otpInputsHtml(prefix = 'otp') {
  // Sin maxlength=1: el navegador recortaría el pegado a un solo dígito.
  const boxes = [0, 1, 2, 3, 4, 5]
    .map(
      (i) => `
      <input type="text" class="form-control form-control-lg text-center otp-input__box" id="${prefix}Digit${i}" maxlength="6" inputmode="numeric" autocomplete="${i === 0 ? 'one-time-code' : 'off'}" aria-label="Dígito ${i + 1}">`
    )
    .join('');

  return `
    <div class="otp-input d-flex gap-2 justify-content-center mb-3" id="${prefix}Digits" role="group" aria-label="Código de 6 dígitos">
      ${boxes}
    </div>
    <input type="hidden" name="codigo" id="${prefix}Codigo" value="">`;
}

/**
 * Autofocus, avance, backspace y pegado del código completo.
 * @returns {{ getCodigo: () => string, clear: () => void, focus: () => void }}
 */
export function wireOtpInputs(root, { onComplete } = {}) {
  const boxes = Array.from(root.querySelectorAll('.otp-input__box'));
  const hidden = root.querySelector('input[name="codigo"]');

  const sync = () => {
    const codigo = boxes.map((b) => String(b.value || '').slice(0, 1)).join('');
    if (hidden) hidden.value = codigo;
    if (codigo.length === 6 && /^[0-9]{6}$/.test(codigo)) onComplete?.(codigo);
    return codigo;
  };

  const fillFrom = (digits, start = 0) => {
    const clean = String(digits || '').replace(/\D/g, '').slice(0, boxes.length - start);
    for (let i = start; i < boxes.length; i++) {
      boxes[i].value = clean[i - start] || '';
    }
    const next = Math.min(start + Math.max(clean.length, 1) - 1, boxes.length - 1);
    boxes[next]?.focus();
    boxes[next]?.select?.();
    sync();
  };

  boxes.forEach((box, i) => {
    box.addEventListener('beforeinput', (ev) => {
      if (ev.inputType === 'insertFromPaste') {
        const text = ev.data || '';
        if (text.replace(/\D/g, '').length > 1) {
          ev.preventDefault();
          fillFrom(text, 0);
        }
      }
    });

    box.addEventListener('input', () => {
      const raw = String(box.value || '').replace(/\D/g, '');
      if (raw.length > 1) {
        fillFrom(raw, 0);
        return;
      }
      box.value = raw.slice(0, 1);
      if (box.value && i < boxes.length - 1) {
        boxes[i + 1].focus();
        boxes[i + 1].select?.();
      }
      sync();
    });

    box.addEventListener('keydown', (ev) => {
      if (ev.key === 'Backspace' && !box.value && i > 0) {
        ev.preventDefault();
        boxes[i - 1].focus();
        boxes[i - 1].value = '';
        sync();
      }
    });

    box.addEventListener('paste', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      const text =
        ev.clipboardData?.getData('text') ||
        ev.clipboardData?.getData('text/plain') ||
        '';
      fillFrom(text, 0);
    });

    box.addEventListener('focus', () => box.select());
  });

  return {
    getCodigo: () => sync(),
    clear: () => {
      boxes.forEach((b) => {
        b.value = '';
      });
      if (hidden) hidden.value = '';
    },
    focus: () => {
      boxes[0]?.focus();
      boxes[0]?.select?.();
    },
  };
}
