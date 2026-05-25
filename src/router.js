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

router.beforeEach((to, from, next) => {
  (async () => {
    // Always allow login route
    if (to.name === 'login' || (to.meta && to.meta.public)) {
      const token = await getToken();
      if (token) {
        // already logged in -> go home
        // kiosk mode: force kiosk route even after login
        const cfg = getConfig();
        next(cfg.kiosk ? { name: 'kiosk' } : { name: 'home' });
        return;
      }
      next();
      return;
    }

    const token = await getToken();
    if (!token) {
      next({ name: 'login' });
      return;
    }

    try {
      // Resolve role from /auth/me (cached for a short time to prevent slowing down every navigation)
      await ensureAuthFromMe({ force: false });
    } catch (e) {
      // token invalid -> clear and go login
      await clearToken();
      clearStoredAuth();
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

