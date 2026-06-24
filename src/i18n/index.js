import { reactive } from 'vue';

import sk from './sk.json';
import en from './en.json';

const messages = { sk, en };

const localeMap = {
  sk_SK: 'sk',
  en_US: 'en',
  en_GB: 'en',
};

export const i18nState = reactive({
  locale: 'sk',
});

const STORAGE_KEY = 'ekidio_locale';

function getNestedValue(obj, path) {
  return path.split('.').reduce((current, key) => {
    return current && current[key] !== undefined ? current[key] : undefined;
  }, obj);
}

export function t(key, fallback = '') {
  const currentMessages = messages[i18nState.locale] || messages.sk;
  const fallbackMessages = messages.sk;

  return (
    getNestedValue(currentMessages, key) ||
    getNestedValue(fallbackMessages, key) ||
    fallback ||
    key
  );
}

export function setLocale(locale) {
  if (!messages[locale]) return;
  i18nState.locale = locale;
  try {
    localStorage.setItem(STORAGE_KEY, locale);
  } catch {}
  if (typeof document !== 'undefined') {
    document.documentElement.lang = locale === 'en' ? 'en' : 'sk';
  }
}

export function initI18n() {
  const wpLocale = typeof window !== 'undefined' ? window.rodinneUlohyApp?.locale : '';
  let locale = localeMap[wpLocale] || 'sk';

  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved && messages[saved]) locale = saved;
  } catch {}

  setLocale(locale);
}
