export const KIOSK_SCREEN_IDLE_KEY = 'ru_kiosk_screen_idle_sec';
export const DEFAULT_KIOSK_SCREEN_IDLE_SEC = 15;
export const MIN_KIOSK_SCREEN_IDLE_SEC = 3;
export const MAX_KIOSK_SCREEN_IDLE_SEC = 300;

export function getKioskScreenIdleSec() {
  try {
    const raw = localStorage.getItem(KIOSK_SCREEN_IDLE_KEY);
    const n = Number(raw);
    if (!Number.isFinite(n)) return DEFAULT_KIOSK_SCREEN_IDLE_SEC;
    return Math.max(
      MIN_KIOSK_SCREEN_IDLE_SEC,
      Math.min(MAX_KIOSK_SCREEN_IDLE_SEC, Math.round(n))
    );
  } catch {
    return DEFAULT_KIOSK_SCREEN_IDLE_SEC;
  }
}

export function setKioskScreenIdleSec(sec) {
  const n = Math.max(
    MIN_KIOSK_SCREEN_IDLE_SEC,
    Math.min(MAX_KIOSK_SCREEN_IDLE_SEC, Math.round(Number(sec)))
  );
  try {
    localStorage.setItem(KIOSK_SCREEN_IDLE_KEY, String(n));
  } catch {
    // ignore
  }
  return n;
}

export function getKioskScreenIdleMs() {
  return getKioskScreenIdleSec() * 1000;
}
