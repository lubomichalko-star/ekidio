<template>
  <div
    class="ru-app notranslate"
    translate="no"
    :class="[`ru-theme-${visualTheme}`, { 'is-login': isLoginLike, 'is-kiosk': isKiosk }]"
  >
    <header class="ru-app__header" v-if="showAppShell">
      <div class="ru-app__brand">
        <img v-if="isStanoTheme" :src="logoDarkUrl" alt="ekidio" />
        <img v-else :src="logoWhiteUrl" alt="ekidio" />
      </div>
      <nav class="ru-app__nav desktop-only">
        <template v-if="isParent">
          <RouterLink to="/" class="ru-nav__item" @click="onOverviewNavClick">{{ t('nav.overview') }}</RouterLink>
          <RouterLink to="/family" class="ru-nav__item">{{ t('nav.family') }}</RouterLink>
          <RouterLink to="/tasks" class="ru-nav__item">{{ t('nav.tasks') }}</RouterLink>
          <RouterLink to="/rewards" class="ru-nav__item">{{ t('nav.rewards') }}</RouterLink>
          <template v-if="isStanoTheme">
            <img class="ru-nav__divider" alt="" src="./images/Line.svg" />
            <RouterLink to="/settings" class="ru-nav__item ru-nav__item-icon">
              <img alt="" src="./images/settings.svg" />
            </RouterLink>
          </template>
          <RouterLink v-else to="/settings" class="ru-nav__item">{{ t('nav.settings') }}</RouterLink>
        </template>
        <template v-else>
          <RouterLink to="/" class="ru-nav__item">{{ t('nav.tasks') }}</RouterLink>
          <RouterLink to="/rewards" class="ru-nav__item">{{ t('nav.rewards') }}</RouterLink>
          <template v-if="isStanoTheme">
            <img class="ru-nav__divider" alt="" src="./images/Line.svg" />
            <RouterLink to="/settings" class="ru-nav__item ru-nav__item-icon">
              <img alt="" src="./images/settings.svg" />
            </RouterLink>
          </template>
          <RouterLink v-else to="/settings" class="ru-nav__item">{{ t('nav.settings') }}</RouterLink>
        </template>
      </nav>
      <button class="ru-burger mobile-only" @click="toggleMenu">☰</button>
    </header>

    <transition name="slide">
      <aside class="ru-drawer" v-if="showMenu && showAppShell">
        <div class="ru-drawer__header">
          <strong>Menu</strong>
          <button class="ru-link" @click="toggleMenu">Zavrieť</button>
        </div>
        <nav class="ru-drawer__nav">
          <template v-if="isParent">
            <RouterLink to="/" class="ru-nav__item" @click="(e) => { onOverviewNavClick(e); closeMenu(); }">{{ t('nav.overview') }}</RouterLink>
            <RouterLink to="/family" class="ru-nav__item" @click="closeMenu">{{ t('nav.family') }}</RouterLink>
            <RouterLink to="/tasks" class="ru-nav__item" @click="closeMenu">{{ t('nav.tasks') }}</RouterLink>
            <RouterLink to="/rewards" class="ru-nav__item" @click="closeMenu">{{ t('nav.rewards') }}</RouterLink>
            <RouterLink to="/settings" class="ru-nav__item" @click="closeMenu">{{ t('nav.settings') }}</RouterLink>
          </template>
          <template v-else>
            <RouterLink to="/" class="ru-nav__item" @click="closeMenu">{{ t('nav.tasks') }}</RouterLink>
            <RouterLink to="/rewards" class="ru-nav__item" @click="closeMenu">{{ t('nav.rewards') }}</RouterLink>
            <RouterLink to="/settings" class="ru-nav__item" @click="closeMenu">{{ t('nav.settings') }}</RouterLink>
          </template>
        </nav>
      </aside>
    </transition>
    <transition name="fade">
      <div class="ru-backdrop" v-if="showMenu && showAppShell" @click="closeMenu"></div>
    </transition>

    <main class="ru-app__main">
      <div v-if="showAppShell && isStanoTheme" class="ru-app__hero">
        <div class="ru-app__title-wrap">
          <h1 class="ru-app__title">{{ pageTitle }}</h1>
        </div>
      </div>
      <div class="ru-app__content-wrap">
      <AppUpdateBanner v-if="showAppShell" />
      <RouterView v-slot="{ Component }">
        <KeepAlive include="OverviewView,ChildrenView,TasksView,RewardsView,SettingsView">
          <component
            :is="Component || RoutePlaceholder"
            :role="viewRole"
            :child-id="childId"
            :localized="localized"
            :app-ready="appReady"
          />
        </KeepAlive>
      </RouterView>
      <div v-if="showSplash" class="ru-app__loading ru-app__loading--overlay">
        <BrandLogo variant="green" size="lg" />
        <div class="ru-app__spinner" role="status" :aria-label="t('common.loading')"></div>
      </div>
      </div>
    </main>

    <FeedbackFab v-if="showAppShell" />

    <!-- Mobile bottom navigation (thumb-friendly) -->
    <nav class="ru-bottom-nav" aria-label="Hlavná navigácia" v-if="showAppShell">
      <template v-if="isParent">
        <RouterLink to="/" class="ru-tab" @click="onOverviewNavClick">
          <img v-if="isStanoTheme" class="ru-tab__img" src="./images/eki-home.svg" alt="" />
          <span v-else class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M3 10.5L12 3l9 7.5" />
              <path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10" />
            </svg>
          </span>
          <span class="ru-tab__label">{{ t('nav.overview') }}</span>
        </RouterLink>
        <RouterLink to="/family" class="ru-tab">
          <img v-if="isStanoTheme" class="ru-tab__img" src="./images/eki-group.svg" alt="" />
          <span v-else class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </span>
          <span class="ru-tab__label">{{ t('nav.family') }}</span>
        </RouterLink>
        <RouterLink to="/tasks" class="ru-tab">
          <img v-if="isStanoTheme" class="ru-tab__img" src="./images/eki-tasks.svg" alt="" />
          <span v-else class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M9 11l3 3 7-7" />
              <path d="M21 12a9 9 0 1 1-5.25-8.16" />
            </svg>
          </span>
          <span class="ru-tab__label">{{ t('nav.tasks') }}</span>
        </RouterLink>
        <RouterLink to="/rewards" class="ru-tab">
          <img v-if="isStanoTheme" class="ru-tab__img" src="./images/eki-gift.svg" alt="" />
          <span v-else class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M20 12v10H4V12" />
              <path d="M2 7h20v5H2z" />
              <path d="M12 22V7" />
              <path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Z" />
              <path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" />
            </svg>
          </span>
          <span class="ru-tab__label">{{ t('nav.rewards') }}</span>
        </RouterLink>
        <RouterLink to="/settings" class="ru-tab">
          <img v-if="isStanoTheme" class="ru-tab__img" src="./images/eki-settings.svg" alt="" />
          <span v-else class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
              <path d="M19.4 15a7.9 7.9 0 0 0 .06-1 7.9 7.9 0 0 0-.06-1l2.1-1.64a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.48 1a8.03 8.03 0 0 0-1.73-1l-.38-2.65A.5.5 0 0 0 12.93 2h-3.86a.5.5 0 0 0-.5.42l-.38 2.65a8.03 8.03 0 0 0-1.73 1l-2.48-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64L3.6 13a7.9 7.9 0 0 0-.06 1 7.9 7.9 0 0 0 .06 1L1.5 16.64a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.48-1a8.03 8.03 0 0 0 1.73 1l.38 2.65a.5.5 0 0 0 .5.42h3.86a.5.5 0 0 0 .5-.42l.38-2.65a8.03 8.03 0 0 0 1.73-1l2.48 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64L19.4 15Z" />
            </svg>
          </span>
          <span class="ru-tab__label">{{ t('nav.settings') }}</span>
        </RouterLink>
      </template>
      <template v-else>
        <RouterLink to="/" class="ru-tab">
          <img v-if="isStanoTheme" class="ru-tab__img" src="./images/eki-tasks.svg" alt="" />
          <span v-else class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M9 11l3 3 7-7" />
              <path d="M21 12a9 9 0 1 1-5.25-8.16" />
            </svg>
          </span>
          <span class="ru-tab__label">{{ t('nav.tasks') }}</span>
        </RouterLink>
        <RouterLink to="/rewards" class="ru-tab">
          <img v-if="isStanoTheme" class="ru-tab__img" src="./images/eki-gift.svg" alt="" />
          <span v-else class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M20 12v10H4V12" />
              <path d="M2 7h20v5H2z" />
              <path d="M12 22V7" />
              <path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Z" />
              <path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" />
            </svg>
          </span>
          <span class="ru-tab__label">{{ t('nav.rewards') }}</span>
        </RouterLink>
        <RouterLink to="/settings" class="ru-tab">
          <img v-if="isStanoTheme" class="ru-tab__img" src="./images/eki-settings.svg" alt="" />
          <span v-else class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
              <path d="M19.4 15a7.9 7.9 0 0 0 .06-1 7.9 7.9 0 0 0-.06-1l2.1-1.64a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.48 1a8.03 8.03 0 0 0-1.73-1l-.38-2.65A.5.5 0 0 0 12.93 2h-3.86a.5.5 0 0 0-.5.42l-.38 2.65a8.03 8.03 0 0 0-1.73 1l-2.48-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64L3.6 13a7.9 7.9 0 0 0-.06 1 7.9 7.9 0 0 0 .06 1L1.5 16.64a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.48-1a8.03 8.03 0 0 0 1.73 1l.38 2.65a.5.5 0 0 0 .5.42h3.86a.5.5 0 0 0 .5-.42l.38-2.65a8.03 8.03 0 0 0 1.73-1l2.48 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64L19.4 15Z" />
            </svg>
          </span>
          <span class="ru-tab__label">{{ t('nav.settings') }}</span>
        </RouterLink>
      </template>
    </nav>
  </div>
</template>

<script setup>
import { computed, ref, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import logoWhiteUrl from './images/logo-white.png';
import logoDarkUrl from './images/logo-gen.svg';
import BrandLogo from './components/BrandLogo.vue';
import FeedbackFab from './components/FeedbackFab.vue';
import AppUpdateBanner from './components/AppUpdateBanner.vue';
import RoutePlaceholder from './components/RoutePlaceholder.vue';
import { getVisualTheme } from './config/visualTheme';
import { t } from './i18n';
import { getToken } from './auth/tokenStorage';
import { api } from './api/client';
import { ensureAuthFromMe, getStoredAuth, clearStoredAuth } from './auth/authState';
import { bootstrapPreload, clearPreloadCache } from './state/preloadCache';
import { pointsApi } from './api/points';
import { childrenApi } from './api/children';
import { tasksApi } from './api/tasks';
import { rewardsApi } from './api/rewards';
import { emitRuForceOverviewRefresh, onRuAuthChanged, onRuDataChanged } from './events/ruEvents';

const props = defineProps({
  role: {
    type: String,
    default: 'child'
  },
  childId: {
    type: [String, Number],
    default: ''
  },
  localized: {
    type: Object,
    default: () => ({})
  }
});

const route = useRoute();
const router = useRouter();
const visualTheme = getVisualTheme();
const isStanoTheme = computed(() => visualTheme === 'stano');
const PARENT_ACCENT = '#5abb6f';
const storedAuth = getStoredAuth();
const runtimeRole = ref(storedAuth.role || props.role || 'child');
const runtimeChildId = ref(storedAuth.childId || '');
const authResolved = ref(false);
const isAuthenticated = ref(false);
const bootstrapped = ref(false);

const childId = computed(() => {
  const v = props.childId || route?.params?.childId || '';
  const raw = (v === 0 || v === '0' ? '' : v) || runtimeChildId.value || '';
  return raw === 0 || raw === '0' ? '' : raw;
});
const localized = computed(() => props.localized || {});
const isParent = computed(() => {
  const stored = getStoredAuth()?.role || '';
  // If we have explicit stored role, trust it.
  if (stored === 'parent') return true;
  if (stored === 'child') return false;

  // Until auth is resolved from token, default to child (safer).
  if (!authResolved.value) return false;

  const role = runtimeRole.value || props.role;
  if (role === 'parent') return true;
  if (role === 'child') return false;

  // Fallback only when WP embed explicitly sets it.
  if (localized.value && localized.value.isParent && !localized.value.forceChild) return true;
  return false;
});
const isLoginRoute = computed(() => route.name === 'login');
const viewRole = computed(() => {
  if (!isAuthenticated.value) return props.role || 'child';
  return isParent.value ? 'parent' : 'child';
});
const showSplash = computed(() => {
  // Login must stay interactive while auth/chunks load (async route components).
  if (isLoginRoute.value) return false;
  if (!authResolved.value) return true;
  if (!isAuthenticated.value) return true;
  if (!bootstrapped.value) return true;
  return false;
});
const isLoginLike = computed(() => isLoginRoute.value || !authResolved.value);
const showAppShell = computed(() =>
  authResolved.value &&
  isAuthenticated.value &&
  bootstrapped.value &&
  !isLoginRoute.value &&
  !isKiosk.value
);
const appReady = computed(() => authResolved.value && bootstrapped.value);
const isKiosk = computed(() => {
  const cfg = localized.value || {};
  return !!cfg.kiosk || route.name === 'kiosk';
});

const showMenu = ref(false);
const toggleMenu = () => {
  showMenu.value = !showMenu.value;
};
const closeMenu = () => {
  showMenu.value = false;
};

const onOverviewNavClick = () => {
  // If user clicks "Prehľad" while already on it, Vue Router may not re-navigate.
  // Force a background revalidation anyway.
  try {
    const isAlreadyOverview = route.name === 'home' || route.name === 'overview';
    if (isAlreadyOverview) {
      localStorage.setItem('ru_overview_stale', '1');
      emitRuForceOverviewRefresh();
    }
  } catch {}
};

const applyAccent = (val) => {
  if (!val) return;
  document.documentElement.style.setProperty('--ru-accent', val);
  document.documentElement.style.setProperty('--ru-accent-light', `${val}33`);
};

let offAuth = null;
let offDataBootstrap = null;

onBeforeUnmount(() => {
  try { offAuth?.(); } catch {}
  try { offDataBootstrap?.(); } catch {}
});

onMounted(() => {
  // React to login/logout without refresh
  const onAuthChanged = async (e) => {
    const detail = e?.detail || {};
    const token = await getToken();
    const loggedIn = !!(token && (detail.role === 'parent' || detail.role === 'child'));

    if (!loggedIn && route.name !== 'login') {
      await router.replace({ name: 'login' });
    }

    if (loggedIn) {
      isAuthenticated.value = true;
      runtimeRole.value = detail.role;
      runtimeChildId.value = detail.childId || '';
    } else {
      isAuthenticated.value = false;
      runtimeRole.value = 'child';
      runtimeChildId.value = '';
      bootstrapped.value = true;
    }
    authResolved.value = true;

    if (isAuthenticated.value && isParent.value) {
      applyAccent(PARENT_ACCENT);
    }

    clearPreloadCache();
    if (isAuthenticated.value) {
      try {
        await router.isReady();
      } catch {}
      await runBootstrap();
    }
  };
  offAuth = onRuAuthChanged(onAuthChanged);

  // Resolve role from token so menu can't drift.
  (async () => {
    try {
      const token = await getToken();
      if (!token) {
        clearStoredAuth();
        isAuthenticated.value = false;
        authResolved.value = true;
        bootstrapped.value = true;
        if (route.name !== 'login') {
          await router.replace({ name: 'login' });
        }
        return;
      }
      const res = await ensureAuthFromMe({ force: false });
      if (res?.loggedIn) {
        isAuthenticated.value = true;
        runtimeRole.value = res.role || 'child';
        runtimeChildId.value = res.childId || '';
      } else {
        isAuthenticated.value = false;
        runtimeRole.value = 'child';
        runtimeChildId.value = '';
        if (route.name !== 'login') {
          await router.replace({ name: 'login' });
        }
      }
    } catch (e) {
      clearStoredAuth();
      isAuthenticated.value = false;
      runtimeRole.value = 'child';
      runtimeChildId.value = '';
      if (route.name !== 'login') {
        await router.replace({ name: 'login' });
      }
    } finally {
      authResolved.value = true;
    }
  })();

  const runBootstrap = async () => {
    if (!isAuthenticated.value || isLoginRoute.value) {
      bootstrapped.value = true;
      return;
    }
    bootstrapped.value = false;
    try {
      await bootstrapPreload({
        role: isParent.value ? 'parent' : 'child',
        childId: childId.value || '',
        todayDay: new Date().getDay(),
        api,
        pointsApi,
        childrenApi,
        tasksApi,
        rewardsApi,
      });
    } catch {
      // ignore preload errors (views will fallback to their own loading)
    } finally {
      bootstrapped.value = true;
    }
  };

  const waitAuthThenBootstrap = async () => {
    try {
      while (!authResolved.value) {
        await new Promise((r) => setTimeout(r, 10));
      }
      await runBootstrap();
    } catch {
      bootstrapped.value = true;
    }
  };
  waitAuthThenBootstrap();

  const onDataChangedBootstrap = (e) => {
    const type = String(e?.detail?.type || '');
    const fullResetTypes = new Set([
      'child_changed',
      'tasks_imported',
      'rewards_imported',
      'reward_changed',
      'task_deleted',
      'reset_week',
      'reset_points',
      'shift_rotation',
    ]);

    if (fullResetTypes.has(type)) {
      clearPreloadCache();
    }

    // Parent overview should revalidate after child-side task/reward changes.
    if (
      type === 'task_status_changed' ||
      type === 'reward_purchased' ||
      type === 'reward_used' ||
      fullResetTypes.has(type)
    ) {
      try {
        localStorage.setItem('ru_overview_stale', '1');
      } catch {}
    }
  };
  offDataBootstrap = onRuDataChanged(onDataChangedBootstrap);

  if (isParent.value) {
    applyAccent(PARENT_ACCENT);
  }
});

watch(
  isLoginLike,
  (loginLike) => {
    if (loginLike) applyAccent(PARENT_ACCENT);
  },
  { immediate: true }
);

const pageTitle = computed(() => {
  switch (route.name) {
    case 'overview':
    case 'home':
      return t('nav.overview');
    case 'family':
      return t('nav.family');
    case 'tasks':
      return t('nav.tasks');
    case 'rewards':
      return t('nav.rewards');
    case 'settings':
      return t('nav.settings');
    default:
      return t('nav.overview');
  }
});
</script>

<style scoped>
.ru-app {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #1f2937;
  min-height: 100vh;
  min-height: 100dvh;
  background: #f3f4f6;
  display: flex;
  flex-direction: column;
}

/* App shell: lock to viewport, scroll only inside main */
.ru-app:not(.is-login) {
  height: 100dvh;
  max-height: 100dvh;
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}

.ru-app__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: var(--ru-accent, #0ea5e9);
  color: white;
  font-weight: 700;
  letter-spacing: 0.3px;
  gap: 16px;
  position: sticky;
  top: 0;
  z-index: 1000;
}
.ru-app__brand img {
  height: 40px;
  width: auto;
  display: block;
}

.ru-app__main {
  padding: 0;
  flex: 1;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
  position: relative;
}

.ru-app__loading {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 24px 14px;
  max-width: 760px;
  margin: 0 auto;
  color: #475569;
  font-weight: 800;
}

.ru-app__loading--overlay {
  position: absolute;
  inset: 0;
  z-index: 2000;
  min-height: 100%;
  max-width: none;
  margin: 0;
  background: #f3f4f6;
}

.ru-app.is-login .ru-app__loading {
  min-height: calc(100dvh - env(safe-area-inset-top, 0px));
}

.ru-app__spinner {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 4px solid rgba(148, 163, 184, 0.35);
  border-top-color: var(--ru-accent, #0ea5e9);
  animation: ru-spin 0.9s linear infinite;
}

@keyframes ru-spin {
  to {
    transform: rotate(360deg);
  }
}

/* Desktop: add breathing room below top header */
.ru-app__header + .ru-app__main {
  padding-top: 14px;
}

.ru-app__spacer {
  flex-shrink: 0;
}

.ru-app__nav {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.ru-nav__item {
  padding: 8px 12px;
  background: rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  color: white;
  text-decoration: none;
  font-size: 13px;
  transition: background 0.2s, transform 0.2s;
}

.ru-nav__item.router-link-active {
  background: white;
  color: var(--ru-accent, #0ea5e9);
  font-weight: 800;
  transform: translateY(-1px);
}

.ru-burger {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.4);
  color: white;
  padding: 8px 10px;
  border-radius: 10px;
  font-size: 18px;
  display: none;
}

.mobile-only {
  display: none;
}
.desktop-only {
  display: flex;
}

@media (max-width: 768px) {
  .desktop-only {
    display: none;
  }
  .mobile-only {
    display: inline-flex;
  }
  /* Mobile app: no top header (use bottom tabs). */
  .ru-app__header {
    display: none;
  }
  .ru-app__header {
    padding: 12px 14px;
  }
  .ru-app__brand img {
    height: 34px;
  }
  /* Leave room for bottom tabs + safe area */
  .ru-app:not(.is-login) .ru-app__main {
    /* No forced top gap on Android; respect notch inset only when present. */
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: calc(64px + env(safe-area-inset-bottom, 0px));
  }
  /* Kiosk: no bottom tabs; header handles top safe area */
  .ru-app.is-kiosk:not(.is-login) .ru-app__main {
    padding-top: 0;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    display: flex;
    flex-direction: column;
    min-height: 0;
  }

  .ru-app.is-kiosk:not(.is-login) .ru-app__main > * {
    flex: 1;
    min-height: 0;
  }
  /* Login page uses its own full-screen layout, BUT respect top inset */
  .ru-app.is-login .ru-app__main {
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: 0;
  }
}

.ru-drawer {
  position: fixed;
  top: 0;
  right: 0;
  width: 260px;
  height: 100vh;
  background: white;
  box-shadow: -6px 0 20px -12px rgba(15, 23, 42, 0.3);
  padding: 16px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ru-drawer__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.ru-drawer__nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ru-drawer__nav .ru-nav__item,
.ru-drawer__nav a {
  color: var(--ru-accent, #0ea5e9);
  background: var(--ru-accent-light, #f0f9ff);
}
.slide-enter-active, .slide-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.slide-enter-from, .slide-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

.ru-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(2, 6, 23, 0.45);
  z-index: 9998;
}
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.18s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

.ru-bottom-nav {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1200;
  display: none;
  gap: 2px;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(10px);
  border-top: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.10);
  padding: 8px 8px calc(8px + env(safe-area-inset-bottom, 0px));
}

.ru-tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 8px 6px;
  border-radius: 14px;
  text-decoration: none;
  /* Inactive: minimalist single-color grey. Active uses theme accent. */
  color: #9ca3af;
  font-weight: 700;
  min-height: 48px;
}
.ru-tab__icon {
  width: 22px;
  height: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.ru-tab__svg {
  width: 22px;
  height: 22px;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.ru-tab__label {
  font-size: 11px;
  line-height: 1;
}
.ru-tab.router-link-active {
  color: var(--ru-accent, #0ea5e9);
  background: var(--ru-accent-light, #e0f2fe);
}

@media (max-width: 768px) {
  .ru-bottom-nav {
    display: flex;
  }
}
</style>

