<template>
  <section class="ru-kiosk">
    <header class="ru-kiosk__top">
      <div class="ru-kiosk__brand" aria-hidden="true">
        <img class="ru-kiosk__logo" :src="logoUrl" alt="" />
      </div>

      <div class="ru-kiosk__title" aria-label="Dátum">
        {{ dateLabel }}
      </div>

      <div class="ru-kiosk__right">
        <div class="ru-kiosk__controls" v-if="pageCount > 1">
          <button class="ru-kiosk__nav-btn" type="button" @click="prevPage" :disabled="pageIndex <= 0" aria-label="Predchádzajúce deti">
            ‹
          </button>
          <div class="ru-kiosk__dots" aria-label="Strany">
            <button
              v-for="n in pageCount"
              :key="n"
              type="button"
              :class="['ru-kiosk__dot', { active: pageIndex === n - 1 }]"
              @click="goToPage(n - 1)"
              :aria-label="`Strana ${n}`"
            ></button>
          </div>
          <button class="ru-kiosk__nav-btn" type="button" @click="nextPage" :disabled="pageIndex >= pageCount - 1" aria-label="Ďalšie deti">
            ›
          </button>
        </div>

        <button class="ru-kiosk__exit" type="button" @click="showExit = true" aria-label="Vypnúť kiosk režim">
          ×
        </button>
      </div>
    </header>

    <div class="ru-kiosk__body">
      <div class="ru-kiosk__error" v-if="!isParent">
        Prístup len pre rodiča.
      </div>
      <div class="ru-kiosk__error" v-else-if="error">{{ error }}</div>
      <div class="ru-kiosk__loading" v-else-if="loading">Načítavam…</div>

      <div v-else class="ru-kiosk__carousel">
        <div
          class="ru-kiosk__pages"
          ref="swipeEl"
          @touchstart.passive="onTouchStart"
          @touchmove.passive="onTouchMove"
          @touchend="onTouchEnd"
        >
          <div class="ru-kiosk__track" :class="{ dragging: drag.active }" :style="trackStyle">
            <div class="ru-kiosk__page" v-for="(page, pIdx) in pages" :key="`page_${pIdx}`">
              <div class="ru-kiosk__grid">
                <template v-if="isPageActive(pIdx)">
                  <KioskChildColumn
                    v-for="c in page"
                    :key="c.id"
                    :child="c"
                    :day="todayDay"
                  />
                  <div
                    v-for="i in emptyColsForPage(page)"
                    :key="`empty_${pIdx}_${i}`"
                    class="ru-kiosk__col ru-kiosk__col--empty"
                  ></div>
                </template>
                <template v-else>
                  <div
                    v-for="i in perPage"
                    :key="`ph_${pIdx}_${i}`"
                    class="ru-kiosk__col ru-kiosk__col--placeholder"
                  ></div>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <RuModal v-if="showExit" title="Vypnúť kiosk režim" @close="closeExit">
      <div class="ru-section">
        <p class="ru-card__subtitle">Zadaj PIN pre ukončenie kiosk režimu.</p>
        <label class="ru-field">
          <span>PIN</span>
          <input
            v-model="exitPin"
            inputmode="numeric"
            autocomplete="one-time-code"
            type="password"
            maxlength="6"
            placeholder="••••"
            @keyup.enter="confirmExit"
          />
        </label>
        <p class="ru-error" v-if="exitError">{{ exitError }}</p>

        <div class="ru-exit-actions">
          <button class="ru-btn ghost" type="button" @click="closeExit">Zrušiť</button>
          <button class="ru-btn ru-btn--primary" type="button" @click="confirmExit">Vypnúť</button>
        </div>
      </div>
    </RuModal>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { childrenApi } from '../api/children';
import KioskChildColumn from '../components/KioskChildColumn.vue';
import RuModal from '../components/RuModal.vue';
import logoUrl from '../images/logo-gen.png';

const props = defineProps({
  role: { type: String, default: 'child' },
  childId: { type: [String, Number], default: '' },
  localized: { type: Object, default: () => ({}) },
});

const isParent = computed(() => {
  if (props.role === 'parent') return true;
  if (props.role === 'child') return false;
  return !!(props.localized?.isParent && !props.localized?.forceChild);
});

const loading = ref(true);
const error = ref('');
const children = ref([]);

const perPage = 4;
const pageIndex = ref(0);

const pages = computed(() => {
  const list = children.value || [];
  const out = [];
  for (let i = 0; i < list.length; i += perPage) {
    out.push(list.slice(i, i + perPage));
  }
  return out.length ? out : [[]];
});

const pageCount = computed(() => Math.max(1, pages.value.length));

const clampPage = () => {
  const max = Math.max(0, pageCount.value - 1);
  pageIndex.value = Math.max(0, Math.min(max, pageIndex.value));
};
const goToPage = (idx) => {
  pageIndex.value = idx;
  clampPage();
};
const prevPage = () => goToPage(pageIndex.value - 1);
const nextPage = () => goToPage(pageIndex.value + 1);

const emptyColsForPage = (page) => Math.max(0, perPage - (Array.isArray(page) ? page.length : 0));
const isPageActive = (idx) => Number(idx) === Number(pageIndex.value);

const todayDay = ref(new Date().getDay());

const dateLabel = ref('');
const formatDateLabel = () => {
  try {
    const d = new Date();
    // One-line, readable from distance
    dateLabel.value = d.toLocaleDateString('sk-SK', {
      weekday: 'long',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  } catch {
    dateLabel.value = '';
  }
};

let timer = null;
const syncToday = () => {
  const prev = Number(todayDay.value);
  const now = new Date().getDay();
  if (prev !== now) todayDay.value = now;
  formatDateLabel();
};

const showExit = ref(false);
const exitPin = ref('');
const exitError = ref('');

const closeExit = () => {
  showExit.value = false;
  exitPin.value = '';
  exitError.value = '';
};

const sha256Hex = async (input) => {
  const raw = String(input || '');
  const enc = new TextEncoder();
  const bytes = enc.encode(raw);
  const digest = await crypto.subtle.digest('SHA-256', bytes);
  return Array.from(new Uint8Array(digest))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
};

const confirmExit = async () => {
  exitError.value = '';
  const pin = String(exitPin.value || '').trim();
  if (!pin) {
    exitError.value = 'Zadaj PIN';
    return;
  }
  let stored = '';
  try {
    stored = localStorage.getItem('ru_kiosk_pin_hash') || '';
  } catch {}
  if (!stored) {
    exitError.value = 'PIN nie je nastavený';
    return;
  }
  try {
    const h = await sha256Hex(pin);
    if (h !== stored) {
      exitError.value = 'Nesprávny PIN';
      return;
    }
  } catch {
    exitError.value = 'Nepodarilo sa overiť PIN';
    return;
  }

  // disable kiosk mode
  try {
    localStorage.removeItem('ru_kiosk_enabled');
  } catch {}
  closeExit();
  // go back to settings
  try {
    window.location.hash = '#/settings';
  } catch {}
};

// Best-effort: keep tablet screen awake (supported browsers only).
let wakeLock = null;
const tryWakeLock = async () => {
  try {
    if (!('wakeLock' in navigator)) return;
    if (wakeLock) return;
    // Some browsers require user gesture; we just try and ignore failures.
    wakeLock = await navigator.wakeLock.request('screen');
    wakeLock.addEventListener?.('release', () => {
      wakeLock = null;
    });
  } catch {
    wakeLock = null;
  }
};


const loadChildren = async () => {
  if (!isParent.value) {
    loading.value = false;
    children.value = [];
    return;
  }
  loading.value = true;
  error.value = '';
  try {
    const list = await childrenApi.list();
    children.value = Array.isArray(list) ? list : [];
    clampPage();
  } catch (e) {
    children.value = [];
    error.value = e?.message || 'Chyba pri načítaní detí';
  } finally {
    loading.value = false;
  }
};

// Swipe handling (visual drag + snap)
const swipeEl = ref(null);
const containerWidth = ref(0);

const drag = ref({
  startX: 0,
  startY: 0,
  lastX: 0,
  lastY: 0,
  dx: 0,
  active: false,
  locked: false,
  horizontal: false,
});

const updateContainerWidth = () => {
  try {
    const w = swipeEl.value?.getBoundingClientRect?.().width || 0;
    containerWidth.value = Number(w) > 0 ? Number(w) : 0;
  } catch {
    containerWidth.value = 0;
  }
};

const trackStyle = computed(() => {
  const w = Number(containerWidth.value || 0);
  const base = -pageIndex.value * w;
  const dx = drag.value.active ? Number(drag.value.dx || 0) : 0;
  const x = base + dx;
  return {
    transform: `translate3d(${x}px, 0, 0)`,
    transition: drag.value.active ? 'none' : 'transform 220ms ease',
  };
});

const onTouchStart = (e) => {
  const t = e?.touches?.[0];
  if (!t) return;
  updateContainerWidth();
  drag.value = {
    startX: t.clientX,
    startY: t.clientY,
    lastX: t.clientX,
    lastY: t.clientY,
    dx: 0,
    active: true,
    locked: false,
    horizontal: false,
  };
};
const onTouchMove = (e) => {
  if (!drag.value.active) return;
  const t = e?.touches?.[0];
  if (!t) return;
  drag.value.lastX = t.clientX;
  drag.value.lastY = t.clientY;

  const dx = drag.value.lastX - drag.value.startX;
  const dy = drag.value.lastY - drag.value.startY;

  // Lock direction after small movement to avoid fighting vertical scrolling.
  if (!drag.value.locked) {
    if (Math.abs(dx) + Math.abs(dy) < 10) return;
    drag.value.locked = true;
    drag.value.horizontal = Math.abs(dx) > Math.abs(dy) * 1.15;
  }

  if (!drag.value.horizontal) {
    // let vertical scroll happen inside columns; don't move carousel
    drag.value.dx = 0;
    return;
  }

  // Rubber-band at edges
  let nextDx = dx;
  const w = Number(containerWidth.value || 0);
  if (!w) return;

  if (pageIndex.value <= 0 && nextDx > 0) nextDx *= 0.35;
  if (pageIndex.value >= pageCount.value - 1 && nextDx < 0) nextDx *= 0.35;

  // Clamp so it doesn't fly too far
  const max = w * 0.55;
  if (nextDx > max) nextDx = max;
  if (nextDx < -max) nextDx = -max;

  drag.value.dx = nextDx;
};
const onTouchEnd = () => {
  if (!drag.value.active) return;
  const dx = Number(drag.value.dx || 0);
  const horizontal = !!drag.value.horizontal;
  drag.value.active = false;
  drag.value.locked = false;
  drag.value.horizontal = false;

  if (!horizontal) {
    drag.value.dx = 0;
    return;
  }

  const w = Number(containerWidth.value || 0);
  if (!w) {
    drag.value.dx = 0;
    return;
  }

  const threshold = w * 0.18;
  if (dx <= -threshold) nextPage();
  else if (dx >= threshold) prevPage();

  drag.value.dx = 0;
};

const onKeyDown = (e) => {
  if (!pageCount.value || pageCount.value <= 1) return;
  if (e.key === 'ArrowLeft') prevPage();
  if (e.key === 'ArrowRight') nextPage();
};

onMounted(() => {
  syncToday();
  loadChildren();
  tryWakeLock();
  updateContainerWidth();
  try {
    timer = setInterval(syncToday, 60 * 1000);
  } catch {}
  try {
    window.addEventListener('keydown', onKeyDown);
  } catch {}
  try {
    window.addEventListener('resize', updateContainerWidth);
  } catch {}
  try {
    document.addEventListener('visibilitychange', tryWakeLock);
  } catch {}
});

onBeforeUnmount(() => {
  try {
    if (timer) clearInterval(timer);
  } catch {}
  timer = null;
  try {
    window.removeEventListener('keydown', onKeyDown);
  } catch {}
  try {
    window.removeEventListener('resize', updateContainerWidth);
  } catch {}
  try {
    document.removeEventListener('visibilitychange', tryWakeLock);
  } catch {}
  try {
    wakeLock?.release?.();
  } catch {}
  wakeLock = null;
});
</script>

<style scoped>
.ru-kiosk {
  width: 100%;
  max-width: 1600px;
  margin: 0 auto;
  padding: 10px 12px;
}

.ru-kiosk__top {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 14px;
  padding: 8px 12px;
  border-radius: 16px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.ru-kiosk__right {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}

.ru-kiosk__brand {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
.ru-kiosk__logo {
  width: 150px;
  height: auto;
  object-fit: contain;
  display: block;
}

.ru-kiosk__title {
  font-weight: 900;
  font-size: 26px;
  color: #0f172a;
  text-align: center;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ru-kiosk__controls {
  display: inline-flex;
  align-items: center;
  gap: 12px;
}

.ru-kiosk__exit {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.12);
  background: #ffffff;
  color: #0f172a;
  font-size: 28px;
  line-height: 1;
  font-weight: 900;
  cursor: pointer;
  flex-shrink: 0;
}

.ru-kiosk__nav-btn {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.12);
  background: #ffffff;
  color: #0f172a;
  font-size: 28px;
  font-weight: 900;
  cursor: pointer;
}
.ru-kiosk__nav-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.ru-kiosk__dots {
  display: inline-flex;
  gap: 10px;
  align-items: center;
}
.ru-kiosk__dot {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  border: 0;
  background: rgba(15, 23, 42, 0.18);
  cursor: pointer;
}
.ru-kiosk__dot.active {
  background: var(--ru-accent, #0ea5e9);
}

.ru-kiosk__body {
  margin-top: 12px;
}

.ru-kiosk__carousel {
  width: 100%;
}
.ru-kiosk__pages {
  width: 100%;
  overflow: hidden;
  /* allow vertical scroll in columns; horizontal handled by our track */
  touch-action: pan-y;
}
.ru-kiosk__track {
  display: flex;
  width: 100%;
  will-change: transform;
}
.ru-kiosk__track.dragging {
  cursor: grabbing;
}
.ru-kiosk__page {
  flex: 0 0 100%;
  width: 100%;
  box-sizing: border-box;
}

.ru-kiosk__loading,
.ru-kiosk__error {
  padding: 18px 14px;
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  font-weight: 800;
  color: #0f172a;
}
.ru-kiosk__error {
  color: #b91c1c;
}

.ru-kiosk__pages {
  width: 100%;
}

.ru-kiosk__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  align-items: stretch;
}

.ru-kiosk__col--placeholder {
  border: 1px solid rgba(15, 23, 42, 0.06);
  border-radius: 14px;
  background: rgba(15, 23, 42, 0.02);
  min-height: 70vh;
}

.ru-kiosk__col--empty {
  opacity: 0.25;
  border: 2px dashed rgba(15, 23, 42, 0.18);
  border-radius: 14px;
  min-height: 70vh;
}

.ru-kiosk__hint {
  margin-top: 10px;
  text-align: center;
  color: #64748b;
  font-weight: 800;
  font-size: 12px;
}

/* Keep 4 columns on landscape tablet; fallback only on very small screens */
@media (max-width: 640px) {
  .ru-kiosk__grid {
    grid-template-columns: 1fr;
  }
  .ru-kiosk__controls {
    gap: 8px;
  }
  .ru-kiosk__col--empty {
    display: none;
  }
}

.ru-exit-actions {
  margin-top: 14px;
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  flex-wrap: wrap;
}
</style>

