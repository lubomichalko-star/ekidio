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
  const result = await GoogleSignIn.signIn();
  return String(result?.idToken || '').trim();
}

export async function signOutNativeGoogle() {
  if (!isNativeGooglePlatform()) return;
  try {
    await GoogleSignIn.signOut();
  } catch {}
}
