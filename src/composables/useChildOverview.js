import { ref } from 'vue';
import { api as defaultApi } from '../api/client';
import {
  getCachedOverview,
  getCachedOverviewAgeMs,
  setCachedOverview,
} from '../state/preloadCache';
import { applyWeekendMultiplierFromPayload } from '../state/weekendMultiplier';

/**
 * Shared loader for "child overview" payload used by both:
 * - Child view (child-scoped /child/overview)
 * - Parent overview view (also uses /child/overview with child_id)
 *
 * Features:
 * - Uses preload cache (instant UI)
 * - Optional background revalidation after N ms
 * - "last request wins" guard (prevents race conditions)
 */
export function useChildOverview({
  api = defaultApi,
  dataRef,
  loadingRef,
  errorRef,
  revalidateMs = 8000,
} = {}) {
  const data = dataRef || ref(null);
  const loading = loadingRef || ref(false);
  const error = errorRef || ref('');

  let reqId = 0;

  const load = async ({ childId, day, weekStart = '', force = false, background = false } = {}) => {
    const cid = String(childId || '');
    const d = day === null || day === undefined ? null : Number(day);
    const ws = String(weekStart || '');
    const myReqId = ++reqId;

    if (!cid) {
      if (!background && myReqId === reqId) {
        data.value = null;
        error.value = 'Chýba ID dieťaťa';
        loading.value = false;
      }
      return null;
    }

    const cached = getCachedOverview(cid, d, ws);
    const age = getCachedOverviewAgeMs(cid, d, ws);

    // Show cached immediately (instant UI); refresh in background when stale.
    if (cached && !force) {
      if (myReqId === reqId) {
        data.value = cached;
        applyWeekendMultiplierFromPayload(cached);
        error.value = '';
        loading.value = false;
      }
      if (Number.isFinite(age) && age < revalidateMs) return cached;
      background = true;
    }

    // Keep current UI visible while fetching another day / revalidating.
    if (data.value && !force) {
      background = true;
    }

    if (!background && myReqId === reqId) {
      loading.value = true;
      error.value = '';
    }

    try {
      const fresh = await api.getChildOverview(cid, d, ws || null);
      if (myReqId !== reqId) return null;
      data.value = fresh;
      applyWeekendMultiplierFromPayload(fresh);
      setCachedOverview(cid, d, fresh, ws || fresh?.week_range?.start || '');
      return fresh;
    } catch (e) {
      if (myReqId !== reqId) return null;
      if (!background) {
        data.value = null;
        error.value = e?.message || 'Chyba pri načítaní dát';
        loading.value = false;
      }
      throw e;
    } finally {
      if (!background && myReqId === reqId) {
        loading.value = false;
      }
    }
  };

  return { data, loading, error, load };
}

