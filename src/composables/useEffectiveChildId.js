import { computed, unref, watch } from 'vue';

const resolveSource = (source) => {
  if (typeof source === 'function') return source();
  return unref(source);
};

/**
 * Single source of truth for resolving the active child ID in child-scoped views.
 * Always prefer this over ad-hoc props/route/localized fallbacks.
 */
export function useEffectiveChildId({ childId, route, localized } = {}) {
  return computed(() => {
    const loc = unref(localized) || {};
    const raw =
      unref(childId) ||
      unref(route)?.params?.childId ||
      loc.childId ||
      loc.children?.[0]?.id ||
      '';
    return raw === 0 || raw === '0' ? '' : String(raw || '');
  });
}

/**
 * Run a loader when child ID becomes available.
 * Shows a missing-ID error only after appReady (auth + bootstrap finished).
 */
export function useChildIdLoadTrigger(effectiveChildId, loadFn, { appReady, onMissing } = {}) {
  watch(
    () => [resolveSource(effectiveChildId), resolveSource(appReady) ?? true],
    ([cid, ready]) => {
      if (cid) {
        loadFn(cid);
        return;
      }
      if (ready && typeof onMissing === 'function') {
        onMissing();
      }
    },
    { immediate: true }
  );
}
