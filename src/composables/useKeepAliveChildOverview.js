import { onActivated, onBeforeUnmount, ref, unref } from 'vue';
import { useChildOverview } from './useChildOverview';
import { onRuDataChanged } from '../events/ruEvents';
import { patchChildOverviewSnapshot, syncChildOverviewSnapshot } from '../state/preloadCache';

/**
 * Child overview loader for KeepAlive views (Rewards, Settings, …).
 * - Revalidates when the tab is opened again
 * - Applies cross-tab patches from ru-data-changed events
 */
export function useKeepAliveChildOverview({
  api,
  effectiveChildId,
  enabled,
  revalidateMs = 8000,
} = {}) {
  const childData = ref(null);
  const loading = ref(true);
  const error = ref('');

  const { load } = useChildOverview({
    api,
    dataRef: childData,
    loadingRef: loading,
    errorRef: error,
    revalidateMs,
  });

  const isEnabled = () => {
    const flag = unref(enabled);
    return flag !== false;
  };

  const todayDay = () => new Date().getDay();

  const loadOverview = ({ force = false, background = false } = {}) => {
    const cid = String(unref(effectiveChildId) || '');
    if (!isEnabled() || !cid) return Promise.resolve(null);
    return load({ childId: cid, day: todayDay(), force, background });
  };

  const applySnapshot = (snapshot) => {
    if (!snapshot) return;
    childData.value = snapshot;
    syncChildOverviewSnapshot(unref(effectiveChildId), snapshot);
  };

  const applyPatch = (patchFn) => {
    const cid = String(unref(effectiveChildId) || '');
    if (!cid || typeof patchFn !== 'function') return;

    if (childData.value) {
      const patched = patchFn({ ...childData.value });
      childData.value = patched;
      syncChildOverviewSnapshot(cid, patched);
      return;
    }

    const next = patchChildOverviewSnapshot(cid, (prev) => patchFn({ ...prev }));
    if (next) childData.value = next;
  };

  const onDataChanged = (e) => {
    if (!isEnabled()) return;
    const detail = e?.detail || {};
    const cid = String(unref(effectiveChildId) || '');
    if (!cid || String(detail.childId || '') !== cid) return;

    if (detail.type === 'task_status_changed') {
      if (typeof detail.points_balance === 'number') {
        applyPatch((prev) => ({
          ...prev,
          points_balance: detail.points_balance,
          points_today:
            typeof detail.points_today === 'number' ? detail.points_today : prev.points_today,
        }));
      } else {
        loadOverview({ force: true, background: true });
      }
      return;
    }

    if (
      detail.type === 'reward_purchased' ||
      detail.type === 'reward_used' ||
      detail.type === 'reward_changed'
    ) {
      if (detail.overview) {
        applySnapshot(detail.overview);
      } else if (typeof detail.points_balance === 'number') {
        applyPatch((prev) => ({
          ...prev,
          points_balance: detail.points_balance,
          points_today:
            typeof detail.points_today === 'number' ? detail.points_today : prev.points_today,
        }));
      }
      // Always revalidate — purchase/use payloads may omit active_purchases ids.
      loadOverview({ force: true, background: true });
    }
  };

  let offData = null;

  onActivated(() => {
    if (!isEnabled()) return;
    loadOverview({ force: true, background: !!childData.value });
  });

  offData = onRuDataChanged(onDataChanged);

  onBeforeUnmount(() => {
    try {
      offData?.();
    } catch {}
    offData = null;
  });

  return {
    childData,
    loading,
    error,
    loadOverview,
    applySnapshot,
    applyPatch,
  };
}
