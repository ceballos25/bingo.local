import { setOrganizacionId } from './org-context.js';

const TOKEN_KEY = 'bingo_token';
const USER_KEY = 'bingo_usuario';
const AFTER_LOGIN_KEY = 'bingo_after_login';

export function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

/** Guarda a dónde ir tras login/registro (ej. #/comprar/1 desde la landing). */
export function setAfterLogin(hash) {
  const h = String(hash || '').trim();
  if (!h || !h.startsWith('#/')) {
    sessionStorage.removeItem(AFTER_LOGIN_KEY);
    return;
  }
  sessionStorage.setItem(AFTER_LOGIN_KEY, h);
}

export function consumeAfterLogin(fallback = '#/landing') {
  const h = sessionStorage.getItem(AFTER_LOGIN_KEY);
  sessionStorage.removeItem(AFTER_LOGIN_KEY);
  if (h && h.startsWith('#/')) return h;
  return fallback;
}

/** Mira el destino post-login sin borrarlo (para UX en #/cuenta). */
export function peekAfterLogin() {
  const h = sessionStorage.getItem(AFTER_LOGIN_KEY);
  return h && h.startsWith('#/') ? h : '';
}

export function getUsuario() {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch (e) {
    return null;
  }
}

export function setSession(token, usuario) {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(USER_KEY, JSON.stringify(usuario));
  setOrganizacionId(usuario?.organizacionId);
}

/** Actualiza solo el usuario en sesión (mismo token). */
export function setUsuario(usuario) {
  localStorage.setItem(USER_KEY, JSON.stringify(usuario));
}

export function clearSession() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

export function isLoggedIn() {
  return !!getToken();
}

export function getRol() {
  const u = getUsuario();
  return String(u?.rol || '').toLowerCase();
}

/** Administrador u operador de la organización. */
export function esMesa() {
  const rol = getRol();
  return rol === 'administrador' || rol === 'operador';
}

export function esAdministrador() {
  return getRol() === 'administrador';
}

export function requireAuth() {
  if (!isLoggedIn()) {
    location.hash = '#/cuenta';
    return false;
  }
  return true;
}

export function requireMesa() {
  if (!requireAuth()) return false;
  if (!esMesa()) {
    location.hash = '#/landing';
    return false;
  }
  return true;
}

/** Destino tras login con contraseña. */
export function homeHashForUser(usuario) {
  const rol = String(usuario?.rol || '').toLowerCase();
  if (rol === 'administrador' || rol === 'operador') {
    return '#/mesa';
  }
  return '#/landing';
}
