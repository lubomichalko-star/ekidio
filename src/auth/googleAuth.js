import { Capacitor } from '@capacitor/core';
import { GoogleSignIn } from '@capawesome/capacitor-google-sign-in';

let initializedClientId = '';
let initializePromise = null;

export function isNativeGooglePlatform() {
  return Capacitor.isNativePlatform();
}

export async function initializeNativeGoogleSignIn(clientId) {
  const normalizedClientId = String(clientId || '').trim();
  if (!isNativeGooglePlatform() || !normalizedClientId) return false;

  if (initializedClientId === normalizedClientId && initializePromise) {
    return initializePromise;
  }

  initializePromise = GoogleSignIn.initialize({
    clientId: normalizedClientId,
  })
    .then(() => {
      initializedClientId = normalizedClientId;
      return true;
    })
    .catch((error) => {
      initializePromise = null;
      throw error;
    });

  return initializePromise;
}

export async function signInWithNativeGoogle(clientId) {
  await initializeNativeGoogleSignIn(clientId);
  try {
    const result = await GoogleSignIn.signIn();
    return String(result?.idToken || '').trim();
  } catch (error) {
    throw normalizeNativeGoogleError(error);
  }
}

function normalizeNativeGoogleError(error) {
  const message = String(error?.message || error || '').trim();
  const code = String(error?.code || '').trim();
  const haystack = `${code} ${message}`.toLowerCase();

  if (haystack.includes('developer_error') || haystack.includes('10:')) {
    return new Error(
      'Google prihlásenie nie je nastavené pre túto Android appku. V Google Cloud Console pridaj OAuth klient typu Android s balíčkom com.ekidio.app a SHA-1 odtlačkom podpisovacieho kľúča.'
    );
  }
  if (haystack.includes('cancel') || haystack.includes('12501')) {
    return new Error('Google prihlásenie bolo zrušené');
  }
  if (message) return new Error(message);
  return new Error('Google prihlásenie zlyhalo');
}

export async function signOutNativeGoogle() {
  if (!isNativeGooglePlatform()) return;
  try {
    await GoogleSignIn.signOut();
  } catch {}
}
