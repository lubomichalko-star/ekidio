function normalizeBaseUrl(url) {
  if (!url) return '';
  return String(url).replace(/\/+$/, '');
}

export function getApiBaseUrl() {
  const fromEnv = import.meta?.env?.VITE_API_BASE_URL;
  const fromWp = typeof window !== 'undefined' ? window.rodinneUlohyApp?.apiBaseUrl : '';
  // Hardcoded fallback for Capacitor build (offline mode)
  const fallback = 'https://lubomichalko.com/wp-json/rodinne-ulohy/v1';
  return normalizeBaseUrl(fromEnv || fromWp || fallback);
}

export function getSiteBaseUrl() {
  const api = getApiBaseUrl();
  if (!api) return '';
  // Typical WP REST base: https://site.tld/wp-json/rodinne-ulohy/v1
  const idx = api.indexOf('/wp-json/');
  if (idx > 0) return normalizeBaseUrl(api.slice(0, idx));
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
  return String(fromEnv || fromWp || '').trim();
}

export const appEnv = import.meta?.env?.MODE || 'production';

