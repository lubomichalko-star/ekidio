import { applyWeekendMultiplierFromPayload, clearWeekendMultiplier } from './weekendMultiplier';
import { getWeekStartForDate } from '../utils/days';

const WEEK_DAYS = [1, 2, 3, 4, 5, 6, 0];

const state = {
  children: null,
  tasks: null,
  rewards: null,
  // key: `${childId}` -> { payload, ts }
  pointsOverview: new Map(),
  // key: `${childId}:${day}` -> { payload, ts }
  overview: new Map(),
  // timestamps
  ts: {
    children: 0,
    tasks: 0,
    rewards: 0,
  },
};

export function clearPreloadCache() {
  state.children = null;
  state.tasks = null;
  state.rewards = null;
  state.pointsOverview = new Map();
  state.overview = new Map();
  state.ts.children = 0;
  state.ts.tasks = 0;
  state.ts.rewards = 0;
  clearWeekendMultiplier();
}

export function setCachedChildren(list) {
  state.children = Array.isArray(list) ? list : [];
  state.ts.children = Date.now();
}

export function getCachedChildren() {
  return state.children;
}

export function setCachedTasks(list) {
  state.tasks = Array.isArray(list) ? list : [];
  state.ts.tasks = Date.now();
}

export function getCachedTasks() {
  return state.tasks;
}

export function setCachedRewards(list) {
  state.rewards = Array.isArray(list) ? list : [];
  state.ts.rewards = Date.now();
}

export function getCachedRewards() {
  return state.rewards;
}

export function setCachedPointsOverview(childId, payload) {
  const key = String(childId || '');
  if (!key) return;
  state.pointsOverview.set(key, { payload, ts: Date.now() });
}

export function getCachedPointsOverview(childId) {
  const key = String(childId || '');
  if (!key) return null;
  const entry = state.pointsOverview.get(key) || null;
  return entry && entry.payload ? entry.payload : null;
}

export function getCachedPointsOverviewAgeMs(childId) {
  const key = String(childId || '');
  if (!key) return Number.POSITIVE_INFINITY;
  const entry = state.pointsOverview.get(key) || null;
  if (!entry || !entry.ts) return Number.POSITIVE_INFINITY;
  return Math.max(0, Date.now() - Number(entry.ts));
}

export function invalidateCachedPointsOverview(childId) {
  const key = String(childId || '');
  if (!key) return;
  const next = new Map(state.pointsOverview);
  next.delete(key);
  state.pointsOverview = next;
}

export function setCachedOverview(childId, day, payload, weekStart = '') {
  const ws = weekStart || payload?.week_range?.start || getOverviewCacheWeekStart(payload) || '';
  const key = overviewCacheKey(childId, day, ws);
  if (!key) return;
  state.overview.set(key, { payload, ts: Date.now() });
}

export function getCachedOverview(childId, day, weekStart = '') {
  const key = overviewCacheKey(childId, day, weekStart);
  const entry = state.overview.get(key) || null;
  return entry && entry.payload ? entry.payload : null;
}

export function getCachedOverviewAgeMs(childId, day, weekStart = '') {
  const key = overviewCacheKey(childId, day, weekStart);
  const entry = state.overview.get(key) || null;
  if (!entry || !entry.ts) return Number.POSITIVE_INFINITY;
  return Math.max(0, Date.now() - Number(entry.ts));
}

function getOverviewCacheWeekStart(payload) {
  return payload?.week_range?.start || payload?.week_start || '';
}

function overviewCacheKey(childId, day, weekStart = '') {
  const cid = String(childId || '');
  if (!cid) return '';
  return `${cid}:${String(weekStart || '')}:${String(day ?? '')}`;
}

export function invalidateCachedOverview(childId) {
  const prefix = `${String(childId || '')}:`;
  const next = new Map();
  for (const [k, v] of state.overview.entries()) {
    if (!k.startsWith(prefix)) next.set(k, v);
  }
  state.overview = next;
}

/**
 * Keep points/rewards in sync across all cached day snapshots for one child.
 * Task/reward changes affect the whole profile, not just one weekday view.
 */
export function syncChildOverviewSnapshot(childId, snapshot) {
  const cid = String(childId || '');
  if (!cid || !snapshot || typeof snapshot !== 'object') return;

  const prefix = `${cid}:`;
  const shared = {
    points_balance: snapshot.points_balance,
    points_today: snapshot.points_today,
    points_week: snapshot.points_week,
    rewards: snapshot.rewards,
  };

  for (const [key, entry] of state.overview.entries()) {
    if (!key.startsWith(prefix) || !entry?.payload) continue;
    const next = {
      ...entry.payload,
      ...shared,
      rewards: shared.rewards
        ? { ...(entry.payload.rewards || {}), ...shared.rewards }
        : entry.payload.rewards,
    };
    state.overview.set(key, { payload: next, ts: Date.now() });
  }

  const day = new Date().getDay();
  const ws = getOverviewCacheWeekStart(snapshot);
  setCachedOverview(cid, day, snapshot, ws);
}

export function patchChildOverviewSnapshot(childId, patchFn) {
  const cid = String(childId || '');
  if (!cid || typeof patchFn !== 'function') return null;

  const prefix = `${cid}:`;
  let latest = null;

  for (const [key, entry] of state.overview.entries()) {
    if (!key.startsWith(prefix) || !entry?.payload) continue;
    const next = patchFn({ ...entry.payload });
    if (!next) continue;
    state.overview.set(key, { payload: next, ts: Date.now() });
    latest = next;
  }

  if (latest) {
    const ws = getOverviewCacheWeekStart(latest);
    setCachedOverview(cid, new Date().getDay(), latest, ws);
  }

  return latest;
}

/**
 * Prefetch child overview for all weekdays (background, best-effort).
 */
export async function prefetchChildOverviewWeek(childId, api, { weekStart = null, skipDay = null } = {}) {
  const cid = String(childId || '');
  if (!cid || !api?.getChildOverview) return;
  const ws = weekStart || getWeekStartForDate();

  await Promise.allSettled(
    WEEK_DAYS.filter((day) => day !== skipDay).map(async (day) => {
      if (getCachedOverview(cid, day, ws)) return;
      const overview = await api.getChildOverview(cid, day, ws).catch(() => null);
      if (overview) {
        setCachedOverview(cid, day, overview, ws);
        applyWeekendMultiplierFromPayload(overview);
      }
    })
  );
}

export async function bootstrapPreload({ role, childId, todayDay, api, childrenApi, tasksApi, rewardsApi, pointsApi } = {}) {
  // role: 'parent' | 'child'
  const r = String(role || 'child');
  const day = typeof todayDay === 'number' ? todayDay : new Date().getDay();

  if (r === 'parent') {
    const [children, tasks, rewards] = await Promise.all([
      childrenApi?.list ? childrenApi.list().catch(() => null) : Promise.resolve(null),
      tasksApi?.list ? tasksApi.list().catch(() => null) : Promise.resolve(null),
      rewardsApi?.list ? rewardsApi.list().catch(() => null) : Promise.resolve(null),
    ]);

    if (children) setCachedChildren(children);
    if (tasks) setCachedTasks(tasks);
    if (rewards) setCachedRewards(rewards);

    const effectiveChildId =
      String(childId || '') ||
      (Array.isArray(children) && children.length ? String(children[0].id) : '');

    const preloadPromises = [];

    if (effectiveChildId && api?.getChildOverview) {
      const ws = getWeekStartForDate();
      preloadPromises.push(
        api
          .getChildOverview(effectiveChildId, day, ws)
          .then((overview) => {
            if (overview) {
              setCachedOverview(effectiveChildId, day, overview, ws);
              applyWeekendMultiplierFromPayload(overview);
            }
          })
          .catch(() => null)
      );
    }

    // Points overview is used in parent Overview header (weekly points line).
    // Preload it so the first paint doesn't show "–" and then fill later.
    if (effectiveChildId && pointsApi?.overview) {
      preloadPromises.push(
        pointsApi
          .overview(effectiveChildId)
          .then((points) => {
            if (points) setCachedPointsOverview(effectiveChildId, points);
          })
          .catch(() => null)
      );
    }

    if (preloadPromises.length) {
      await Promise.all(preloadPromises);
    }

    if (effectiveChildId) {
      prefetchChildOverviewWeek(effectiveChildId, api, { weekStart: getWeekStartForDate(), skipDay: day });
    }

    return;
  }

  // child role
  if (String(childId || '') && api?.getChildOverview) {
    const cid = String(childId);
    const ws = getWeekStartForDate();
    const overview = await api.getChildOverview(cid, day, ws).catch(() => null);
    if (overview) {
      setCachedOverview(cid, day, overview, ws);
      applyWeekendMultiplierFromPayload(overview);
    }
    prefetchChildOverviewWeek(cid, api, { weekStart: ws, skipDay: day });
  }
}
