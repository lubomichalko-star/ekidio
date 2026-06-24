import { Capacitor, registerPlugin } from '@capacitor/core';
import { getKioskScreenIdleMs, getKioskScreenIdleSec } from './kioskSettings';

const KioskMotionNative = registerPlugin('KioskMotion');

export function isKioskMotionAvailable() {
  return Capacitor.getPlatform() === 'android';
}

export async function startKioskMotion(options = {}) {
  if (!isKioskMotionAvailable()) {
    return false;
  }
  try {
    const idleTimeoutMs = Number(options.idleTimeoutMs || getKioskScreenIdleMs());
    const idleTimeoutSec = Number(options.idleTimeoutSec || getKioskScreenIdleSec());
    await KioskMotionNative.start({
      idleTimeoutMs,
      idleTimeoutSec,
    });
    return true;
  } catch {
    return false;
  }
}

export async function notifyKioskUserActivity() {
  if (!isKioskMotionAvailable()) {
    return;
  }
  try {
    await KioskMotionNative.notifyUserActivity();
  } catch {
    // ignore
  }
}

export async function updateKioskMotionIdleTimeout(idleTimeoutSec) {
  if (!isKioskMotionAvailable()) {
    return false;
  }
  try {
    const sec = Number(idleTimeoutSec || getKioskScreenIdleSec());
    await KioskMotionNative.updateIdleTimeout({
      idleTimeoutMs: Math.max(1000, Math.round(sec * 1000)),
      idleTimeoutSec: sec,
    });
    return true;
  } catch {
    return false;
  }
}

export async function stopKioskMotion() {
  if (!isKioskMotionAvailable()) {
    return;
  }
  try {
    await KioskMotionNative.stop();
  } catch {
    // ignore
  }
}
