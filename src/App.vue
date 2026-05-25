<template>
  <div class="ru-app" :class="{ 'is-login': isLoginRoute, 'is-kiosk': isKiosk }">
    <header class="ru-app__header" v-if="!isLoginRoute && !isKiosk">
      <div class="ru-app__brand">
        <img :src="logoWhiteUrl" alt="ekidio" />
      </div>
      <nav class="ru-app__nav desktop-only">
        <template v-if="isParent">
          <RouterLink to="/" class="ru-nav__item" @click="onOverviewNavClick">Prehľad</RouterLink>
          <RouterLink to="/family" class="ru-nav__item">Rodina</RouterLink>
          <RouterLink to="/tasks" class="ru-nav__item">Úlohy</RouterLink>
          <RouterLink to="/rewards" class="ru-nav__item">Odmeny</RouterLink>
          <RouterLink to="/settings" class="ru-nav__item">Nastavenia</RouterLink>
        </template>
        <template v-else>
          <RouterLink to="/" class="ru-nav__item">Úlohy</RouterLink>
          <RouterLink to="/rewards" class="ru-nav__item">Odmeny</RouterLink>
          <RouterLink to="/settings" class="ru-nav__item">Nastavenia</RouterLink>
        </template>
      </nav>
      <button class="ru-burger mobile-only" @click="toggleMenu">☰</button>
    </header>

    <transition name="slide">
      <aside class="ru-drawer" v-if="showMenu && !isLoginRoute && !isKiosk">
        <div class="ru-drawer__header">
          <strong>Menu</strong>
          <button class="ru-link" @click="toggleMenu">Zavrieť</button>
        </div>
        <nav class="ru-drawer__nav">
          <template v-if="isParent">
            <RouterLink to="/" class="ru-nav__item" @click="(e) => { onOverviewNavClick(e); closeMenu(); }">Prehľad</RouterLink>
            <RouterLink to="/family" class="ru-nav__item" @click="closeMenu">Rodina</RouterLink>
            <RouterLink to="/tasks" class="ru-nav__item" @click="closeMenu">Úlohy</RouterLink>
            <RouterLink to="/rewards" class="ru-nav__item" @click="closeMenu">Odmeny</RouterLink>
            <RouterLink to="/settings" class="ru-nav__item" @click="closeMenu">Nastavenia</RouterLink>
          </template>
          <template v-else>
            <RouterLink to="/" class="ru-nav__item" @click="closeMenu">Úlohy</RouterLink>
            <RouterLink to="/rewards" class="ru-nav__item" @click="closeMenu">Odmeny</RouterLink>
            <RouterLink to="/settings" class="ru-nav__item" @click="closeMenu">Nastavenia</RouterLink>
          </template>
        </nav>
      </aside>
    </transition>
    <transition name="fade">
      <div class="ru-backdrop" v-if="showMenu && !isLoginRoute && !isKiosk" @click="closeMenu"></div>
    </transition>

    <main class="ru-app__main">
      <div v-if="!isLoginRoute && (!authResolved || !bootstrapped)" class="ru-app__loading">
        <img class="ru-app__loading-logo" :src="logoGenUrl" alt="ekidio" />
        <div class="ru-app__spinner" role="status" aria-label="Načítavam"></div>
      </div>
      <RouterView v-else v-slot="{ Component }">
        <KeepAlive include="OverviewView,ChildrenView,TasksView,RewardsView,SettingsView">
          <component
            :is="Component"
            :role="isParent ? 'parent' : 'child'"
            :child-id="childId"
            :localized="localized"
          />
        </KeepAlive>
      </RouterView>
    </main>

    <FeedbackFab v-if="!isLoginRoute && !isKiosk" />

    <!-- Mobile bottom navigation (thumb-friendly) -->
    <nav class="ru-bottom-nav" aria-label="Hlavná navigácia" v-if="!isLoginRoute && !isKiosk">
      <template v-if="isParent">
        <RouterLink to="/" class="ru-tab" @click="onOverviewNavClick">
          <span class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M3 10.5L12 3l9 7.5" />
              <path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10" />
            </svg>
          </span>
          <span class="ru-tab__label">Prehľad</span>
        </RouterLink>
        <RouterLink to="/family" class="ru-tab">
          <span class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </span>
          <span class="ru-tab__label">Rodina</span>
        </RouterLink>
        <RouterLink to="/tasks" class="ru-tab">
          <span class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M9 11l3 3 7-7" />
              <path d="M21 12a9 9 0 1 1-5.25-8.16" />
            </svg>
          </span>
          <span class="ru-tab__label">Úlohy</span>
        </RouterLink>
        <RouterLink to="/rewards" class="ru-tab">
          <span class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M20 12v10H4V12" />
              <path d="M2 7h20v5H2z" />
              <path d="M12 22V7" />
              <path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Z" />
              <path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" />
            </svg>
          </span>
          <span class="ru-tab__label">Odmeny</span>
        </RouterLink>
        <RouterLink to="/settings" class="ru-tab">
          <span class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path
                d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"
              />
              <path
                d="M19.4 15a7.9 7.9 0 0 0 .06-1 7.9 7.9 0 0 0-.06-1l2.1-1.64a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.48 1a8.03 8.03 0 0 0-1.73-1l-.38-2.65A.5.5 0 0 0 12.93 2h-3.86a.5.5 0 0 0-.5.42l-.38 2.65a8.03 8.03 0 0 0-1.73 1l-2.48-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64L3.6 13a7.9 7.9 0 0 0-.06 1 7.9 7.9 0 0 0 .06 1L1.5 16.64a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.48-1a8.03 8.03 0 0 0 1.73 1l.38 2.65a.5.5 0 0 0 .5.42h3.86a.5.5 0 0 0 .5-.42l.38-2.65a8.03 8.03 0 0 0 1.73-1l2.48 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64L19.4 15Z"
              />
            </svg>
          </span>
          <span class="ru-tab__label">Nastavenia</span>
        </RouterLink>
      </template>
      <template v-else>
        <RouterLink to="/" class="ru-tab">
          <span class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M9 11l3 3 7-7" />
              <path d="M21 12a9 9 0 1 1-5.25-8.16" />
            </svg>
          </span>
          <span class="ru-tab__label">Úlohy</span>
        </RouterLink>
        <RouterLink to="/rewards" class="ru-tab">
          <span class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path d="M20 12v10H4V12" />
              <path d="M2 7h20v5H2z" />
              <path d="M12 22V7" />
              <path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Z" />
              <path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" />
            </svg>
          </span>
          <span class="ru-tab__label">Odmeny</span>
        </RouterLink>
        <RouterLink to="/settings" class="ru-tab">
          <span class="ru-tab__icon" aria-hidden="true">
            <svg class="ru-tab__svg" viewBox="0 0 24 24" fill="none">
              <path
                d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"
              />
              <path
                d="M19.4 15a7.9 7.9 0 0 0 .06-1 7.9 7.9 0 0 0-.06-1l2.1-1.64a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.48 1a8.03 8.03 0 0 0-1.73-1l-.38-2.65A.5.5 0 0 0 12.93 2h-3.86a.5.5 0 0 0-.5.42l-.38 2.65a8.03 8.03 0 0 0-1.73 1l-2.48-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64L3.6 13a7.9 7.9 0 0 0-.06 1 7.9 7.9 0 0 0 .06 1L1.5 16.64a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.48-1a8.03 8.03 0 0 0 1.73 1l.38 2.65a.5.5 0 0 0 .5.42h3.86a.5.5 0 0 0 .5-.42l.38-2.65a8.03 8.03 0 0 0 1.73-1l2.48 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64L19.4 15Z"
              />
            </svg>
          </span>
          <span class="ru-tab__label">Nastavenia</span>
        </RouterLink>
      </template>
    </nav>
  </div>
</template>

<script setup>
import { computed, ref, onBeforeUnmount, onMounted, KeepAlive } from 'vue';
import { useRoute, RouterView, RouterLink } from 'vue-router';
import logoWhiteUrl from './images/logo-white.png';
import logoGenUrl from './images/logo-gen.png';
import FeedbackFab from './components/FeedbackFab.vue';
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
const PARENT_ACCENT = '#5abb6f';
const runtimeRole = ref(props.role || 'child');
const runtimeChildId = ref('');
const authResolved = ref(false);
const bootstrapped = ref(false);

const childId = computed(() => {
  const v = props.childId || route.params.childId || '';
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
let offAuthBootstrap = null;
let offDataBootstrap = null;

onBeforeUnmount(() => {
  try { offAuth?.(); } catch {}
  try { offAuthBootstrap?.(); } catch {}
  try { offDataBootstrap?.(); } catch {}
});

onMounted(() => {
  const { role: storedRole, childId: storedChild } = getStoredAuth();
  if (storedRole) runtimeRole.value = storedRole;
  if (storedChild) runtimeChildId.value = storedChild;

  // React to login/logout without refresh
  const onAuthChanged = (e) => {
    const detail = e?.detail || {};
    if (detail.role) runtimeRole.value = detail.role;
    runtimeChildId.value = detail.childId || '';
  };
  offAuth = onRuAuthChanged(onAuthChanged);

  // Resolve role from token so menu can't drift.
  (async () => {
    try {
      const token = await getToken();
      if (!token) {
        clearStoredAuth();
        authResolved.value = true;
        bootstrapped.value = true;
        return;
      }
      const res = await ensureAuthFromMe({ force: false });
      if (res?.loggedIn) {
        runtimeRole.value = res.role || 'child';
        runtimeChildId.value = res.childId || '';
      } else {
        runtimeRole.value = 'child';
        runtimeChildId.value = '';
      }
    } catch (e) {
      clearStoredAuth();
      runtimeRole.value = 'child';
      runtimeChildId.value = '';
    } finally {
      authResolved.value = true;
    }
  })();

  const runBootstrap = async () => {
    if (isLoginRoute.value) {
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

  // Clear preloaded data when auth or data changes (views will refetch as needed)
  const onAuthChangedBootstrap = () => {
    clearPreloadCache();
    runBootstrap();
  };
  const onDataChangedBootstrap = () => {
    clearPreloadCache();
    // Make parent overview refetch on next open (KeepAlive keeps component state).
    try {
      localStorage.setItem('ru_overview_stale', '1');
    } catch {}
  };
  offAuthBootstrap = onRuAuthChanged(onAuthChangedBootstrap);
  offDataBootstrap = onRuDataChanged(onDataChangedBootstrap);

  if (isParent.value) {
    applyAccent(PARENT_ACCENT);
  }
});
</script>

<style scoped>
.ru-app {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #1f2937;
  min-height: 100vh;
  background: #f3f4f6;
  display: flex;
  flex-direction: column;
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

.ru-app__loading-logo {
  width: 160px;
  height: auto;
  display: block;
  filter: drop-shadow(0 6px 18px rgba(15, 23, 42, 0.12));
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
  /* Kiosk: no bottom tabs, use full height */
  .ru-app.is-kiosk:not(.is-login) .ru-app__main {
    padding-top: max(12px, env(safe-area-inset-top, 12px));
    padding-bottom: env(safe-area-inset-bottom, 0px);
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

