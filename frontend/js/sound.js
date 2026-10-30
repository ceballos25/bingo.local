/**
 * Audio del juego (Web Audio).
 * Unlock con gesto del usuario (unlockAudio).
 */

const MUTE_KEY = 'bingo_audio_muted';

let ctx = null;
let unlocked = false;
let noiseBuf = null;

export function isMuted() {
  return localStorage.getItem(MUTE_KEY) === '1';
}

export function setMuted(muted) {
  localStorage.setItem(MUTE_KEY, muted ? '1' : '0');
}

export function toggleMuted() {
  setMuted(!isMuted());
  return isMuted();
}

export async function unlockAudio() {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return false;
    if (!ctx) ctx = new AudioCtx();
    if (ctx.state === 'suspended') await ctx.resume();
    if (!noiseBuf) noiseBuf = buildNoiseBuffer(ctx);
    unlocked = ctx.state === 'running';
    return unlocked;
  } catch (e) {
    return false;
  }
}

function buildNoiseBuffer(audioCtx) {
  const len = Math.floor(audioCtx.sampleRate * 0.5);
  const buf = audioCtx.createBuffer(1, len, audioCtx.sampleRate);
  const data = buf.getChannelData(0);
  for (let i = 0; i < len; i++) {
    data[i] = Math.random() * 2 - 1;
  }
  return buf;
}

function ensureCtx() {
  if (isMuted() || !ctx || !unlocked) return null;
  if (ctx.state === 'suspended') ctx.resume();
  if (!noiseBuf) noiseBuf = buildNoiseBuffer(ctx);
  return ctx;
}

function tone(freq, duration, type = 'sine', vol = 0.2, when = 0) {
  const c = ensureCtx();
  if (!c) return;
  try {
    const t0 = c.currentTime + when;
    const osc = c.createOscillator();
    const gain = c.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t0);
    gain.gain.setValueAtTime(0.0001, t0);
    gain.gain.exponentialRampToValueAtTime(Math.max(vol, 0.0002), t0 + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);
    osc.connect(gain);
    gain.connect(c.destination);
    osc.start(t0);
    osc.stop(t0 + duration + 0.03);
  } catch (e) {
    /* ignore */
  }
}

/**
 * Click plástico/metálico de balota contra el bombo (clac / raca).
 */
function cageClac(when = 0, intensity = 1) {
  const c = ensureCtx();
  if (!c || !noiseBuf) return;
  try {
    const t0 = c.currentTime + when;
    const src = c.createBufferSource();
    src.buffer = noiseBuf;
    const bp = c.createBiquadFilter();
    bp.type = 'bandpass';
    bp.frequency.setValueAtTime(1800 + Math.random() * 2200, t0);
    bp.Q.setValueAtTime(6 + Math.random() * 8, t0);
    const hp = c.createBiquadFilter();
    hp.type = 'highpass';
    hp.frequency.setValueAtTime(700, t0);
    const gain = c.createGain();
    const vol = 0.14 * intensity;
    gain.gain.setValueAtTime(0.0001, t0);
    gain.gain.exponentialRampToValueAtTime(vol, t0 + 0.004);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.045 + Math.random() * 0.03);
    src.connect(bp);
    bp.connect(hp);
    hp.connect(gain);
    gain.connect(c.destination);
    src.start(t0);
    src.stop(t0 + 0.09);

    // Tintineo breve (cascabeleo)
    const ping = 2400 + Math.random() * 1800;
    tone(ping, 0.035, 'triangle', 0.045 * intensity, when);
    tone(ping * 0.55, 0.025, 'square', 0.02 * intensity, when + 0.008);
  } catch (e) {
    /* ignore */
  }
}

/**
 * Bombo girando: clac-clac-clac / raca-raca acelerando (bingo cage tumbler).
 */
export function sonarMezcla(ms = 1500) {
  const c = ensureCtx();
  if (!c) return;

  const total = ms / 1000;
  let t = 0;
  let gap = 0.095;
  let n = 0;

  while (t < total - 0.05) {
    const intensity = 0.65 + Math.random() * 0.45;
    // Cada 3–4 clacs un “doble” más denso (chicharrón)
    if (n % 4 === 0) {
      cageClac(t, intensity);
      cageClac(t + 0.018, intensity * 0.7);
    } else {
      cageClac(t, intensity);
    }
    t += gap + Math.random() * 0.025;
    gap = Math.max(0.038, gap * 0.94);
    n += 1;
  }
}

/**
 * Ding de balota (campana tipo bingo hall).
 */
export function sonarBalota() {
  if (!unlocked) return;
  tone(1480, 0.08, 'square', 0.09, 0);
  tone(1046.5, 0.42, 'sine', 0.32, 0.02);
  tone(1568, 0.35, 'sine', 0.14, 0.04);
  tone(2093, 0.28, 'triangle', 0.1, 0.06);
  tone(3136, 0.18, 'sine', 0.05, 0.08);
  tone(160, 0.14, 'triangle', 0.12, 0);
}

/**
 * Corrediza: swish de lámina + tope suave.
 */
export function sonarCorrediza() {
  const c = ensureCtx();
  if (!c || !noiseBuf) return;
  try {
    const t0 = c.currentTime;
    const src = c.createBufferSource();
    src.buffer = noiseBuf;
    const bp = c.createBiquadFilter();
    bp.type = 'bandpass';
    bp.Q.setValueAtTime(1.2, t0);
    bp.frequency.setValueAtTime(2400, t0);
    bp.frequency.exponentialRampToValueAtTime(380, t0 + 0.16);
    const gain = c.createGain();
    gain.gain.setValueAtTime(0.0001, t0);
    gain.gain.exponentialRampToValueAtTime(0.11, t0 + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.18);
    src.connect(bp);
    bp.connect(gain);
    gain.connect(c.destination);
    src.start(t0);
    src.stop(t0 + 0.2);

    // Tope de la corrediza
    tone(190, 0.05, 'triangle', 0.07, 0.14);
    tone(110, 0.06, 'sine', 0.05, 0.155);
  } catch (e) {
    /* ignore */
  }
}

/** Bingo cantado → misma victoria arcade. */
export function sonarBingo() {
  sonarCantar();
}

/**
 * Error al cantar / acción inválida: buzzer corto (máquina “no”).
 */
export function sonarError() {
  if (!unlocked) return;
  tone(220, 0.14, 'square', 0.14, 0);
  tone(165, 0.18, 'square', 0.16, 0.12);
  tone(110, 0.22, 'sawtooth', 0.1, 0.22);
  cageClac(0.02, 0.55);
}

/** Moneda arcade clásica (blip-blip corto y brillante). */
function coin(when = 0, vol = 0.14) {
  tone(988, 0.05, 'square', vol, when);
  tone(1319, 0.09, 'square', vol * 0.95, when + 0.045);
}

/** Evita doble sonido (quien cantó + SSE al resto de hosts). */
let ultimaCantadaSonadaId = null;

/**
 * Victoria arcade al cantar: monedas → lluvia de puntos → 1-UP.
 * Siempre intenta unlock antes de tocar.
 * @param {{ reclamoId?: number|string }} [opts]
 */
export async function sonarCantar(opts = {}) {
  await unlockAudio();

  const reclamoId = Number(opts.reclamoId) || 0;
  if (reclamoId > 0) {
    if (ultimaCantadaSonadaId === reclamoId) return;
    ultimaCantadaSonadaId = reclamoId;
  }

  if (isMuted() || !unlocked) return;

  // 1) Monedas que van cayendo (¡sííí!)
  for (let i = 0; i < 7; i++) {
    coin(i * 0.1, 0.13 - i * 0.004);
  }

  // 2) Contador loco de puntos (más rápido, más agudo)
  let t = 0.72;
  for (let i = 0; i < 18; i++) {
    tone(1175 + i * 70, 0.045, 'square', 0.1, t);
    t += 0.028;
  }

  // 3) Fanfarrita 1-UP (la que te pone la piel de gallina)
  const u = t + 0.04;
  const up = [
    [659.25, 0],
    [783.99, 0.09],
    [1318.5, 0.18],
    [1046.5, 0.27],
    [1174.7, 0.36],
    [1568.0, 0.45],
  ];
  up.forEach(([f, d]) => {
    tone(f, 0.14, 'square', 0.18, u + d);
    tone(f * 2, 0.1, 'triangle', 0.08, u + d + 0.02);
  });

  // 4) Golpe final brillante
  const end = u + 0.62;
  tone(1568, 0.2, 'square', 0.14, end);
  tone(2093, 0.35, 'triangle', 0.16, end + 0.05);
  tone(2637, 0.4, 'sine', 0.1, end + 0.1);
  coin(end + 0.15, 0.12);
  coin(end + 0.26, 0.1);
}
