/**
 * Al volver a la pestaña / bfcache: rehidratar estado y SSE sin perder balotas.
 */
import { cleanupDrawOverlay } from './draw-overlay.js';

/**
 * @param {{
 *   isActive: () => boolean,
 *   resync: () => Promise<void>,
 *   reconnectLive: () => void,
 * }} opts
 * @returns {() => void} dispose
 */
export function bindLiveResume(opts) {
  let busy = false;
  let lastResumeAt = 0;

  async function resume(reason) {
    if (!opts.isActive() || busy) return;
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
      return;
    }
    // Evitar ráfaga visibility+pageshow que abre 2 SSE y satura workers PHP.
    const now = Date.now();
    if (now - lastResumeAt < 1500) return;
    lastResumeAt = now;
    busy = true;
    try {
      cleanupDrawOverlay();
      await opts.resync();
      // desdeId=0 en el servidor = cursor al último evento (sin reanimar historial).
      opts.reconnectLive();
    } catch (e) {
      console.warn('[vivo-resume]', reason, e);
    } finally {
      busy = false;
    }
  }

  const onVis = () => {
    if (document.visibilityState === 'visible') {
      resume('visibility');
    }
  };
  const onShow = (ev) => {
    // Solo bfcache (atrás); el primer pageshow del load no debe reabrir SSE.
    if (ev?.persisted) {
      resume('pageshow');
    }
  };

  document.addEventListener('visibilitychange', onVis);
  window.addEventListener('pageshow', onShow);

  return () => {
    document.removeEventListener('visibilitychange', onVis);
    window.removeEventListener('pageshow', onShow);
  };
}
