import { CONFIG } from './config.js';
import { getToken, clearSession } from './auth.js';

export class ApiError extends Error {
  constructor(message, status, body) {
    super(message);
    this.status = status;
    this.body = body;
  }
}

export async function api(path, options = {}) {
  const headers = Object.assign({}, options.headers || {});
  const token = getToken();

  if (token && !headers.Authorization) {
    headers.Authorization = 'Bearer ' + token;
  }

  let body = options.body;
  if (body && !(body instanceof FormData) && typeof body === 'object') {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(body);
  }

  const res = await fetch(CONFIG.apiBase + path, {
    ...options,
    headers,
    body,
  });

  const text = await res.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch (e) {
      data = { raw: text };
    }
  }

  if (res.status === 401) {
    clearSession();
  }

  if (!res.ok) {
    throw new ApiError(
      (data && data.error) || ('Error HTTP ' + res.status),
      res.status,
      data
    );
  }

  return data;
}
