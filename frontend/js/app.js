import { CONFIG } from './config.js';
import { registrarServiceWorker } from './push.js';
import {
  getUsuario,
  isLoggedIn,
  setSession,
  setUsuario,
  esMesa,
  getToken,
} from './auth.js';
import { setOrganizacionId } from './org-context.js';
import {
  setNavActive,
  inicialesUsuario,
  colorAvatarUsuario,
  toast,
} from './ui.js';
import {
  hideBootPreloader,
  showRoutePreloader,
  hideRoutePreloader,
} from './preloader.js';
import { initCartShell, updateCartBadge } from './cart.js';
import { api } from './api.js';
import { renderLanding } from './pages/landing.js';
import { renderBingos } from './pages/bingos.js';
import { renderComprar } from './pages/comprar.js';
import { renderMisCartones } from './pages/mis-cartones.js';
import { renderCuenta } from './pages/cuenta.js';
import { renderVivo, cleanupVivo } from './pages/vivo.js';
import { syncAppDock, setMesaNavActive } from './pages/mesa/shell.js';
import { renderMesaDashboard } from './pages/mesa/dashboard.js';
import { renderMesaPartidas } from './pages/mesa/partidas.js';
import { renderMesaPartidaDetalle } from './pages/mesa/partida-detalle.js';
import { renderMesaVivo, cleanupMesaVivo } from './pages/mesa/vivo.js';
import { renderMesaPagos } from './pages/mesa/pagos.js';
import { renderMesaClientes } from './pages/mesa/clientes.js';
import { renderMesaOrganizacion } from './pages/mesa/organizacion.js';

initCartShell();
setOrganizacionId(getUsuario()?.organizacionId);

const view = document.getElementById('view');
const subEl = document.getElementById('headerSub');
const avatarEl = document.getElementById('headerAvatar');
let bootDone = false;
let yoRefrescado = false;

function wireBrandAndSupport() {
  document.title = CONFIG.brandName || 'Bingo';

  const waBase = CONFIG.support?.whatsapp
    ? `https://wa.me/${CONFIG.support.whatsapp}?text=${encodeURIComponent('Hola, necesito ayuda con mi bingo / cartón.')}`
    : '#';

  const waAyuda = document.getElementById('btnWhatsappAyuda');
  if (waAyuda) waAyuda.setAttribute('href', waBase);

  const hours = document.getElementById('footerHours');
  if (hours && CONFIG.support?.hours) {
    hours.textContent = `Soporte humano · ${CONFIG.support.hours}`;
  }

  const yearEl = document.getElementById('footerYear');
  if (yearEl) yearEl.textContent = String(new Date().getFullYear());

  const verEl = document.getElementById('footerVersion');
  if (verEl && CONFIG.appVersion) {
    verEl.textContent = String(CONFIG.appVersion).replace(/^v/i, '');
  }
}

wireBrandAndSupport();

/**
 * #/landing → name landing
 * #/mesa/partidas → name mesa, section partidas
 * #/mesa/partidas/3 → name mesa, section partidas, id 3
 * #/mesa/vivo/3 → name mesa, section vivo, id 3
 */
function parseRoute() {
  const raw = (location.hash || '#/landing').replace(/^#/, '');
  const qAt = raw.indexOf('?');
  const pathPart = qAt >= 0 ? raw.slice(0, qAt) : raw;
  const queryPart = qAt >= 0 ? raw.slice(qAt + 1) : '';
  const parts = pathPart.split('/').filter(Boolean);
  const query = {};
  if (queryPart) {
    new URLSearchParams(queryPart).forEach((v, k) => {
      query[k] = v;
    });
  }

  if (parts[0] === 'mesa') {
    return {
      name: 'mesa',
      section: parts[1] || 'inicio',
      id: parts[2] ? Number(parts[2]) : null,
      query,
    };
  }

  return {
    name: parts[0] || 'landing',
    section: null,
    id: parts[1] ? Number(parts[1]) : null,
    query,
  };
}

async function refreshYoOnce() {
  if (yoRefrescado || !getToken()) return;
  yoRefrescado = true;
  try {
    const yo = await api('/auth/yo');
    setUsuario(yo);
  } catch (_) {
    /* token inválido: api.js limpia en 401 */
  }
}

function paintHeaderUser() {
  const u = getUsuario();
  const brand = document.querySelector('.navbar-brand');
  if (brand) {
    brand.setAttribute('href', esMesa() ? '#/mesa' : '#/landing');
  }
  if (!subEl || !avatarEl) return;
  if (isLoggedIn() && u) {
    const nombre = u.nombre || (esMesa() ? 'Mesa' : 'Jugador');
    subEl.textContent = nombre;
    avatarEl.textContent = inicialesUsuario(u);
    avatarEl.style.backgroundColor = colorAvatarUsuario(u);
    avatarEl.classList.remove('user-avatar--guest');
  } else {
    subEl.textContent = 'Invitado';
    avatarEl.textContent = '?';
    avatarEl.style.backgroundColor = '';
    avatarEl.classList.add('user-avatar--guest');
  }
}

async function route() {
  cleanupVivo();
  cleanupMesaVivo();
  await refreshYoOnce();
  syncAppDock();

  const { name, section, id, query } = parseRoute();

  if (bootDone) {
    const rp = document.getElementById('routePreloader');
    if (rp) rp.removeAttribute('aria-hidden');
    showRoutePreloader();
  }

  try {
    const scroller = document.getElementById('appScroll');
    if (scroller) scroller.scrollTop = 0;

    paintHeaderUser();
    updateCartBadge();

    // Nav activa
    if (esMesa()) {
      const mesaNav = {
        partidas: 'mesa-partidas',
        vivo: 'mesa-vivo',
        pagos: 'mesa-pagos',
      };
      if (name === 'cuenta') {
        setMesaNavActive('cuenta');
      } else if (name === 'mesa') {
        setMesaNavActive(mesaNav[section] || 'mesa-inicio');
      } else {
        setMesaNavActive('mesa-inicio');
      }
    } else {
      const navMap = {
        landing: 'bingos',
        bingos: 'bingos',
        comprar: 'bingos',
        'mis-cartones': 'mis-cartones',
        vivo: 'mis-cartones',
        cuenta: 'cuenta',
      };
      setNavActive(navMap[name] || 'bingos');
    }

    // Enlace único de invitado / confirmación de compra (token en correo o WhatsApp)
    if (name === 'acceso') {
      const token = String(query.token || '').trim();
      const nextRaw = String(query.next || '').trim();
      const next =
        nextRaw.startsWith('#/') && !nextRaw.startsWith('#/acceso')
          ? nextRaw
          : '';
      if (!token) {
        toast('Enlace incompleto. Revisa el correo o WhatsApp.', 'warning');
        location.replace('#/landing');
        return;
      }
      try {
        setSession(token, { nombre: '…' });
        const yo = await api('/auth/yo');
        setSession(token, yo);
        toast('Acceso listo. Bienvenido.', 'success');
        if (esMesa()) {
          location.replace('#/mesa');
        } else {
          location.replace(next || '#/mis-cartones');
        }
      } catch (e) {
        toast(e.message || 'Enlace inválido o vencido', 'danger');
        location.replace('#/cuenta');
      }
      return;
    }

    // Guards mesa ↔ jugador
    if (name === 'mesa') {
      if (!isLoggedIn()) {
        location.hash = '#/cuenta';
        return;
      }
      if (!esMesa()) {
        location.replace('#/landing');
        return;
      }
    }

    if (esMesa() && ['landing', 'inicio', 'bingos', 'comprar', 'mis-cartones', 'vivo'].includes(name)) {
      location.replace('#/mesa');
      return;
    }

    // Comprar es público (vitrina): elegir cartones sin cuenta.
    // Datos / login solo al pagar (ensureGuestSession en el carrito).
    if (!isLoggedIn() && ['vivo', 'mis-cartones'].includes(name)) {
      location.hash = '#/cuenta';
      return;
    }

    const mainEl = document.getElementById('appMain');
    const isLanding = name === 'landing' || name === 'inicio' || name === 'bingos';
    if (mainEl && !isLanding) {
      mainEl.classList.remove('app-main--bleed');
      mainEl.classList.add('container-lg', 'py-3', 'py-md-4');
    }

    // Rutas mesa
    if (name === 'mesa') {
      if (section === 'pagos') {
        await renderMesaPagos(view);
        return;
      }
      if (section === 'clientes') {
        await renderMesaClientes(view);
        return;
      }
      if (section === 'organizacion' || section === 'organizaciones') {
        await renderMesaOrganizacion(view);
        return;
      }
      if (section === 'vivo' && id) {
        await renderMesaVivo(view, id);
        return;
      }
      if (section === 'partidas' && id) {
        await renderMesaPartidaDetalle(view, id);
        return;
      }
      if (section === 'partidas') {
        await renderMesaPartidas(view);
        return;
      }
      await renderMesaDashboard(view);
      return;
    }

    if (name === 'comprar' && id) {
      await renderComprar(view, id);
      return;
    }
    if (name === 'vivo' && id) {
      await renderVivo(view, id);
      return;
    }
    if (name === 'mis-cartones') {
      await renderMisCartones(view);
      return;
    }
    if (name === 'cuenta') {
      await renderCuenta(view);
      return;
    }
    if (name === 'bingos') {
      location.replace('#/landing');
      return;
    }
    if (name === 'landing' || name === 'inicio') {
      await renderLanding(view);
      return;
    }
    await renderBingos(view);
  } finally {
    if (!bootDone) {
      bootDone = true;
      if (window.__bootLetterTimer) {
        clearInterval(window.__bootLetterTimer);
        window.__bootLetterTimer = null;
      }
      window.setTimeout(() => hideBootPreloader(), 450);
    }
    hideRoutePreloader();
  }
}

window.addEventListener('hashchange', () => {
  route().catch(console.error);
});

registrarServiceWorker().catch(() => {});
route().catch(console.error);
