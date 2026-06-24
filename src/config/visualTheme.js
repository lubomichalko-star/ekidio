export const VISUAL_THEMES = ['lubo', 'stano'];
export const DEFAULT_VISUAL_THEME = 'lubo';
export const VISUAL_THEME_STORAGE_KEY = 'ekidio_visual_theme';

export function readThemeFromUrl() {
  if (typeof window === 'undefined') return '';
  try {
    const fromSearch = new URLSearchParams(window.location.search).get('theme');
    if (VISUAL_THEMES.includes(fromSearch)) return fromSearch;
    const hash = String(window.location.hash || '');
    const qIndex = hash.indexOf('?');
    if (qIndex >= 0) {
      const fromHash = new URLSearchParams(hash.slice(qIndex + 1)).get('theme');
      if (VISUAL_THEMES.includes(fromHash)) return fromHash;
    }
  } catch {}
  return '';
}

export function getVisualTheme() {
  const fromUrl = readThemeFromUrl();
  if (fromUrl) return fromUrl;

  try {
    const fromLs = localStorage.getItem(VISUAL_THEME_STORAGE_KEY);
    if (VISUAL_THEMES.includes(fromLs)) return fromLs;
  } catch {}

  return DEFAULT_VISUAL_THEME;
}

export function applyVisualTheme(theme) {
  const safe = VISUAL_THEMES.includes(theme) ? theme : DEFAULT_VISUAL_THEME;
  if (typeof document !== 'undefined') {
    document.documentElement.dataset.visualTheme = safe;
    for (const name of VISUAL_THEMES) {
      document.documentElement.classList.remove(`ru-theme-${name}`);
    }
    document.documentElement.classList.add(`ru-theme-${safe}`);
  }
  try {
    localStorage.setItem(VISUAL_THEME_STORAGE_KEY, safe);
  } catch {}
  return safe;
}

export function initVisualTheme() {
  const fromUrl = readThemeFromUrl();
  const theme = fromUrl || getVisualTheme();
  return applyVisualTheme(theme);
}
