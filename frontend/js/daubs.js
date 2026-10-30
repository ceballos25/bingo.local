/** Tachado manual del jugador (local). El backend valida el bingo con balotas reales. */

function key(partidaId, cartonId) {
  return `bingo_daubs_${partidaId}_${cartonId}`;
}

export function loadDaubs(partidaId, cartonId) {
  try {
    const raw = localStorage.getItem(key(partidaId, cartonId));
    if (!raw) return new Set();
    const arr = JSON.parse(raw);
    return new Set((arr || []).map(Number).filter((n) => n > 0));
  } catch (e) {
    return new Set();
  }
}

export function saveDaubs(partidaId, cartonId, set) {
  localStorage.setItem(
    key(partidaId, cartonId),
    JSON.stringify([...set])
  );
}

export function toggleDaub(partidaId, cartonId, numero) {
  const set = loadDaubs(partidaId, cartonId);
  const n = Number(numero);
  if (set.has(n)) set.delete(n);
  else set.add(n);
  saveDaubs(partidaId, cartonId, set);
  return set;
}
