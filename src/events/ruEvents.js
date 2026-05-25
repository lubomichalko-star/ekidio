export const RU_EVENTS = {
  AUTH_CHANGED: 'ru-auth-changed',
  DATA_CHANGED: 'ru-data-changed',
  FORCE_OVERVIEW_REFRESH: 'ru-force-overview-refresh',
};

function hasWindow() {
  return typeof window !== 'undefined' && !!window;
}

function safeDispatch(name, detail) {
  if (!hasWindow()) return;
  try {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  } catch {}
}

function safeListen(name, handler) {
  if (!hasWindow()) return () => {};
  try {
    window.addEventListener(name, handler);
  } catch {}
  return () => {
    try {
      window.removeEventListener(name, handler);
    } catch {}
  };
}

export function emitRuAuthChanged(detail = {}) {
  safeDispatch(RU_EVENTS.AUTH_CHANGED, detail);
}

export function emitRuDataChanged(detail = {}) {
  safeDispatch(RU_EVENTS.DATA_CHANGED, detail);
}

export function emitRuForceOverviewRefresh(detail = {}) {
  safeDispatch(RU_EVENTS.FORCE_OVERVIEW_REFRESH, detail);
}

export function onRuAuthChanged(handler) {
  return safeListen(RU_EVENTS.AUTH_CHANGED, handler);
}

export function onRuDataChanged(handler) {
  return safeListen(RU_EVENTS.DATA_CHANGED, handler);
}

export function onRuForceOverviewRefresh(handler) {
  return safeListen(RU_EVENTS.FORCE_OVERVIEW_REFRESH, handler);
}

