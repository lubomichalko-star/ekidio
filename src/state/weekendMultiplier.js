import { ref } from 'vue';

function readWpDefault() {
  try {
    const m = Number(window?.rodinneUlohyApp?.weekendMultiplier);
    if (m > 0) return m;
  } catch {
    // ignore
  }
  return 3;
}

export const weekendMultiplier = ref(readWpDefault());

export function setWeekendMultiplier(value) {
  const n = Number(value);
  if (Number.isFinite(n) && n > 0) {
    weekendMultiplier.value = n;
  }
}

export function applyWeekendMultiplierFromPayload(payload) {
  if (!payload) return;
  const m = Number(payload.weekendMultiplier ?? payload.multiplier);
  if (m > 0) setWeekendMultiplier(m);
}

export async function loadWeekendMultiplier(api) {
  if (!api?.getWeekendMultiplier) return weekendMultiplier.value;
  try {
    const res = await api.getWeekendMultiplier();
    applyWeekendMultiplierFromPayload(res);
  } catch {
    // ignore – overview or defaults still apply
  }
  return weekendMultiplier.value;
}

export function clearWeekendMultiplier() {
  weekendMultiplier.value = readWpDefault();
}
