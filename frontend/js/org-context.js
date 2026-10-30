/**
 * Contexto de organización en el navegador (comodidad de UI).
 * La verdad de negocio sigue en JWT / partida en el backend.
 */
const KEY = 'bingo_organizacion_id';

export function getOrganizacionId() {
  const n = Number(
    localStorage.getItem(KEY) || sessionStorage.getItem(KEY) || 0
  );
  return n > 0 ? n : null;
}

export function setOrganizacionId(organizacionId) {
  const n = Number(organizacionId);
  if (n > 0) {
    localStorage.setItem(KEY, String(n));
    return n;
  }
  return null;
}

export function clearOrganizacionId() {
  localStorage.removeItem(KEY);
  sessionStorage.removeItem(KEY);
}

/**
 * Org conocida o 0: el backend resuelve la organización activa
 * cuando el jugador no entró por el enlace de una partida.
 */
export function requireOrganizacionId() {
  return getOrganizacionId() || 0;
}
