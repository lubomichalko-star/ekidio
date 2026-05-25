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

export function setCachedOverview(childId, day, payload) {
  const key = `${String(childId || '')}:${String(day ?? '')}`;
  if (!key) return;
  state.overview.set(key, { payload, ts: Date.now() });
}

export function getCachedOverview(childId, day) {
  const key = `${String(childId || '')}:${String(day ?? '')}`;
  const entry = state.overview.get(key) || null;
  return entry && entry.payload ? entry.payload : null;
}

export function getCachedOverviewAgeMs(childId, day) {
  const key = `${String(childId || '')}:${String(day ?? '')}`;
  const entry = state.overview.get(key) || null;
  if (!entry || !entry.ts) return Number.POSITIVE_INFINITY;
  return Math.max(0, Date.now() - Number(entry.ts));
}

export function invalidateCachedOverview(childId) {
  const prefix = `${String(childId || '')}:`;
  const next = new Map();
  for (const [k, v] of state.overview.entries()) {
    if (!k.startsWith(prefix)) next.set(k, v);
  }
  state.overview = next;
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
      preloadPromises.push(
        api
          .getChildOverview(effectiveChildId, day)
          .then((overview) => {
            if (overview) setCachedOverview(effectiveChildId, day, overview);
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

    return;
  }

  // child role
  if (String(childId || '') && api?.getChildOverview) {
    const overview = await api.getChildOverview(String(childId), day).catch(() => null);
    if (overview) setCachedOverview(String(childId), day, overview);
  }
}
