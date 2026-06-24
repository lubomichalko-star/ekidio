import { createRouter, createWebHashHistory } from 'vue-router';
import { getToken, clearToken } from './auth/tokenStorage';
import { ensureAuthFromMe, clearStoredAuth, getStoredAuth } from './auth/authState';

const getConfig = () => {
  const cfg = window.rodinneUlohyApp || {};
  const mountEl =
    document.getElementById('rodinne-ulohy-app') || document.getElementById('app');
  const datasetChildIdRaw = mountEl?.dataset?.childId || '';
  const datasetChildId = datasetChildIdRaw && datasetChildIdRaw !== '0' ? datasetChildIdRaw : '';
  const datasetKioskRaw = mountEl?.dataset?.kiosk || '';

  const cfgChildIdRaw = cfg.childId ?? '';
  const cfgChildId =
    cfgChildIdRaw === 0 || cfgChildIdRaw === '0' ? '' : (cfgChildIdRaw || '');

  const childId = datasetChildId || cfgChildId || '';

  let kioskFromLs = false;
  try {
    kioskFromLs = localStorage.getItem('ru_kiosk_enabled') === '1';
  } catch {}
  const kiosk =
    !!cfg.kiosk ||
    kioskFromLs ||
    datasetKioskRaw === '1' ||
    datasetKioskRaw === 'true';

  // NOTE: in unified SPA shortcodes, role is derived from /auth/me and stored locally.
  // We only keep explicit flags from server-side config for backward compatibility.
  const forceChild = !!cfg.forceChild;
  const isParent = !!cfg.isParent && !forceChild;

  return { ...cfg, childId, forceChild, isParent, kiosk };
};

const isParent = () => {
  const stored = getStoredAuth()?.role || '';
  if (stored) return stored === 'parent';
  const cfg = getConfig();
  return !!cfg.isParent;
};

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('./views/HomeView.vue')
  },
  {
    path: '/kiosk',
    name: 'kiosk',
    component: () => import('./views/KioskView.vue')
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('./views/LoginView.vue'),
    meta: { public: true }
  },
  {
    path: '/child/:childId',
    name: 'child',
    component: () => import('./views/ChildView.vue'),
    props: true
  },
  {
    path: '/tasks',
    name: 'tasks',
    component: () => import('./views/TasksView.vue'),
    props: { title: 'Úlohy' },
    meta: { requiresParent: true }
  },
  {
    path: '/family',
    name: 'family',
    component: () => import('./views/ChildrenView.vue'),
    props: { title: 'Rodina' },
    meta: { requiresParent: true }
  },
  {
    path: '/children',
    redirect: { name: 'family' }
  },
  {
    path: '/rewards',
    name: 'rewards',
    component: () => import('./views/RewardsView.vue'),
    props: { title: 'Odmeny' },
    meta: { requiresParent: false }
  },
  {
    path: '/overview',
    name: 'overview',
    component: () => import('./views/OverviewView.vue'),
    props: { title: 'Prehľad' },
    meta: { requiresParent: true }
  },
  {
    path: '/dnes',
    redirect: { name: 'home' }
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('./views/SettingsView.vue'),
    props: { title: 'Nastavenia' },
    meta: { requiresParent: false }
  }
];

const router = createRouter({
  history: createWebHashHistory(),
  routes
});

let isStartupNavigation = true;

async function validateSession({ force = false } = {}) {
  const token = await getToken();
  if (!token) {
    clearStoredAuth();
    return { ok: false };
  }

  try {
    const res = await ensureAuthFromMe({ force });
    if (res?.loggedIn) return { ok: true };
    await clearToken();
    clearStoredAuth();
    return { ok: false };
  } catch (e) {
    await clearToken();
    clearStoredAuth();
    return { ok: false };
  }
}

router.beforeEach((to, from, next) => {
  (async () => {
    const forceAuth = isStartupNavigation;
    isStartupNavigation = false;

    if (to.name === 'login' || (to.meta && to.meta.public)) {
      const session = await validateSession({ force: forceAuth });
      if (session.ok) {
        const cfg = getConfig();
        next(cfg.kiosk ? { name: 'kiosk' } : { name: 'home' });
        return;
      }
      next();
      return;
    }

    const session = await validateSession({ force: forceAuth });
    if (!session.ok) {
      next({ name: 'login' });
      return;
    }

    // Respect explicit forceChild (backward compat), but stored role is source of truth otherwise.
    const cfg = getConfig();
    if (cfg.forceChild && to.meta && to.meta.requiresParent) {
      next({ name: 'home' });
      return;
    }

    // Kiosk mode: lock the app into the kiosk route.
    if (cfg.kiosk && to.name !== 'kiosk') {
      next({ name: 'kiosk' });
      return;
    }

    if (to.meta && to.meta.requiresParent && !isParent()) {
      next({ name: 'home' });
      return;
    }

    next();
  })();
});

export default router;

