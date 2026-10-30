/** Render HTML de balota 3D (CSS). No inventa números: solo presenta datos del API. */

export function letraDeNumero(numero) {
  const n = Number(numero);
  if (n >= 1 && n <= 15) return 'B';
  if (n >= 16 && n <= 30) return 'I';
  if (n >= 31 && n <= 45) return 'N';
  if (n >= 46 && n <= 60) return 'G';
  if (n >= 61 && n <= 75) return 'O';
  return '';
}

export function colorClase(letra) {
  const map = { B: 'b', I: 'i', N: 'n', G: 'g', O: 'o' };
  return map[letra] || 'n';
}

export function datosBalota(b) {
  if (!b) return null;
  const numero = Number(b.numero);
  const letra = (b.letra || letraDeNumero(numero) || '').toUpperCase();
  const etiqueta = b.etiqueta || `${letra}${numero}`;
  return { numero, letra, etiqueta, orden: Number(b.orden || 0) };
}

/**
 * @param {object|null} b balota del API
 * @param {'xl'|'lg'|'md'|'sm'} size
 * @param {{ actual?: boolean, anim?: boolean }} opts
 */
export function renderBalotaHtml(b, size = 'md', opts = {}) {
  const d = datosBalota(b);
  if (!d) {
    return `<div class="bball bball--${size} bball--empty" aria-hidden="true"></div>`;
  }
  const cls = [
    'bball',
    `bball--${size}`,
    `bball--${colorClase(d.letra)}`,
    opts.actual ? 'bball--actual' : '',
    opts.anim ? 'bball--pop' : '',
  ]
    .filter(Boolean)
    .join(' ');

  return `
    <div class="${cls}" data-numero="${d.numero}" data-orden="${d.orden}" title="${d.etiqueta}">
      <div class="bball__sphere">
        <div class="bball__face">
          <span class="bball__letter">${d.letra}</span>
          <span class="bball__num">${d.numero}</span>
        </div>
      </div>
    </div>`;
}

/**
 * Historial visual: actual (última) + hasta 4 anteriores.
 * En UI: ÚLTIMA a la izquierda (grande); a la derecha las previas (más vieja → más nueva).
 */
export function sliceHistorial(balotas) {
  const list = balotas || [];
  if (!list.length) {
    return { actual: null, previas: [null, null, null, null] };
  }
  const actual = list[list.length - 1];
  const previas = list.slice(Math.max(0, list.length - 5), list.length - 1);
  while (previas.length < 4) {
    previas.unshift(null);
  }
  return { actual, previas };
}

/**
 * Panel Última + Anteriores (mismo markup jugador / mesa).
 * @param {object[]} balotas
 * @param {{ animActual?: boolean, id?: string, bare?: boolean }} opts
 */
export function renderBalotasPanelHtml(balotas, opts = {}) {
  const list = balotas || [];
  const { actual, previas } = sliceHistorial(list);
  const id = opts.id || 'panelBalotas';
  const rail = `
      <div class="d-flex justify-content-between align-items-center mb-0">
        <span class="fw-semibold small"><i class="bi bi-circle-fill text-warning me-1" aria-hidden="true"></i>Balotas</span>
        <span class="small text-secondary">${list.length}/75</span>
      </div>
      <div class="bball-rail bball-rail--ltr bball-rail--compact">
        <div class="bball-slot bball-slot--now">
          <div class="bball-slot__label">Última</div>
          ${renderBalotaHtml(actual, 'md', { actual: true, anim: !!opts.animActual })}
        </div>
        <div class="bball-slot bball-slot--prev">
          <div class="bball-slot__label">Anteriores</div>
          <div class="d-flex align-items-end gap-1 flex-wrap">
            ${previas.map((b) => renderBalotaHtml(b, 'sm')).join('')}
          </div>
        </div>
      </div>`;

  if (opts.bare) {
    return `<div id="${id}">${rail}</div>`;
  }

  return `
    <div class="border bg-white p-1 px-2 mb-2" id="${id}">
      ${rail}
    </div>`;
}
