/**
 * Tablero maestro B-I-N-G-O (1–75) compacto para mesa en vivo.
 * Filas horizontales (letra + 15 números) para no comerse la pantalla.
 */
import { colorClase } from './balota.js';
import { setNumerosSorteados } from './ui.js';

const COLUMNAS = [
  { letra: 'B', min: 1, max: 15 },
  { letra: 'I', min: 16, max: 30 },
  { letra: 'N', min: 31, max: 45 },
  { letra: 'G', min: 46, max: 60 },
  { letra: 'O', min: 61, max: 75 },
];

/**
 * @param {Array} balotas lista normalizada
 * @param {{ lastNumero?: number|null, id?: string }} [opts]
 */
export function renderTablero75Html(balotas, opts = {}) {
  const llamados = setNumerosSorteados(balotas || []);
  const last = opts.lastNumero != null
    ? Number(opts.lastNumero)
    : balotas?.length
      ? Number(balotas[balotas.length - 1].numero)
      : null;
  const id = opts.id || 'tablero75';

  const rows = COLUMNAS.map((col) => {
    const cells = [];
    for (let n = col.min; n <= col.max; n++) {
      const called = llamados.has(n);
      const isLast = last === n;
      const cls = [
        'tablero75__cell',
        `tablero75__cell--${colorClase(col.letra)}`,
        called ? 'is-called' : '',
        isLast ? 'is-last' : '',
      ]
        .filter(Boolean)
        .join(' ');
      cells.push(
        `<span class="${cls}" data-numero="${n}" aria-label="${col.letra}${n}${called ? ' sacada' : ''}">${n}</span>`
      );
    }
    return `
      <div class="tablero75__row" data-letra="${col.letra}">
        <div class="tablero75__head tablero75__head--${colorClase(col.letra)}" aria-hidden="true">${col.letra}</div>
        <div class="tablero75__nums">${cells.join('')}</div>
      </div>`;
  }).join('');

  const total = llamados.size;
  return `
    <div class="tablero75" id="${id}" role="table" aria-label="Tablero de 75 balotas, ${total} sacadas">
      <div class="tablero75__meta">
        <span class="fw-semibold small">Tablero</span>
        <span class="small text-secondary">${total}/75</span>
      </div>
      <div class="tablero75__board">${rows}</div>
    </div>`;
}

export { COLUMNAS };
