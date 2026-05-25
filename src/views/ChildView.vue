<template>
  <section
    class="ru-card"
    :style="{
      '--ru-card-max-width': '560px',
      '--accent': accentColor,
      '--accent-light': accentLight
    }"
  >
    <header class="ru-card__header">
      <div class="ru-header-left">
        <div class="ru-avatar circle" :style="{ background: childData?.child?.color || '#0ea5e9' }">
          <span v-if="!childData?.child?.avatar_url">
            {{ childData?.child?.name ? childData.child.name.charAt(0) : '?' }}
          </span>
          <img v-else :src="childData?.child?.avatar_url" alt="avatar" />
        </div>
        <div class="ru-header-info">
          <h2>{{ childData?.child?.name || 'Dieťa' }}</h2>
        </div>
      </div>
      <div class="ru-header-actions">
        <div class="ru-chip ru-chip--points lg">
          <strong>{{ childData?.points_balance ?? '–' }}</strong>
          <img :src="coinIcon" alt="coin" class="ru-coin" />
        </div>
      </div>
    </header>

    <div class="ru-days-bar" v-if="childData?.week_range">
      <button
        v-for="d in days"
        :key="d.value"
        :class="['ru-day-pill', { active: selectedDay === d.value }]"
        @click="changeDay(d.value)"
      >
        {{ d.label }}
      </button>
    </div>

    <div class="ru-card__body" v-if="loading">
      <p>Načítavam…</p>
    </div>

    <div class="ru-card__body" v-else-if="error">
      <p class="ru-error">{{ error }}</p>
    </div>

    <div class="ru-card__body" v-else>
      <div class="ru-section">
        <div class="ru-section__header">
          <h3>Povinné úlohy</h3>
        </div>
        <div v-if="(childData?.tasks?.povinne?.items || []).length" id="tasks">
          <div
            v-for="item in (childData?.tasks?.povinne?.items || [])"
            :key="item.id"
            class="ru-task"
            :class="{ 'is-done': isTaskDone(item) }"
          >
            <label v-if="isToday" class="ru-task__check">
              <input
                type="checkbox"
                :checked="isTaskDone(item)"
                :disabled="!isToday"
                @change="toggleStatus(item)"
              />
              <span></span>
            </label>
            <div v-else class="ru-task__status-dot" :class="{ done: isTaskDone(item) }"></div>
            <div class="ru-task__content">
              <div class="ru-task__row">
                <div class="ru-task__title">{{ item.task_name }}</div>
                <div class="ru-task__points">
                  {{ pointLabel(item, true) }} <img :src="coinIcon" alt="coin" class="ru-coin" />
                </div>
              </div>
              <p v-if="item.description" class="ru-task__desc">{{ item.description }}</p>
            </div>
          </div>
        </div>
        <p v-else class="ru-empty">Žiadne povinné úlohy na dnes.</p>
      </div>

      <div class="ru-section">
        <div class="ru-section__header">
          <h3>Dobrovoľné úlohy</h3>
        </div>
        <div v-if="(childData?.tasks?.dobrovolne?.items || []).length">
          <div
            v-for="item in (childData?.tasks?.dobrovolne?.items || [])"
            :key="item.id"
            class="ru-task"
            :class="{ 'is-done': isTaskDone(item) }"
          >
            <label v-if="isToday" class="ru-task__check">
              <input
                type="checkbox"
                :checked="isTaskDone(item)"
                :disabled="!isToday"
                @change="toggleStatus(item)"
              />
              <span></span>
            </label>
            <div v-else class="ru-task__status-dot" :class="{ done: isTaskDone(item) }"></div>
            <div class="ru-task__content">
              <div class="ru-task__row">
                <div class="ru-task__title">{{ item.task_name }}</div>
                <div class="ru-task__points">
                  {{ pointLabel(item, false) }} <img :src="coinIcon" alt="coin" class="ru-coin" />
                </div>
              </div>
              <p v-if="item.description" class="ru-task__desc">{{ item.description }}</p>
            </div>
          </div>
        </div>
        <p v-else class="ru-empty">Žiadne dobrovoľné úlohy na dnes.</p>
      </div>

    </div>

    <transition name="slide">
      <aside class="ru-drawer" v-if="showMenu">
        <div class="ru-drawer__header">
          <strong>Menu</strong>
          <button class="ru-link" @click="toggleMenu">Zavrieť</button>
        </div>
        <nav class="ru-drawer__nav">
          <a href="#tasks" @click.prevent="scrollTo('tasks')">Úlohy</a>
          <a href="#rewards" @click.prevent="scrollTo('rewards')">Odmeny</a>
          <a href="#settings" @click.prevent="scrollTo('settings')">Nastavenia</a>
        </nav>
        <div class="ru-drawer__section" id="settings">
          <p>Farebná škála</p>
          <div class="ru-colors">
            <button
              v-for="c in palette"
              :key="c"
              :style="{ background: c }"
              :class="{ active: accentColor === c }"
              @click="setAccent(c)"
            ></button>
          </div>
        </div>
      </aside>
    </transition>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/client';
import coinPng from '../images/star.png';
import { setCachedOverview } from '../state/preloadCache';
import { DAYS_SHORT_SK, DAY_ORDER, isDayPastOrToday } from '../utils/days';
import { pointLabel as pointLabelUtil } from '../utils/points';
import { useChildOverview } from '../composables/useChildOverview';
import { emitRuDataChanged } from '../events/ruEvents';

const safeLsGet = (key) => {
  try {
    return localStorage.getItem(key) || '';
  } catch {
    return '';
  }
};

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
const effectiveChildId = computed(
  () => props.childId || route.params.childId || (props.localized?.children?.[0]?.id ?? '')
);
const todayDay = ref(new Date().getDay()); // 0 = NE
const selectedDay = ref(todayDay.value);
const days = DAYS_SHORT_SK;
const isToday = computed(() => Number(selectedDay.value) === Number(todayDay.value));
const isDayPastOrTodayLocal = (day) => isDayPastOrToday(day, todayDay.value);
const isFutureSelected = computed(() => !isDayPastOrTodayLocal(selectedDay.value));

const loading = ref(true);
const error = ref('');
const childData = ref(null);
const purchaseLoading = ref(null); // legacy, rewards moved to /rewards
const weekendMultiplier = computed(() =>
  Number(props.localized?.weekendMultiplier) > 0 ? Number(props.localized.weekendMultiplier) : 1
);

const { load: loadOverview } = useChildOverview({
  api,
  dataRef: childData,
  loadingRef: loading,
  errorRef: error,
  // Child page: show cache immediately + revalidate after short time (same as overview).
  revalidateMs: 8000,
});

async function loadData() {
  await loadOverview({ childId: effectiveChildId.value, day: selectedDay.value });
}

function toYmd(d) {
  if (!(d instanceof Date) || Number.isNaN(d.getTime())) return '';
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function ymdForSelectedDay() {
  const start = childData.value?.week_range?.start || '';
  if (!start) return '';
  // week_start is Monday (YYYY-MM-DD). selectedDay uses JS getDay() convention.
  const base = new Date(`${start}T00:00:00`);
  const offset = Object.prototype.hasOwnProperty.call(DAY_ORDER, Number(selectedDay.value))
    ? DAY_ORDER[Number(selectedDay.value)]
    : 0;
  base.setDate(base.getDate() + offset);
  return toYmd(base);
}

function parseMysqlDateTime(input) {
  const raw = String(input || '').trim();
  if (!raw) return null;
  // MySQL datetime is usually "YYYY-MM-DD HH:MM:SS". JS parses both with a space and with 'T' as local time.
  const d = new Date(raw);
  if (!Number.isNaN(d.getTime())) return d;
  const d2 = new Date(raw.replace(' ', 'T'));
  return Number.isNaN(d2.getTime()) ? null : d2;
}

const isTaskDone = (item) => {
  if (!item) return false;
  if (item.status !== 'completed') return false;
  // Never show "done" for future-selected days.
  if (!isDayPastOrTodayLocal(selectedDay.value)) return false;

  // For tasks that appear on multiple days in the same week (days_of_week),
  // the assignment status is weekly. We therefore treat "done" per-day by
  // comparing completion timestamp to the selected day date in this week.
  const completedAt = item.completed_at || item.completedAt || '';
  const raw = String(completedAt || '').trim();
  const completedYmd =
    /^\d{4}-\d{2}-\d{2}/.test(raw) ? raw.slice(0, 10) : toYmd(parseMysqlDateTime(raw));

  const targetYmd = ymdForSelectedDay();
  if (completedYmd && targetYmd) return completedYmd === targetYmd;

  // Fallback (older payloads without week_range): compare weekday.
  const d = parseMysqlDateTime(raw);
  if (!d) return false;
  return d.getDay() === Number(selectedDay.value);
};

async function toggleStatus(item) {
  if (!isToday.value) return;
  // IMPORTANT:
  // Assignments are weekly, but tasks may display on multiple days.
  // We toggle based on the *displayed* done-state (per-day), not raw status,
  // so a task completed on a previous day doesn't accidentally get "undone"
  // (and potentially deduct points) when it shows up again today.
  const wasDoneToday = isTaskDone(item);
  const newStatus = wasDoneToday ? 'todo' : 'completed';
  const prevStatus = item.status;
  const prevCompletedAt = item.completed_at;
  try {
    // No full refresh: optimistic update + patch points from API response.
    item.status = newStatus;
    if (newStatus === 'completed') {
      // keep MySQL-ish format so isTaskDone() works immediately
      item.completed_at = new Date().toISOString().slice(0, 19).replace('T', ' ');
    } else {
      item.completed_at = null;
    }
    const res = await api.updateChildTaskStatus(item.id, newStatus);
    if (childData.value) {
      if (typeof res?.points_balance === 'number') {
        childData.value.points_balance = res.points_balance;
      }
      if (typeof res?.points_today === 'number') {
        childData.value.points_today = res.points_today;
      }
    }
    // Update cached overview snapshot for this child/day so other pages can reuse it.
    try {
      if (childData.value) {
        setCachedOverview(effectiveChildId.value, selectedDay.value, childData.value);
      }
    } catch {}
    // Mark overview as stale for parent view; refresh on next open (no hard reload).
    try {
      localStorage.setItem('ru_overview_stale', '1');
    } catch {}
    emitRuDataChanged({
      type: 'task_status_changed',
      childId: String(effectiveChildId.value || ''),
      day: selectedDay.value,
    });
  } catch (e) {
    // Revert optimistic UI if API fails
    item.status = prevStatus;
    item.completed_at = prevCompletedAt;
    error.value = e?.message || 'Chyba pri zmene stavu';
  }
}

onMounted(() => {
  if (effectiveChildId.value) {
    loadData();
  } else {
    error.value = 'Chýba ID dieťaťa';
    loading.value = false;
  }
});

let dayTimer = null;
const syncToday = () => {
  const prev = Number(todayDay.value);
  const now = new Date().getDay();
  if (Number(now) === prev) return;
  todayDay.value = now;
  // If user was effectively on "today", move them to the new day automatically.
  if (Number(selectedDay.value) === prev) {
    selectedDay.value = now;
    loadData();
  }
};

onMounted(() => {
  // If the app stays open across midnight, refresh "today" and avoid stale checkboxes.
  try {
    dayTimer = setInterval(syncToday, 60 * 1000);
    window.addEventListener('focus', syncToday);
    document.addEventListener('visibilitychange', syncToday);
  } catch {}
});

onBeforeUnmount(() => {
  try {
    if (dayTimer) clearInterval(dayTimer);
  } catch {}
  dayTimer = null;
  try { window.removeEventListener('focus', syncToday); } catch {}
  try { document.removeEventListener('visibilitychange', syncToday); } catch {}
});

const changeDay = (day) => {
  selectedDay.value = day;
  loadData();
};

const pointLabel = (item, isMandatory) =>
  pointLabelUtil(item, isMandatory, selectedDay.value, weekendMultiplier.value);

// Rewards presunuté na samostatnú stránku /rewards

const localAccent = ref(safeLsGet('ru-accent'));
const accentColor = computed(() => childData.value?.child?.color || localAccent.value || '#0ea5e9');
const accentLight = computed(() => `${accentColor.value}33`);
const palette = ['#0ea5e9', '#16a34a', '#f97316', '#f59e0b', '#6366f1', '#ec4899', '#8b5cf6', '#0ea35c'];
const showMenu = ref(false);
const coinIcon = coinPng;

const setAccent = (c) => {
  localAccent.value = c;
};
const toggleMenu = () => {
  showMenu.value = !showMenu.value;
};
const scrollTo = (id) => {
  const el = document.getElementById(id);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  showMenu.value = false;
};

watch(
  () => accentColor.value,
  (val) => {
    if (!val) return;
    document.documentElement.style.setProperty('--ru-accent', val);
    document.documentElement.style.setProperty('--ru-accent-light', `${val}33`);
  },
  { immediate: true }
);
</script>

<style scoped>
.ru-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.ru-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  color: white;
  font-weight: 700;
  font-size: 20px;
  overflow: hidden;
}

.ru-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ru-header-info h2 {
  margin: 0;
}

.ru-card__body {
  font-size: 16px;
}

.ru-chip--sub {
  background: #e0f2fe;
  color: #075985;
  box-shadow: none;
}

.ru-pre {
  background: #0b1021;
  color: #cde2ff;
  padding: 12px;
  border-radius: 10px;
  overflow: auto;
  font-size: 12px;
  margin-top: 12px;
}

.ru-details summary {
  cursor: pointer;
  color: #0ea5e9;
  font-weight: 600;
  margin-top: 8px;
}

.ru-error {
  color: #b91c1c;
  font-weight: 600;
}

.ru-section {
  margin: 0 0 30px;
  padding: 0;
  background: transparent;
  border: 0;
  border-radius: 0;
  box-shadow: none;
}

.ru-section__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
  padding: 0;
}

.ru-task {
  display: flex;
  gap: 12px;
  padding: 5px;
  border-radius: 15px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  margin-bottom: 5px;
  align-items: center;
}

.ru-task.is-done {
  /* Done tasks should use child's profile accent (soft) */
  background: var(--ru-accent-light, #dcfce7);
  border-color: rgba(15, 23, 42, 0.14);
}

@media (max-width: 768px) {
  .ru-avatar {
    width: 80px;
    height: 80px;
    font-size: 18px;
  }
}

.ru-task__check {
  width: 44px;
  height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.ru-task__check input {
  display: none;
}

.ru-task__check span {
  width: 20px;
  height: 20px;
  border-radius: 6px;
  border: 2px solid var(--ru-accent, #0ea5e9);
  display: inline-block;
  position: relative;
}

.ru-task__check input:checked + span {
  background: var(--ru-accent, #0ea5e9);
}

.ru-task__check input:checked + span::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 6px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.ru-task__status-dot {
  width: 20px;
  height: 20px;
  border-radius: 6px;
  border: 2px solid #d1d5db;
  background: #f8fafc;
  flex-shrink: 0;
}
.ru-task__status-dot.done {
  border-color: var(--ru-accent, #0ea5e9);
  background: var(--ru-accent, #0ea5e9);
}

.ru-task__content {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-height: 44px;
}

.ru-task__title {
  font-weight: 700;
  margin-bottom: 4px;
}
.ru-task__row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: center;
}
.ru-task__points {
  font-weight: 800;
  color: #d69116;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.ru-coin {
  width: 25px;
  height: 25px;
  object-fit: contain;
}

.ru-pin {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-width: 220px;
}
.ru-pin__input {
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 16px;
  letter-spacing: 4px;
  text-align: center;
}

.ru-task__meta {
  display: flex;
  gap: 6px;
  margin-bottom: 6px;
}

.ru-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 8px;
  background: #fee2e2;
  color: #b91c1c;
  font-weight: 700;
  font-size: 12px;
}

.ru-badge.green {
  background: #dcfce7;
  color: #166534;
}

.ru-badge.ghost {
  background: #e5e7eb;
  color: #374151;
}

.ru-task__desc {
  margin: 0;
  color: #4b5563;
}

.ru-empty {
  color: #6b7280;
  font-style: italic;
}

.ru-rewards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px;
}

.ru-reward {
  position: relative;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px;
  background: white;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ru-reward.disabled {
  opacity: 0.6;
}

.ru-reward__icon {
  font-size: 24px;
}

.ru-reward__title {
  font-weight: 700;
}

.ru-reward__cost {
  color: #d69116;
  font-weight: 700;
}

.ru-reward__badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: #fde68a;
  color: #92400e;
  padding: 3px 8px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 12px;
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
  gap: 8px;
}
.ru-drawer__nav a {
  text-decoration: none;
  color: #0ea5e9;
  font-weight: 700;
}
.ru-drawer__section {
  border-top: 1px solid #e5e7eb;
  padding-top: 10px;
}
.ru-colors {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-top: 8px;
}
.ru-colors button {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
}
.ru-colors button.active {
  border-color: #0f172a;
}
.slide-enter-active, .slide-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.slide-enter-from, .slide-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

.ru-btn-buy {
  margin-top: 4px;
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #0ea5e9;
  color: white;
  font-weight: 700;
  cursor: pointer;
}
.ru-btn-buy:disabled {
  background: #e5e7eb;
  color: #6b7280;
  cursor: not-allowed;
}
</style>

