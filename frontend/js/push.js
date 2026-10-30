import { api } from './api.js';
import { isLoggedIn } from './auth.js';

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const out = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
  return out;
}

export async function registrarServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    return null;
  }
  const reg = await navigator.serviceWorker.register('./sw.js?v=232', { scope: './' });
  return reg;
}

export async function activarNotificaciones() {
  if (!isLoggedIn()) {
    throw new Error('Inicia sesión para activar notificaciones.');
  }
  if (!('Notification' in window) || !('serviceWorker' in navigator)) {
    throw new Error('Este navegador no soporta notificaciones.');
  }
  if (!('PushManager' in window)) {
    throw new Error(
      'Push no disponible. En iPhone: Safari → Compartir → Añadir a Inicio, luego abre la app.'
    );
  }

  const permiso = await Notification.requestPermission();
  if (permiso !== 'granted') {
    throw new Error('Permiso de notificaciones denegado.');
  }

  const { publicKey } = await api('/push/vapid-publica');
  const reg = await navigator.serviceWorker.ready;
  let sub = await reg.pushManager.getSubscription();

  if (!sub) {
    sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicKey),
    });
  }

  const json = sub.toJSON();
  await api('/push/suscribir', {
    method: 'POST',
    body: {
      endpoint: json.endpoint,
      keys: {
        p256dh: json.keys.p256dh,
        auth: json.keys.auth,
      },
    },
  });

  return true;
}

export function puedeInstalarPwa() {
  return !!window.__bingoDeferredPrompt;
}

export async function instalarPwa() {
  const prompt = window.__bingoDeferredPrompt;
  if (!prompt) {
    throw new Error(
      'Usa el menú del navegador: “Instalar app” o en iPhone “Añadir a Inicio”.'
    );
  }
  prompt.prompt();
  const result = await prompt.userChoice;
  window.__bingoDeferredPrompt = null;
  return result.outcome === 'accepted';
}

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  window.__bingoDeferredPrompt = e;
});
