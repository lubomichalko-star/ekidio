import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';

const DISMISS_PREFIX = 'ru_app_update_dismissed_';
const CHECK_URL = 'https://ekidio.com/download/version.json';
const DEFAULT_DOWNLOAD_URL = 'https://ekidio.com/download/';
const CHECK_INTERVAL_MS = 6 * 60 * 60 * 1000;

let lastCheckAt = 0;
let cachedAvailableUpdate = undefined;

export function parseVersionParts(version) {
  return String(version || '0')
    .split('.')
    .map((part) => parseInt(String(part).replace(/[^\d]/g, ''), 10) || 0);
}

export function isVersionNewer(latest, current) {
  const a = parseVersionParts(latest);
  const b = parseVersionParts(current);
  const len = Math.max(a.length, b.length);
  for (let i = 0; i < len; i += 1) {
    const av = a[i] || 0;
    const bv = b[i] || 0;
    if (av > bv) return true;
    if (av < bv) return false;
  }
  return false;
}

export async function getNativeAppVersion() {
  if (!Capacitor.isNativePlatform()) return '';
  try {
    const info = await App.getInfo();
    return String(info?.version || '').trim();
  } catch {
    return '';
  }
}

export function isUpdateDismissed(latestVersion) {
  const key = `${DISMISS_PREFIX}${String(latestVersion || '').trim()}`;
  if (!key || key === DISMISS_PREFIX) return false;
  try {
    return localStorage.getItem(key) === '1';
  } catch {
    return false;
  }
}

export function dismissUpdate(latestVersion) {
  const key = `${DISMISS_PREFIX}${String(latestVersion || '').trim()}`;
  if (!key || key === DISMISS_PREFIX) return;
  try {
    localStorage.setItem(key, '1');
  } catch {}
}

export async function fetchLatestAndroidRelease() {
  const res = await fetch(CHECK_URL, { cache: 'no-store' });
  if (!res.ok) {
    throw new Error(`Version check failed (${res.status})`);
  }
  const data = await res.json();
  return data?.android || data || {};
}

export async function getAvailableAppUpdate({ force = false } = {}) {
  if (!Capacitor.isNativePlatform()) return null;
  if (Capacitor.getPlatform() !== 'android') return null;

  const now = Date.now();
  if (!force && cachedAvailableUpdate !== undefined && now - lastCheckAt < CHECK_INTERVAL_MS) {
    return cachedAvailableUpdate;
  }

  const currentVersion = await getNativeAppVersion();
  if (!currentVersion) {
    cachedAvailableUpdate = null;
    lastCheckAt = now;
    return null;
  }

  try {
    const release = await fetchLatestAndroidRelease();
    const latestVersion = String(release?.latestVersion || '').trim();
    const downloadUrl = String(release?.downloadUrl || DEFAULT_DOWNLOAD_URL).trim() || DEFAULT_DOWNLOAD_URL;
    const message = String(release?.message || 'Je dostupná nová verzia aplikácie ekidio.').trim();

    if (!latestVersion || !isVersionNewer(latestVersion, currentVersion)) {
      cachedAvailableUpdate = null;
      lastCheckAt = now;
      return null;
    }

    cachedAvailableUpdate = {
      currentVersion,
      latestVersion,
      downloadUrl,
      message,
    };
    lastCheckAt = now;
    return cachedAvailableUpdate;
  } catch {
    cachedAvailableUpdate = null;
    lastCheckAt = now;
    return null;
  }
}

/** Banner: skryté po „Neskôr“, inak rovnaké ako getAvailableAppUpdate. */
export async function checkForAppUpdate({ force = false } = {}) {
  const info = await getAvailableAppUpdate({ force });
  if (!info) return null;
  if (isUpdateDismissed(info.latestVersion)) return null;
  return info;
}

export function buildDownloadTarget(baseUrl, latestVersion) {
  const base = String(baseUrl || DEFAULT_DOWNLOAD_URL).trim() || DEFAULT_DOWNLOAD_URL;
  const version = String(latestVersion || '').trim();
  if (!version) return base;

  // Direct APK link avoids stale cached landing page (index.html) on CDN/browser.
  if (base.endsWith('/')) {
    return `${base}ekidio-${version}.apk`;
  }
  if (base.endsWith('.apk')) return base;

  const sep = base.includes('?') ? '&' : '?';
  return `${base}${sep}v=${encodeURIComponent(version)}`;
}

export function openDownloadPage(url, latestVersion) {
  const target = buildDownloadTarget(url, latestVersion);
  try {
    window.location.assign(target);
  } catch {
    window.open(target, '_blank');
  }
}
