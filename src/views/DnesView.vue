<template>
  <section class="ru-card ru-dnes-card">
    <header class="ru-dnes-header">
      <div>
        <h2 class="ru-dnes-title">Dnes</h2>
        <p class="ru-dnes-date">{{ dateLabel }}</p>
      </div>
    </header>

    <div class="ru-card__body" v-if="!isParent">
      <p class="ru-error">Prístup len pre rodiča.</p>
    </div>

    <div class="ru-card__body" v-else-if="childrenLoading">Načítavam…</div>

    <div class="ru-card__body" v-else-if="!children.length">
      <div class="ru-empty-state">
        <h3>Zatiaľ žiadne deti</h3>
        <p class="ru-empty-state__text">Pridajte dieťa v sekcii Rodina.</p>
      </div>
    </div>

    <div class="ru-card__body" v-else-if="loading && !hasAnyData">Načítavam…</div>

    <div class="ru-card__body" v-else-if="error && !hasAnyData">
      <p class="ru-error">{{ error }}</p>
    </div>

    <div class="ru-card__body ru-dnes-list" v-else>
      <article
        v-for="entry in childEntries"
        :key="entry.child.id"
        class="ru-dnes-child"
        :style="{ '--child-accent': entry.child.color || '#0ea5e9' }"
      >
        <header class="ru-dnes-child__header">
          <div class="ru-dnes-child__who">
            <ChildAvatarBadge
              :child="entry.child"
              :points="childPointsBalance(entry)"
            />
            <h3 class="ru-dnes-child__name">{{ entry.child.name || 'Dieťa' }}</h3>
          </div>
        </header>

        <div class="ru-dnes-child__body" v-if="entry.loading">Načítavam…</div>
        <div class="ru-dnes-child__body ru-error" v-else-if="entry.error">{{ entry.error }}</div>

        <div class="ru-dnes-child__body" v-else>
          <div v-if="mandatoryTasks(entry).length" class="ru-task-list">
            <div
              v-for="item in mandatoryTasks(entry)"
              :key="item.id"
              class="ru-task-row"
              :class="{ done: isTaskDone(entry, item) }"
            >
              <div class="ru-task__icon-wrap">
                <img
                  v-if="taskIconUrl(item)"
                  :src="taskIconUrl(item)"
                  alt=""
                  class="ru-task__icon"
                />
              </div>
              <div class="ru-task-body">
                <div class="ru-task-title">{{ item.task_name || item.name }}</div>
              </div>
              <label class="ru-task-check">
                <input
                  type="checkbox"
                  :checked="isTaskDone(entry, item)"
                  @change="toggleStatus(entry, item)"
                />
                <span></span>
              </label>
            </div>
          </div>
          <p v-else class="ru-empty">Žiadne povinné úlohy na dnes.</p>
        </div>

        <div class="ru-dnes-child__footer">
          <button
            class="ru-dnes-child__all-tasks"
            type="button"
            @click="openChildDetail(entry.child.id)"
          >
            Všetky úlohy
          </button>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, onActivated, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api/client';
import { childrenApi } from '../api/children';
import ChildAvatarBadge from '../components/ChildAvatarBadge.vue';
import { getTaskIconUrl } from '../lib/taskIcons';
import { emitRuDataChanged, onRuAuthChanged, onRuDataChanged, onRuForceOverviewRefresh } from '../events/ruEvents';
import {
  getCachedChildren,
  getCachedOverview,
  getCachedOverviewAgeMs,
  setCachedChildren,
  setCachedOverview,
} from '../state/preloadCache';
import { applyWeekendMultiplierFromPayload } from '../state/weekendMultiplier';
import { DAY_ORDER, getWeekStartForDate, isDayPastOrToday } from '../utils/days';

defineOptions({ name: 'DnesView' });

const props = defineProps({
  role: { type: String, default: 'parent' },
  localized: { type: Object, default: () => ({}) },
  appReady: { type: Boolean, default: false },
});

const taskIconUrl = (item) => getTaskIconUrl(item?.task_icon || item?.icon || '');
const childPointsBalance = (entry) => {
  const n = Number(entry?.data?.points_balance ?? 0);
  return Number.isFinite(n) ? n : 0;
};
const router = useRouter();

const isParent = computed(() => {
  if (props.role === 'parent') return true;
  if (props.role === 'child') return false;
  return !!(props.localized?.isParent && !props.localized?.forceChild);
});

const todayDay = ref(new Date().getDay());
const dateLabel = ref('');

const formatDateLabel = () => {
  try {
    dateLabel.value = new Date().toLocaleDateString('sk-SK', {
      weekday: 'long',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  } catch {
    dateLabel.value = '';
  }
};

const children = ref([]);
const childrenLoading = ref(false);
const childDataById = ref({});
const childLoadingById = ref({});
const childErrorById = ref({});
const loading = ref(false);
const error = ref('');

const hasAnyData = computed(() =>
  Object.values(childDataById.value || {}).some((v) => v && typeof v === 'object')
);

const childEntries = computed(() =>
  (Array.isArray(children.value) ? children.value : [])
    .filter((c) => c && Number(c.id || 0) > 0)
    .map((child) => ({
      child,
      data: childDataById.value[String(child.id)] || null,
      loading: !!childLoadingById.value[String(child.id)],
      error: childErrorById.value[String(child.id)] || '',
    }))
);

const mandatoryTasks = (entry) => entry?.data?.tasks?.povinne?.items || [];

function parseMysqlDateTime(input) {
  const raw = String(input || '').trim();
  if (!raw) return null;
  const d = new Date(raw);
  if (!Number.isNaN(d.getTime())) return d;
  const d2 = new Date(raw.replace(' ', 'T'));
  return Number.isNaN(d2.getTime()) ? null : d2;
}

function toYmd(d) {
  if (!(d instanceof Date) || Number.isNaN(d.getTime())) return '';
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function ymdForSelectedDay(data) {
  const start = data?.week_range?.start || '';
  if (!start) return '';
  const base = new Date(`${start}T00:00:00`);
  const offset = Object.prototype.hasOwnProperty.call(DAY_ORDER, Number(todayDay.value))
    ? DAY_ORDER[Number(todayDay.value)]
    : 0;
  base.setDate(base.getDate() + offset);
  return toYmd(base);
}

const isTaskDone = (entry, item) => {
  if (!item) return false;
  if (item.status !== 'completed') return false;
  if (!isDayPastOrToday(todayDay.value, todayDay.value)) return false;
  const completedAt = item.completed_at || item.completedAt || '';
  const raw = String(completedAt || '').trim();
  const completedYmd =
    /^\d{4}-\d{2}-\d{2}/.test(raw) ? raw.slice(0, 10) : toYmd(parseMysqlDateTime(raw));
  const targetYmd = ymdForSelectedDay(entry.data);
  if (completedYmd && targetYmd) return completedYmd === targetYmd;
  const d = parseMysqlDateTime(raw);
  if (!d) return false;
  return d.getDay() === Number(todayDay.value);
};

let childrenReqId = 0;
const loadChildren = async () => {
  const reqId = ++childrenReqId;
  if (!isParent.value) {
    children.value = [];
    return;
  }

  const cached = getCachedChildren();
  if (Array.isArray(cached)) {
    if (reqId === childrenReqId) {
      children.value = cached;
      childrenLoading.value = false;
    }
    if (cached.length) return;
  }

  if (reqId === childrenReqId) childrenLoading.value = true;
  try {
    const list = await childrenApi.list();
    if (reqId !== childrenReqId) return;
    children.value = Array.isArray(list) ? list : [];
    setCachedChildren(children.value);
  } catch (e) {
    if (reqId !== childrenReqId) return;
    children.value = [];
    error.value = e?.message || 'Chyba pri načítaní detí';
  } finally {
    if (reqId === childrenReqId) childrenLoading.value = false;
  }
};

const OVERVIEW_REVALIDATE_MS = 8000;

const setChildState = (childId, { data, loading: isLoading, error: errMsg } = {}) => {
  const cid = String(childId || '');
  if (!cid) return;
  if (data !== undefined) {
    childDataById.value = { ...childDataById.value, [cid]: data };
  }
  if (isLoading !== undefined) {
    childLoadingById.value = { ...childLoadingById.value, [cid]: !!isLoading };
  }
  if (errMsg !== undefined) {
    childErrorById.value = { ...childErrorById.value, [cid]: String(errMsg || '') };
  }
};

const loadChildOverview = async (childId, { force = false, background = false } = {}) => {
  const cid = String(childId || '');
  const day = todayDay.value;
  const ws = getWeekStartForDate();
  if (!cid) return null;

  const cached = getCachedOverview(cid, day, ws);
  const age = getCachedOverviewAgeMs(cid, day, ws);

  if (cached && !force) {
    setChildState(cid, { data: cached, loading: false, error: '' });
    applyWeekendMultiplierFromPayload(cached);
    if (Number.isFinite(age) && age < OVERVIEW_REVALIDATE_MS) return cached;
    background = true;
  }

  if (!background) setChildState(cid, { loading: true, error: '' });

  try {
    const fresh = await api.getChildOverview(cid, day, ws);
    setChildState(cid, { data: fresh, loading: false, error: '' });
    applyWeekendMultiplierFromPayload(fresh);
    setCachedOverview(cid, day, fresh, ws);
    return fresh;
  } catch (e) {
    if (!background) {
      setChildState(cid, { data: null, loading: false, error: e?.message || 'Chyba pri načítaní dát' });
    }
    return null;
  }
};

let loadAllReqId = 0;
const loadAllChildData = async ({ force = false, background = false } = {}) => {
  const reqId = ++loadAllReqId;
  const list = (Array.isArray(children.value) ? children.value : []).filter((c) => Number(c?.id || 0) > 0);
  if (!list.length || !isParent.value) return;

  if (!background && reqId === loadAllReqId) {
    loading.value = true;
    error.value = '';
  }

  await Promise.allSettled(
    list.map((child) => loadChildOverview(child.id, { force, background }))
  );

  if (reqId === loadAllReqId && !background) {
    loading.value = false;
  }
};

async function toggleStatus(entry, item) {
  const childId = String(entry?.child?.id || '');
  if (!childId || !item) return;

  const wasDoneToday = isTaskDone(entry, item);
  const newStatus = wasDoneToday ? 'todo' : 'completed';
  const prevStatus = item.status;
  const prevCompletedAt = item.completed_at;

  try {
    item.status = newStatus;
    if (newStatus === 'completed') {
      item.completed_at = new Date().toISOString().slice(0, 19).replace('T', ' ');
    } else {
      item.completed_at = null;
    }

    const res = await api.updateChildTaskStatus(item.id, newStatus);
    const data = entry.data;
    if (data) {
      if (typeof res?.points_balance === 'number') data.points_balance = res.points_balance;
      if (typeof res?.points_today === 'number') data.points_today = res.points_today;
      setCachedOverview(childId, todayDay.value, data, getWeekStartForDate());
    }

    try {
      localStorage.setItem('ru_overview_stale', '1');
    } catch {}

    emitRuDataChanged({
      type: 'task_status_changed',
      childId,
      day: todayDay.value,
    });
  } catch (e) {
    item.status = prevStatus;
    item.completed_at = prevCompletedAt;
    error.value = e?.message || 'Chyba pri zmene stavu';
  }
}

const OVERVIEW_PREF_MODE_KEY = 'ru_overview_mode';
const OVERVIEW_PREF_CHILD_KEY = 'ru_overview_selected_child';

const openChildDetail = (childId) => {
  const id = String(childId || '');
  if (!id) return;
  try {
    localStorage.setItem(OVERVIEW_PREF_MODE_KEY, 'detail');
    localStorage.setItem(OVERVIEW_PREF_CHILD_KEY, id);
  } catch {}
  router.push({ name: 'overview' });
};

const refreshPage = async ({ force = false, background = false } = {}) => {
  if (!isParent.value || !props.appReady) return;
  await loadChildren();
  await loadAllChildData({ force, background });
};

let dayTimer = null;
const syncToday = () => {
  const prev = Number(todayDay.value);
  const now = new Date().getDay();
  formatDateLabel();
  if (Number(now) === prev) return;
  todayDay.value = now;
  loadAllChildData({ force: true, background: true });
};

let offAuth = null;
let offData = null;
let offForce = null;

watch(
  () => [isParent.value, props.appReady],
  ([parent, ready]) => {
    if (parent && ready) refreshPage();
  },
  { immediate: true }
);

onMounted(() => {
  formatDateLabel();

  offAuth = onRuAuthChanged(() => {
    children.value = [];
    childDataById.value = {};
    childLoadingById.value = {};
    childErrorById.value = {};
    refreshPage({ force: true });
  });

  offData = onRuDataChanged((e) => {
    const detail = e?.detail || {};
    if (detail?.type === 'task_status_changed') return;
    refreshPage({ force: true, background: true });
  });

  offForce = onRuForceOverviewRefresh(() => {
    refreshPage({ force: true, background: true });
  });

  try {
    dayTimer = setInterval(syncToday, 60 * 1000);
    window.addEventListener('focus', syncToday);
    document.addEventListener('visibilitychange', syncToday);
  } catch {}
});

onActivated(() => {
  refreshPage({ force: true, background: true });
});

onBeforeUnmount(() => {
  try { offAuth?.(); } catch {}
  try { offData?.(); } catch {}
  try { offForce?.(); } catch {}
  try {
    if (dayTimer) clearInterval(dayTimer);
  } catch {}
  dayTimer = null;
  try { window.removeEventListener('focus', syncToday); } catch {}
  try { document.removeEventListener('visibilitychange', syncToday); } catch {}
});
</script>

<style scoped>
.ru-dnes-card {
  --ru-card-max-width: 760px;
}

.ru-dnes-header {
  padding: 8px 8px 4px;
  text-align: left;
}

.ru-dnes-title {
  margin: 0;
  font-size: 28px;
  font-weight: 900;
  color: #0f172a;
}

.ru-dnes-date {
  margin: 4px 0 0;
  color: #64748b;
  font-weight: 700;
  font-size: 14px;
  text-transform: capitalize;
}

.ru-dnes-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ru-dnes-child {
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  overflow: visible;
}

.ru-dnes-child__header {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 8px 12px;
  border-bottom: 0;
}

.ru-dnes-child__who {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 0;
  text-align: center;
}

.ru-dnes-child__name {
  margin: 0;
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
  line-height: 1.1;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ru-dnes-child__body {
  padding: 0 0 4px;
}

.ru-dnes-child__footer {
  display: flex;
  justify-content: center;
  padding: 8px 8px 4px;
}

.ru-dnes-child__all-tasks {
  border: 1px solid rgba(15, 23, 42, 0.14);
  background: transparent;
  border-radius: 999px;
  padding: 8px 18px;
  font-weight: 700;
  font-size: 14px;
  color: #0f172a;
  cursor: pointer;
}

.ru-dnes-child__all-tasks:hover {
  border-color: rgba(15, 23, 42, 0.28);
}

.ru-task-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ru-task-row {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 5px 8px;
  border-radius: 12px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  overflow: hidden;
}

.ru-task-row.done .ru-task-title {
  color: #64748b;
  text-decoration: line-through;
}

.ru-task__icon-wrap {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.ru-task__icon {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.ru-task-body {
  flex: 1;
  min-width: 0;
}

.ru-task-title {
  font-weight: 600;
  color: #0f172a;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ru-task-points-sub {
  margin-top: 2px;
  font-weight: 700;
  font-size: 13px;
  color: #16a34a;
}

.ru-task-check {
  width: 36px;
  height: 36px;
}

.ru-empty {
  margin: 0;
  color: #94a3b8;
  font-weight: 600;
}

.ru-empty-state h3 {
  margin: 0 0 6px;
  font-size: 18px;
  font-weight: 900;
}

.ru-empty-state__text {
  margin: 0;
  color: #64748b;
  font-weight: 700;
  font-size: 13px;
}

.ru-error {
  color: #b91c1c;
  font-weight: 600;
}

.ru-coin {
  width: 22px;
  height: 22px;
  object-fit: contain;
}

@media (max-width: 640px) {
  .ru-dnes-child__header {
    flex-wrap: wrap;
  }

  .ru-dnes-child__detail {
    width: 100%;
  }
}
</style>
