import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import './styles/ru-base.css';
import { getInitialApiToken } from './config/appConfig';
import { setToken, getToken } from './auth/tokenStorage';
import { Capacitor } from '@capacitor/core';
import { StatusBar, Style } from '@capacitor/status-bar';

const mountEl = document.getElementById('app') || document.getElementById('rodinne-ulohy-app');

if (mountEl) {
  (async () => {
    // Android/iOS: avoid drawing under the system status bar.
    if (Capacitor.isNativePlatform()) {
      try {
        await StatusBar.setOverlaysWebView({ overlay: false });
      } catch {}
      try {
        await StatusBar.setStyle({ style: Style.Light });
      } catch {}
      // Optional: keep status bar background opaque so the time/icons are readable.
      try {
        await StatusBar.setBackgroundColor({ color: '#ffffff' });
      } catch {}
    }

    const rawChildId = mountEl.dataset.childId || '';
    const normalizedChildId = rawChildId && rawChildId !== '0' ? rawChildId : '';

    // If running inside WP parent view, pre-seed token (Bearer) so Vue uses only API.
    const existing = await getToken();
    if (!existing) {
      const initial = getInitialApiToken();
      if (initial) await setToken(initial);
    }

    const app = createApp(App, {
      role: mountEl.dataset.role || 'child',
      childId: normalizedChildId,
      localized: window.rodinneUlohyApp || {}
    });
    app.use(router);
    app.mount(mountEl);
  })();
}

