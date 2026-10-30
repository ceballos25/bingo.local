/**
 * Carrito en el menú inferior + Offcanvas Bootstrap (abajo → arriba).
 */
import { api } from './api.js';
import { isLoggedIn } from './auth.js';
import { CONFIG } from './config.js';
import { ensureGuestSession } from './guest-auth.js';
import { getOrganizacionId } from './org-context.js';
import {
  escapeHtml,
  formatMoney,
  toast,
  setButtonBusy,
  premiosListHtml,
} from './ui.js';

const PARTIDA_KEY = 'bingo_cart_partida';
const IDS_PREFIX = 'bingo_cart_';

let context = null; // { partidaId, precio, nombre, modalidades, gratis, onChange }

function premiosCartHtml(modalidades) {
  return premiosListHtml(modalidades, {
    ulClass: 'list-unstyled mb-0 small',
    liClass: 'py-1',
  });
}
let offcanvas = null;
/** Cuentas activas desde API (o fallback CONFIG). */
let cuentasTransferencia = null;

const MARK_CLASS = {
  nequi: 'text-bg-dark',
  bancolombia: 'text-bg-warning',
  breb: 'text-bg-primary',
  otro: 'text-bg-secondary',
};

function idsKey(partidaId) {
  return IDS_PREFIX + partidaId;
}

function getOffcanvas() {
  const el = document.getElementById('carritoOffcanvas');
  if (!el || typeof bootstrap === 'undefined') return null;
  if (!offcanvas) {
    offcanvas = bootstrap.Offcanvas.getOrCreateInstance(el);
  }
  return offcanvas;
}

export function getActivePartidaId() {
  const n = Number(sessionStorage.getItem(PARTIDA_KEY) || 0);
  return n > 0 ? n : null;
}

export function getCartIds(partidaId) {
  if (!partidaId) return [];
  try {
    const raw = sessionStorage.getItem(idsKey(partidaId));
    const ids = raw ? JSON.parse(raw) : [];
    return (Array.isArray(ids) ? ids : []).map(Number).filter((n) => n > 0);
  } catch {
    return [];
  }
}

function saveIds(partidaId, ids) {
  sessionStorage.setItem(PARTIDA_KEY, String(partidaId));
  sessionStorage.setItem(idsKey(partidaId), JSON.stringify([...ids]));
}

export function cartCount(partidaId = getActivePartidaId()) {
  return getCartIds(partidaId).length;
}

export function setCartContext(ctx) {
  context = ctx;
  updateCartBadge();
  renderPanelBody();
}

export function clearCartContext() {
  context = null;
}

export function replaceCartIds(partidaId, ids, meta = {}) {
  const clean = [...new Set((ids || []).map(Number).filter((n) => n > 0))];
  saveIds(partidaId, clean);
  if (
    meta.nombre != null ||
    meta.precio != null ||
    meta.modalidades != null ||
    meta.gratis != null ||
    meta.onChange
  ) {
    context = {
      partidaId: Number(partidaId),
      precio: Number(meta.precio) || context?.precio || 0,
      nombre: meta.nombre || context?.nombre || '',
      modalidades:
        meta.modalidades != null
          ? meta.modalidades
          : context?.modalidades || [],
      gratis:
        meta.gratis != null
          ? !!meta.gratis
          : !!context?.gratis || Number(meta.precio) <= 0,
      onChange: meta.onChange || context?.onChange || null,
    };
  }
  updateCartBadge();
  renderPanelBody();
  return clean;
}

export function addCartItem(partidaId, cartonId, meta = {}) {
  const ids = new Set(getCartIds(partidaId));
  ids.add(Number(cartonId));
  return replaceCartIds(partidaId, [...ids], meta);
}

export function removeCartItem(partidaId, cartonId) {
  const ids = new Set(getCartIds(partidaId));
  ids.delete(Number(cartonId));
  saveIds(partidaId, ids);
  updateCartBadge();
  notifyChange();
  renderPanelBody();
  return [...ids];
}

export function clearCart(partidaId = getActivePartidaId()) {
  if (!partidaId) return;
  saveIds(partidaId, []);
  updateCartBadge();
  notifyChange();
  renderPanelBody();
}

function notifyChange() {
  if (typeof context?.onChange === 'function') {
    context.onChange(getCartIds(context.partidaId));
  }
}

export function updateCartBadge() {
  const n = cartCount();
  const badge = document.getElementById('navCartBadge');
  if (!badge) return;
  badge.textContent = String(n);
  badge.hidden = n === 0;
}

/** Pulso en el ícono Carrito del menú (móvil y desktop). */
export function bumpCartNav() {
  const btn = document.getElementById('navCartBtn');
  const badge = document.getElementById('navCartBadge');
  if (!btn) return;
  btn.classList.remove('is-bump');
  badge?.classList.remove('is-bump');
  void btn.offsetWidth;
  btn.classList.add('is-bump');
  badge?.classList.add('is-bump');
  window.setTimeout(() => {
    btn.classList.remove('is-bump');
    badge?.classList.remove('is-bump');
  }, 450);
}

/**
 * Efecto tipo “vuelo a la bolsa” (Rappi): del botón Agregar → Carrito del dock.
 * En reduced-motion solo hace bump.
 */
export function flyToCart(fromEl) {
  const target = document.getElementById('navCartBtn');
  updateCartBadge();

  if (!fromEl || !target) {
    bumpCartNav();
    return;
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    bumpCartNav();
    return;
  }

  const start = fromEl.getBoundingClientRect();
  const end = target.getBoundingClientRect();
  const ghost = document.createElement('div');
  ghost.className = 'cart-fly';
  ghost.setAttribute('aria-hidden', 'true');
  ghost.innerHTML = '<i class="bi bi-ticket-perforated"></i>';

  const x0 = start.left + start.width / 2;
  const y0 = start.top + start.height / 2;
  const x1 = end.left + end.width / 2;
  const y1 = end.top + end.height / 2;

  ghost.style.transform = `translate(${x0}px, ${y0}px) scale(1)`;
  document.body.appendChild(ghost);
  void ghost.offsetWidth;
  ghost.style.transform = `translate(${x1}px, ${y1}px) scale(0.35)`;
  ghost.classList.add('is-flying');

  const finish = () => {
    ghost.remove();
    bumpCartNav();
  };

  ghost.addEventListener('transitionend', finish, { once: true });
  window.setTimeout(finish, 700);
}

export function openCartPanel() {
  renderPanelBody();
  getOffcanvas()?.show();
}

export function closeCartPanel() {
  getOffcanvas()?.hide();
}

function renderPanelBody() {
  const itemsEl = document.getElementById('cartItems');
  const footEl = document.getElementById('cartCheckout');
  const subEl = document.getElementById('carritoSub');
  const totalEl = document.getElementById('cartTotal');
  if (!itemsEl || !footEl) return;

  const partidaId = context?.partidaId || getActivePartidaId();
  const ids = getCartIds(partidaId);
  const precio = Number(context?.precio) || 0;
  const nombre = context?.nombre || '';
  const modalidades = context?.modalidades || [];
  const gratis = !!context?.gratis || precio <= 0;
  const n = ids.length;
  const total = n * precio;

  if (subEl) {
    subEl.textContent = n === 0 ? 'Vacío' : n === 1 ? '1 cartón' : `${n} cartones`;
  }

  if (n === 0) {
    itemsEl.innerHTML = `
      <div class="text-center text-secondary py-5 px-3">
        <i class="bi bi-cart3 fs-4xl d-block mb-2 opacity-50" aria-hidden="true"></i>
        <p class="mb-1 fw-semibold text-body">Tu carrito está vacío</p>
        <p class="small mb-3">Agrega cartones desde una partida en Bingos.</p>
        <button type="button" class="btn btn-success" data-cart-go="#/landing">Ver bingos</button>
      </div>`;
    footEl.hidden = true;
    return;
  }

  // precio 0 es válido (bingo gratis); solo falta contexto si no hay partidaId
  if (!partidaId) {
    itemsEl.innerHTML = `
      <div class="text-center text-secondary py-5 px-3">
        <p class="mb-3">Tienes ${n} cartón${n === 1 ? '' : 'es'} en el carrito.</p>
        <button type="button" class="btn btn-success" data-cart-go="#/landing">Ver bingos</button>
      </div>`;
    footEl.hidden = true;
    return;
  }

  footEl.hidden = false;
  if (totalEl) totalEl.textContent = formatMoney(total);

  const partidaBlock = nombre
    ? `
    <div class="border rounded-3 p-3 mb-3 bg-body-tertiary">
      <div class="fw-semibold font-brand mb-1">${escapeHtml(nombre)}</div>
      <p class="small text-secondary text-uppercase fw-semibold mb-1 letter-spacing">Premios a ganar</p>
      ${premiosCartHtml(modalidades)}
    </div>`
    : '';

  itemsEl.innerHTML = `
    ${partidaBlock}
    <ul class="list-group list-group-flush">
      ${ids
        .map(
          (id) => `
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2 px-0">
          <div class="min-w-0">
            <div class="fw-semibold">Cartón #${id}</div>
          </div>
          <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <span class="fw-semibold">${gratis ? 'Gratis' : formatMoney(precio)}</span>
            <button type="button" class="btn-sm btn btn-outline-danger btn-remove-cart" data-id="${id}" aria-label="Quitar #${id}">
              <i class="bi bi-trash3" aria-hidden="true"></i>
            </button>
          </div>
        </li>`
        )
        .join('')}
    </ul>`;

  const btnPse = document.getElementById('btnPse');
  const btnComp = document.getElementById('btnComp');
  const btnGratis = document.getElementById('btnGratis');
  const btnEnviar = document.getElementById('btnEnviarComp');
  const datosEl = document.getElementById('datosTransferencia');
  const panelComp = document.getElementById('panelComp');

  // Si volvió del banco (atrás / bfcache), el PSE no debe quedar trabado
  resetCheckoutButtons();

  if (btnPse) {
    btnPse.hidden = gratis;
    btnPse.innerHTML = `<i class="bi bi-bank me-1" aria-hidden="true"></i>PSE · ${formatMoney(total)}`;
  }
  if (btnComp) {
    btnComp.hidden = gratis;
    btnComp.innerHTML = `<i class="bi bi-image me-1" aria-hidden="true"></i>Transferencia · ${formatMoney(total)}`;
  }
  if (btnGratis) {
    btnGratis.hidden = !gratis;
    btnGratis.innerHTML = `<i class="bi bi-gift me-1" aria-hidden="true"></i>Obtener gratis`;
  }
  if (panelComp && gratis) {
    panelComp.classList.remove('show');
  }
  if (btnEnviar) {
    btnEnviar.innerHTML =
      '<i class="bi bi-send me-1" aria-hidden="true"></i>Enviar comprobante';
  }
  if (datosEl) {
    datosEl.innerHTML = gratis ? '' : transferenciaHtml(total);
    if (!gratis) wireTransferenciaUi(datosEl);
  }

  itemsEl.querySelectorAll('.btn-remove-cart').forEach((btn) => {
    btn.addEventListener('click', () => {
      removeCartItem(partidaId, Number(btn.getAttribute('data-id')));
    });
  });
}

function cuentasParaCheckout() {
  if (Array.isArray(cuentasTransferencia) && cuentasTransferencia.length) {
    return cuentasTransferencia;
  }
  return CONFIG.transferencia?.cuentas || [];
}

function transferenciaHtml(total) {
  const cuentas = cuentasParaCheckout();

  const rows = cuentas
    .map((c) => {
      const markRaw = c.mark || '';
      const mark =
        markRaw === 'key'
          ? '<i class="bi bi-key-fill" aria-hidden="true"></i>'
          : escapeHtml(markRaw || '?');
      const markClass =
        c.markClass || MARK_CLASS[c.tipo] || 'text-bg-secondary';
      const holder = c.holder
        ? `<div class="small text-secondary text-truncate">
            <i class="bi bi-person-fill me-1" aria-hidden="true"></i>Titular: <strong>${escapeHtml(c.holder)}</strong>
          </div>`
        : '';
      return `
      <button type="button" class="list-group-item list-group-item-action py-2 px-2 transfer-account" data-copy="${escapeHtml(c.copy)}" aria-label="Copiar ${escapeHtml(c.bank)}">
        <div class="d-flex align-items-center gap-2">
          <span class="badge ${escapeHtml(markClass)} rounded-circle transfer-account__mark d-inline-flex align-items-center justify-content-center flex-shrink-0">${mark}</span>
          <span class="flex-grow-1 min-w-0 text-start">
            <span class="d-block fw-semibold text-truncate">${escapeHtml(c.bank)}</span>
            <span class="d-block fw-bold font-monospace text-truncate transfer-account__key">${escapeHtml(c.label)}</span>
            ${holder}
          </span>
          <span class="transfer-account__copy flex-shrink-0 text-primary" aria-hidden="true">
            <i class="bi bi-clipboard"></i>
          </span>
        </div>
      </button>`;
    })
    .join('');

  return `
    <p class="small text-secondary mb-2">
      <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>
      Transfiere <strong>${formatMoney(total)}</strong> exactos. Toca una cuenta para copiarla.
    </p>
    <div class="list-group list-group-flush border rounded mb-3">
      ${rows || '<div class="list-group-item small text-secondary">No hay cuentas configuradas.</div>'}
    </div>
    <label class="list-group-item list-group-item-action rounded border d-flex align-items-center gap-2 py-2 mb-0" for="fileComp" role="button">
      <i class="bi bi-cloud-upload fs-5 text-primary flex-shrink-0" aria-hidden="true"></i>
      <span class="flex-grow-1 min-w-0 text-start">
        <span class="d-block small fw-semibold text-body text-truncate" id="fileCompLabel">Subir comprobante</span>
        <span class="small text-secondary text-truncate d-block" id="fileCompHint">JPG, PNG o WebP</span>
      </span>
      <i class="bi bi-chevron-right text-secondary small flex-shrink-0" aria-hidden="true"></i>
    </label>
    <input type="file" class="d-none" id="fileComp" accept="image/jpeg,image/png,image/webp">`;
}

function syncEnviarComprobanteBtn() {
  const btn = document.getElementById('btnEnviarComp');
  const file = document.getElementById('fileComp')?.files?.[0];
  if (!btn) return;
  if (btn.getAttribute('aria-busy') === 'true') return;
  btn.disabled = !file;
}

function wireTransferenciaUi(root) {
  root.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const text = btn.getAttribute('data-copy') || '';
      const iconWrap = btn.querySelector('.transfer-account__copy');
      try {
        await navigator.clipboard.writeText(text);
        toast('Copiado', 'success');
        if (iconWrap) {
          iconWrap.innerHTML = '<i class="bi bi-check2-circle"></i>';
          window.setTimeout(() => {
            iconWrap.innerHTML = '<i class="bi bi-clipboard"></i>';
          }, 1200);
        }
      } catch {
        toast('No se pudo copiar', 'warning');
      }
    });
  });

  const file = root.querySelector('#fileComp');
  const nameEl = root.querySelector('#fileCompLabel');
  const hintEl = root.querySelector('#fileCompHint');
  file?.addEventListener('change', () => {
    const f = file.files?.[0];
    if (f && nameEl && hintEl) {
      nameEl.innerHTML =
        '<i class="bi bi-check2-circle text-success me-1" aria-hidden="true"></i>Comprobante listo';
      hintEl.textContent = f.name;
    } else if (nameEl && hintEl) {
      nameEl.textContent = 'Subir comprobante';
      hintEl.textContent = 'JPG, PNG o WebP';
    }
    syncEnviarComprobanteBtn();
  });

  syncEnviarComprobanteBtn();
}

function resetCheckoutButtons() {
  ['btnPse', 'btnComp', 'btnGratis'].forEach((id) => {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.disabled = false;
    btn.removeAttribute('aria-busy');
    delete btn.dataset.idleHtml;
  });

  const btnEnviar = document.getElementById('btnEnviarComp');
  if (btnEnviar) {
    btnEnviar.removeAttribute('aria-busy');
    delete btnEnviar.dataset.idleHtml;
    btnEnviar.innerHTML =
      '<i class="bi bi-send me-1" aria-hidden="true"></i>Enviar comprobante';
  }
  syncEnviarComprobanteBtn();
}

async function payPse() {
  const partidaId = context?.partidaId || getActivePartidaId();
  const ids = getCartIds(partidaId);
  if (!partidaId || ids.length === 0) return;
  if (context?.gratis || Number(context?.precio) <= 0) {
    return payGratis();
  }

  const btnPse = document.getElementById('btnPse');
  if (btnPse?.disabled || btnPse?.getAttribute('aria-busy') === 'true') return;

  if (!(await ensureGuestSession())) {
    toast('Ingresa tus datos para pagar', 'warning');
    return;
  }

  setButtonBusy(btnPse, true, 'Conectando…');

  try {
    const redirectUrl =
      location.origin + CONFIG.pseRedirectPath + '?partidaId=' + partidaId;
    const res = await api('/ventas/pse', {
      method: 'POST',
      body: { cartonIds: ids, redirectUrl },
    });
    const url = res.pse && res.pse.url;
    if (!url) throw new Error('OpenPay no devolvió URL PSE.');
    // No vaciar carrito aquí: si el usuario vuelve atrás desde el banco, sigue viendo ítems y PSE usable
    location.href = url;
  } catch (e) {
    toast(e.message || 'No se pudo iniciar PSE');
    setButtonBusy(btnPse, false);
  }
}

async function payGratis() {
  const partidaId = context?.partidaId || getActivePartidaId();
  const ids = getCartIds(partidaId);
  if (!partidaId || ids.length === 0) return;

  const btn = document.getElementById('btnGratis');
  if (btn?.disabled || btn?.getAttribute('aria-busy') === 'true') return;

  if (!(await ensureGuestSession())) {
    toast('Ingresa tus datos para continuar', 'warning');
    return;
  }

  setButtonBusy(btn, true, 'Asignando…');

  try {
    await api('/ventas/gratis', {
      method: 'POST',
      body: { cartonIds: ids },
    });
    clearCart(partidaId);
    toast(
      ids.length === 1 ? 'Cartón listo' : `${ids.length} cartones listos`,
      'success'
    );
    closeCartPanel();
    location.hash = '#/mis-cartones';
  } catch (e) {
    toast(e.message || 'No se pudieron asignar los cartones');
    setButtonBusy(btn, false);
  }
}

async function payComprobante() {
  const partidaId = context?.partidaId || getActivePartidaId();
  const ids = getCartIds(partidaId);
  if (!partidaId || ids.length === 0) return;

  if (!(await ensureGuestSession())) {
    toast('Ingresa tus datos para pagar', 'warning');
    return;
  }

  const file = document.getElementById('fileComp')?.files?.[0];
  if (!file) {
    toast('Elige la imagen del comprobante', 'warning');
    const panel = document.getElementById('panelComp');
    if (panel && typeof bootstrap !== 'undefined') {
      bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).show();
    }
    return;
  }

  const btn = document.getElementById('btnEnviarComp');
  if (btn?.disabled) return;
  setButtonBusy(btn, true, 'Enviando…');
  let ok = 0;
  let fail = 0;

  try {
    for (const cartonId of ids) {
      const fd = new FormData();
      fd.append('cartonId', String(cartonId));
      fd.append('comprobante', file);
      try {
        await api('/ventas/comprobante', { method: 'POST', body: fd });
        ok += 1;
        removeCartItem(partidaId, cartonId);
      } catch {
        fail += 1;
      }
    }

    if (ok > 0 && fail === 0) {
      toast(ok === 1 ? 'Comprobante enviado' : `${ok} comprobantes enviados`, 'success');
      closeCartPanel();
      location.hash = '#/mis-cartones';
      return;
    }
    if (ok > 0) {
      toast(`${ok} ok, ${fail} fallaron`, 'warning');
      closeCartPanel();
      location.hash = '#/mis-cartones';
      return;
    }
    toast('No se pudo subir el comprobante');
  } finally {
    setButtonBusy(btn, false);
    syncEnviarComprobanteBtn();
  }
}

async function cargarCuentasTransferencia() {
  const orgId = getOrganizacionId();
  if (!orgId) {
    cuentasTransferencia = null;
    return;
  }
  try {
    const list = await api(
      '/cuentas-pago/publicas?organizacionId=' + encodeURIComponent(orgId)
    );
    if (Array.isArray(list) && list.length) {
      cuentasTransferencia = list.map((c) => ({
        id: c.id,
        tipo: c.tipo,
        mark: c.mark,
        markClass: MARK_CLASS[c.tipo] || 'text-bg-secondary',
        bank: c.bank,
        label: c.label,
        copy: c.copy,
        holder: c.holder || '',
      }));
    }
  } catch {
    // Fallback CONFIG.transferencia
  }
}

function goFromCart(hash) {
  closeCartPanel();
  const target = hash || '#/landing';
  if (location.hash === target) {
    window.dispatchEvent(new HashChangeEvent('hashchange'));
  } else {
    location.hash = target;
  }
}

export function initCartShell() {
  updateCartBadge();
  cargarCuentasTransferencia();
  resetCheckoutButtons();

  const el = document.getElementById('carritoOffcanvas');
  if (el) {
    el.addEventListener('show.bs.offcanvas', () => {
      resetCheckoutButtons();
      cargarCuentasTransferencia().finally(() => renderPanelBody());
    });
    el.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-cart-go]');
      if (!btn) return;
      goFromCart(btn.getAttribute('data-cart-go'));
    });
  }

  // Atrás desde PSE / bfcache: reactivar botones
  window.addEventListener('pageshow', () => {
    resetCheckoutButtons();
    updateCartBadge();
  });

  document.getElementById('btnVaciarConfirm')?.addEventListener('click', () => {
    clearCart(context?.partidaId || getActivePartidaId());
  });
  document.getElementById('btnPse')?.addEventListener('click', () => payPse());
  document.getElementById('btnGratis')?.addEventListener('click', () => payGratis());
  document.getElementById('btnComp')?.addEventListener(
    'click',
    async (e) => {
      if (isLoggedIn()) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      if (!(await ensureGuestSession())) {
        toast('Ingresa tus datos para pagar', 'warning');
        return;
      }
      const panel = document.getElementById('panelComp');
      if (panel && typeof bootstrap !== 'undefined') {
        bootstrap.Collapse.getOrCreateInstance(panel).show();
      }
    },
    true
  );
  document.getElementById('btnEnviarComp')?.addEventListener('click', () => payComprobante());
}
