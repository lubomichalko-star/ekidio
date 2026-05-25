import { getApiBaseUrl } from '../config/appConfig';
import { getToken } from '../auth/tokenStorage';
import { clearToken } from '../auth/tokenStorage';
import { clearStoredAuth, dispatchAuthChanged, getStoredAuth } from '../auth/authState';

async function request(method, path, { body, isFormData = false, query } = {}) {
  const base = getApiBaseUrl();
  if (!base) throw new Error('API base URL is missing (VITE_API_BASE_URL)');

  const p = String(path || '');

  const url = new URL(base + path);
  const isGet = String(method).toUpperCase() === 'GET';
  if (query && typeof query === 'object') {
    Object.entries(query).forEach(([k, v]) => {
      if (v === undefined || v === null || v === '') return;
      url.searchParams.set(k, String(v));
    });
  }
  // Extra cache-buster for environments that ignore fetch cache options/headers.
  // Should be ignored by backend endpoints.
  if (isGet && !url.searchParams.has('ru_ts')) {
    url.searchParams.set('ru_ts', String(Date.now()));
  }

  const token = await getToken();

  // Avoid noisy 401 network errors: if we don't have a token, don't call protected endpoints.
  // Only auth endpoints are allowed without a token.
  const allowWithoutToken =
    p === '/auth/login' ||
    p === '/auth/logout' ||
    p === '/auth/invite/accept' ||
    p === '/auth/register' ||
    p === '/auth/register/verify' ||
    p === '/auth/register/resend' ||
    p === '/auth/google';
  if (!token && !allowWithoutToken) {
    const err = new Error('Nie ste prihlásený');
    err.status = 401;
    throw err;
  }

  const headers = {};
  if (token) headers.Authorization = `Bearer ${token}`;
  if (!isFormData) headers['Content-Type'] = 'application/json';
  // Prevent stale lists after mutations (WP/proxies/WebView can cache GETs aggressively).
  if (isGet) {
    headers['Cache-Control'] = 'no-store, no-cache, max-age=0';
    headers.Pragma = 'no-cache';
  }

  const res = await fetch(url.toString(), {
    method,
    headers,
    body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
    // Ensure browser/WebView doesn't serve cached GET responses.
    cache: isGet ? 'no-store' : 'default',
  });

  const text = await res.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = text;
  }

  if (!res.ok) {
    // If token is invalid/expired, clear local auth and route to login.
    // IMPORTANT: do NOT logout parent on "normal" 403 (e.g. deleted child_id / forbidden resource).
    // Only treat 404 as session invalid for CHILD role on child-scoped endpoints.
    const isChildScoped = p.startsWith('/child/') || p === '/auth/me';
    const role = getStoredAuth()?.role || '';
    const isChildRole = role === 'child';
    const shouldClearAuth =
      res.status === 401 ||
      (res.status === 403 && p === '/auth/me') ||
      (res.status === 404 && isChildRole && isChildScoped) ||
      (res.status === 403 && isChildRole && p.startsWith('/child/'));

    if (shouldClearAuth) {
      try {
        await clearToken();
      } catch {}
      clearStoredAuth();
      dispatchAuthChanged({ role: 'child', childId: '' });
      try {
        if (typeof window !== 'undefined' && window.location && !String(window.location.hash || '').includes('#/login')) {
          window.location.hash = '#/login';
        }
      } catch {}
    }
    const message =
      (data && data.message) ||
      (data && data.data && data.data.message) ||
      `HTTP ${res.status}`;
    const err = new Error(message);
    err.status = res.status;
    err.data = data;
    throw err;
  }

  return data;
}

export const http = {
  get: (path, opts) => request('GET', path, opts),
  post: (path, body, opts = {}) => request('POST', path, { ...opts, body }),
  del: (path, opts) => request('DELETE', path, opts),
  postForm: (path, formData, opts = {}) =>
    request('POST', path, { ...opts, body: formData, isFormData: true }),
};

