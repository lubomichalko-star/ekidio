import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';

const KEY = 'ru_api_token';
let memoryToken = '';

export async function getToken() {
  if (memoryToken) return memoryToken;
  if (Capacitor.isNativePlatform()) {
    const { value } = await Preferences.get({ key: KEY });
    memoryToken = value || '';
    return memoryToken;
  }
  memoryToken = localStorage.getItem(KEY) || '';
  return memoryToken;
}

export async function setToken(token) {
  const v = token || '';
  memoryToken = v;
  if (Capacitor.isNativePlatform()) {
    await Preferences.set({ key: KEY, value: v });
  } else {
    localStorage.setItem(KEY, v);
  }
}

export async function clearToken() {
  memoryToken = '';
  if (Capacitor.isNativePlatform()) {
    await Preferences.remove({ key: KEY });
  } else {
    localStorage.removeItem(KEY);
  }
}

