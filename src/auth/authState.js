import { getToken, clearToken } from './tokenStorage';
import { http } from '../api/http';
import { emitRuAuthChanged } from '../events/ruEvents';

const KEY_ROLE = 'ru_auth_role';
const KEY_CHILD_ID = 'ru_child_id';
const KEY_ME_TS = 'ru_auth_me_ts';

// Keep aligned with previous router logic.
export const ME_TTL_MS = 5 * 60 * 1000;

let meInFlight = null;

function safeGet(key) {
  try {
    return typeof window !== 'undefined' ? (localStorage.getItem(key) || '') : '';
  } catch {
    return '';
  }
}

function safeSet(key, val) {
  try {
    if (typeof window !== 'undefined') localStorage.setItem(key, String(val ?? ''));
  } catch {}
}

function safeRemove(key) {
  try {
    if (typeof window !== 'undefined') localStorage.removeItem(key);
  } catch {}
}

export function getStoredAuth() {
  const role = safeGet(KEY_ROLE);
  const childId = safeGet(KEY_CHILD_ID);
  const tsRaw = safeGet(KEY_ME_TS);
  const ts = tsRaw ? Number(tsRaw) : 0;
  return { role, childId, ts: Number.isFinite(ts) ? ts : 0 };
}

export function setStoredAuth({ role, childId } = {}) {
  const r = String(role || '');
  if (r) {
    safeSet(KEY_ROLE, r);
    if (r === 'parent') {
      safeRemove(KEY_CHILD_ID);
    } else if (r === 'child') {
      safeSet(KEY_CHILD_ID, String(childId || ''));
    }
    safeSet(KEY_ME_TS, String(Date.now()));
  }
}

export function clearStoredAuth() {
  safeRemove(KEY_ROLE);
  safeRemove(KEY_CHILD_ID);
  safeRemove(KEY_ME_TS);
}

export function dispatchAuthChanged({ role, childId } = {}) {
  emitRuAuthChanged({ role, childId: childId || '' });
}

export function isMeFresh(now = Date.now()) {
  const { role, ts } = getStoredAuth();
  if (!role || !ts) return false;
  return Number.isFinite(ts) && now - ts < ME_TTL_MS;
}

/**
 * Resolve current subject from /auth/me and cache role + child_id to localStorage.
 *
 * Returns: { loggedIn: boolean, role: 'parent'|'child'|'', childId: string }
 */
export async function ensureAuthFromMe({ force = false } = {}) {
  const token = await getToken();
  if (!token) {
    clearStoredAuth();
    return { loggedIn: false, role: '', childId: '' };
  }

  // Fast path: cached role is still fresh.
  if (!force && isMeFresh()) {
    const { role, childId } = getStoredAuth();
    return { loggedIn: true, role, childId };
  }

  if (meInFlight) return meInFlight;

  meInFlight = (async () => {
    try {
      const me = await http.get('/auth/me');
      const type = me?.subject?.type || '';
      if (type === 'parent') {
        setStoredAuth({ role: 'parent', childId: '' });
        dispatchAuthChanged({ role: 'parent', childId: '' });
        return { loggedIn: true, role: 'parent', childId: '' };
      }
      if (type === 'child') {
        const cid = String(me?.subject?.child_id || '');
        setStoredAuth({ role: 'child', childId: cid });
        dispatchAuthChanged({ role: 'child', childId: cid });
        return { loggedIn: true, role: 'child', childId: cid };
      }

      // Unknown shape -> treat as logged out.
      await clearToken();
      clearStoredAuth();
      dispatchAuthChanged({ role: 'child', childId: '' });
      return { loggedIn: false, role: '', childId: '' };
    } catch (e) {
      // Token invalid/expired or network error:
      // - if auth is broken, clear it (consistent with old behavior)
      try {
        await clearToken();
      } catch {}
      clearStoredAuth();
      dispatchAuthChanged({ role: 'child', childId: '' });
      throw e;
    } finally {
      meInFlight = null;
    }
  })();

  return meInFlight;
}

