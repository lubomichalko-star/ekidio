export const CHILD_COLOR_PREFIX = 'child:';
export const DEFAULT_CHILD_THEME = 'green';

export const AVATAR_THEMES = {
  blue: {
    key: 'blue',
    background: 'var(--blue-100)',
    color: 'var(--blue-700)',
    accent: 'var(--blue-500)',
    hex: '#2563EB',
  },
  green: {
    key: 'green',
    background: 'var(--green-100)',
    color: 'var(--green-700)',
    accent: 'var(--green-500)',
    hex: '#16a34a',
  },
  yellow: {
    key: 'yellow',
    background: 'var(--yellow-100)',
    color: 'var(--yellow-700)',
    accent: 'var(--yellow-500)',
    hex: '#f59e0b',
  },
  cyan: {
    key: 'cyan',
    background: 'var(--cyan-100)',
    color: 'var(--cyan-700)',
    accent: 'var(--cyan-500)',
    hex: '#06b6d4',
  },
  pink: {
    key: 'pink',
    background: 'var(--pink-100)',
    color: 'var(--pink-700)',
    accent: 'var(--pink-500)',
    hex: '#ec4899',
  },
  indigo: {
    key: 'indigo',
    background: 'var(--indigo-100)',
    color: 'var(--indigo-700)',
    accent: 'var(--indigo-500)',
    hex: '#6366f1',
  },
  teal: {
    key: 'teal',
    background: 'var(--teal-100)',
    color: 'var(--teal-700)',
    accent: 'var(--teal-500)',
    hex: '#14b8a6',
  },
  orange: {
    key: 'orange',
    background: 'var(--orange-100)',
    color: 'var(--orange-700)',
    accent: 'var(--orange-500)',
    hex: '#f97316',
  },
  violet: {
    key: 'violet',
    background: 'var(--violet-100)',
    color: 'var(--violet-700)',
    accent: 'var(--violet-500)',
    hex: '#8b5cf6',
  },
  red: {
    key: 'red',
    background: 'var(--red-100)',
    color: 'var(--red-700)',
    accent: 'var(--red-500)',
    hex: '#ef4444',
  },
};

export const AVATAR_THEME_KEYS = Object.keys(AVATAR_THEMES);

const LEGACY_COLOR_TO_THEME = {
  '#dbeafe': 'blue',
  '#0ea5e9': 'blue',
  '#2563eb': 'blue',
  '#1d4ed8': 'blue',
  '#dcfce7': 'green',
  '#16a34a': 'green',
  '#4caf50': 'green',
  '#5abb6f': 'green',
  '#fef9c3': 'yellow',
  '#f59e0b': 'yellow',
  '#cffafe': 'cyan',
  '#06b6d4': 'cyan',
  '#fce7f3': 'pink',
  '#ec4899': 'pink',
  '#e0e7ff': 'indigo',
  '#6366f1': 'indigo',
  '#ccfbf1': 'teal',
  '#14b8a6': 'teal',
  '#ffedd5': 'orange',
  '#f97316': 'orange',
  '#f3e8ff': 'violet',
  '#8b5cf6': 'violet',
  '#fee2e2': 'red',
  '#ef4444': 'red',
};

function readColor(valueOrChild) {
  if (valueOrChild && typeof valueOrChild === 'object') {
    return String(valueOrChild.color || '').trim().toLowerCase();
  }
  return String(valueOrChild || '').trim().toLowerCase();
}

export function isChildSelectedColor(valueOrChild) {
  return readColor(valueOrChild).startsWith(CHILD_COLOR_PREFIX);
}

export function getThemeKey(valueOrChild) {
  const value = readColor(valueOrChild);
  const rawKey = value.startsWith(CHILD_COLOR_PREFIX)
    ? value.slice(CHILD_COLOR_PREFIX.length)
    : value;

  if (AVATAR_THEMES[rawKey]) return rawKey;
  return LEGACY_COLOR_TO_THEME[rawKey] || 'blue';
}

export function getParentTheme(valueOrChild) {
  return AVATAR_THEMES[getThemeKey(valueOrChild)];
}

export function getChildTheme(valueOrChild) {
  if (!isChildSelectedColor(valueOrChild)) {
    return AVATAR_THEMES[DEFAULT_CHILD_THEME];
  }
  return AVATAR_THEMES[getThemeKey(valueOrChild)];
}

export function getChildHeroTheme(valueOrChild) {
  return getParentTheme(valueOrChild);
}

export function getAvatarStyle(theme) {
  return {
    background: theme.background,
    color: theme.color,
  };
}

export function getAccentHex(valueOrChild) {
  const theme = getParentTheme(valueOrChild);
  return theme?.hex || '#0ea5e9';
}

export function toChildSelectedColor(themeKey) {
  const safeThemeKey = AVATAR_THEMES[themeKey] ? themeKey : DEFAULT_CHILD_THEME;
  return `${CHILD_COLOR_PREFIX}${safeThemeKey}`;
}
