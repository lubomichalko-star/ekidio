function normalizeBaseUrl(url) {
  if (!url) return '';
  return String(url).replace(/\/+$/, '');
}

export function getApiBaseUrl() {
  const fromEnv = import.meta?.env?.VITE_API_BASE_URL;
  const fromWp = typeof window !== 'undefined' ? window.rodinneUlohyApp?.apiBaseUrl : '';
  // Hardcoded fallback for Capacitor build (bundled SPA, no WP bootstrap)
  const fallback = 'https://ekidio.com/wp-json/rodinne-ulohy/v1';
  return normalizeBaseUrl(fromEnv || fromWp || fallback);
}

export function getSiteBaseUrl() {
  const api = getApiBaseUrl();
  if (!api) return '';
  // Pretty permalinks: https://site.tld/wp-json/rodinne-ulohy/v1
  const idx = api.indexOf('/wp-json/');
  if (idx > 0) return normalizeBaseUrl(api.slice(0, idx));
  // Plain permalinks: https://site.tld/index.php?rest_route=/rodinne-ulohy/v1
  const restRouteIdx = api.indexOf('?rest_route=');
  if (restRouteIdx > 0) return normalizeBaseUrl(api.slice(0, restRouteIdx));
  // Fallback: strip trailing namespace segment
  return normalizeBaseUrl(api.replace(/\/rodinne-ulohy\/v1\/?$/, ''));
}

export function getInitialApiToken() {
  const fromWp = typeof window !== 'undefined' ? window.rodinneUlohyApp?.apiToken : '';
  return fromWp || '';
}

export function getGoogleClientId() {
  const fromEnv = import.meta?.env?.VITE_GOOGLE_CLIENT_ID;
  const fromWp = typeof window !== 'undefined' ? window.rodinneUlohyApp?.googleClientId : '';
  // Public OAuth Web client ID (same as ekidio.com WP config) for Capacitor builds.
  const fallback = '1084546512159-g2js3qh2fhrnabsv122f0cqhv48kp80e.apps.googleusercontent.com';
  return String(fromEnv || fromWp || fallback).trim();
}

export const appEnv = import.meta?.env?.MODE || 'production';

