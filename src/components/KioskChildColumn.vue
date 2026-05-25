<template>
  <div class="ru-kiosk-col-wrap" :style="{ '--accent': accentColor, '--accent-light': accentLight }">
    <section class="ru-kiosk-col">
      <header class="ru-kiosk-col__header">
        <button class="ru-kiosk-col__who ru-kiosk-col__who-btn" type="button" @click="emitOpenDetail">
          <div class="ru-kiosk-col__avatar" :style="{ background: accentColor }">
            <span v-if="!child?.avatar_url">{{ (child?.name || '?').charAt(0) }}</span>
            <img v-else :src="child.avatar_url" :alt="child?.name || 'avatar'" />
          </div>
          <div class="ru-kiosk-col__name">{{ child?.name || 'Dieťa' }}</div>
        </button>
      </header>

      <div class="ru-kiosk-col__body" v-if="loading">
        Načítavam…
      </div>
      <div class="ru-kiosk-col__body ru-kiosk-col__body--error" v-else-if="error">
        {{ error }}
      </div>

      <div class="ru-kiosk-col__body ru-kiosk-col__body--scroll" v-else>
        <div class="ru-kiosk-col__group">
          <div class="ru-kiosk-col__group-title">Povinné</div>
          <div v-if="povinne.length" class="ru-kiosk-col__list">
            <div
              v-for="item in povinne"
              :key="item.id"
              class="ru-kiosk-col__task"
              :class="{ done: isTaskDone(item) }"
              @click="toggleStatus(item)"
            >
              <span class="ru-kiosk-col__dot" :class="{ on: isTaskDone(item) }" aria-hidden="true"></span>
              <span class="ru-kiosk-col__text">
                <span class="ru-kiosk-col__task-title">{{ item.task_name }}</span>
              </span>
              <div class="ru-kiosk-col__task-actions">
                <span class="ru-kiosk-col__task-points" v-if="taskPoints(item)">{{ taskPoints(item) }}</span>
                <div class="ru-kiosk-col__rotate-wrap" v-if="isRotatingTask(item)">
                  <button
                    type="button"
                    class="ru-kiosk-col__rotate-btn"
                    title="Presunúť rotačnú úlohu na iné dieťa"
                    @click.stop="openShiftMenu(item)"
                  >
                    ⟳
                  </button>
                  <div v-if="shiftOpenTaskId === Number(item.task_id || 0)" class="ru-kiosk-col__rotate-menu" @click.stop>
                    <button
                      v-for="target in shiftChildrenOptions(item)"
                      :key="`povinne-shift-${item.id}-${target.id}`"
                      type="button"
                      class="ru-kiosk-col__rotate-option"
                      :disabled="shiftLoadingTaskId === Number(item.task_id || 0)"
                      @click.stop="shiftTaskToChild(item, target.id)"
                    >
                      {{ target.name }}
                    </button>
                    <div class="ru-kiosk-col__rotate-empty" v-if="!shiftChildrenOptions(item).length">
                      Úloha má len jedno dieťa
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="ru-kiosk-col__empty">Žiadne povinné úlohy.</div>
        </div>

        <div class="ru-kiosk-col__group">
          <div class="ru-kiosk-col__group-title">Dobrovoľné</div>
          <div v-if="dobrovolne.length" class="ru-kiosk-col__list">
            <div
              v-for="item in dobrovolne"
              :key="item.id"
              class="ru-kiosk-col__task"
              :class="{ done: isTaskDone(item) }"
              @click="toggleStatus(item)"
            >
              <span class="ru-kiosk-col__dot" :class="{ on: isTaskDone(item) }" aria-hidden="true"></span>
              <span class="ru-kiosk-col__text">
                <span class="ru-kiosk-col__task-title">{{ item.task_name }}</span>
              </span>
              <div class="ru-kiosk-col__task-actions">
                <span class="ru-kiosk-col__task-points" v-if="taskPoints(item)">{{ taskPoints(item) }}</span>
                <div class="ru-kiosk-col__rotate-wrap" v-if="isRotatingTask(item)">
                  <button
                    type="button"
                    class="ru-kiosk-col__rotate-btn"
                    title="Presunúť rotačnú úlohu na iné dieťa"
                    @click.stop="openShiftMenu(item)"
                  >
                    ⟳
                  </button>
                  <div v-if="shiftOpenTaskId === Number(item.task_id || 0)" class="ru-kiosk-col__rotate-menu" @click.stop>
                    <button
                      v-for="target in shiftChildrenOptions(item)"
                      :key="`dobrovolne-shift-${item.id}-${target.id}`"
                      type="button"
                      class="ru-kiosk-col__rotate-option"
                      :disabled="shiftLoadingTaskId === Number(item.task_id || 0)"
                      @click.stop="shiftTaskToChild(item, target.id)"
                    >
                      {{ target.name }}
                    </button>
                    <div class="ru-kiosk-col__rotate-empty" v-if="!shiftChildrenOptions(item).length">
                      Úloha má len jedno dieťa
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="ru-kiosk-col__empty">Žiadne dobrovoľné úlohy.</div>
        </div>

        <div class="ru-kiosk-col__shift-error" v-if="shiftError">{{ shiftError }}</div>

        <div class="ru-kiosk-col__done" v-if="totals.total > 0 && totals.completed === totals.total">
          Hotovo!
        </div>
      </div>
    </section>

    <!-- Outside footer: rendered below the white card (.ru-kiosk-col) -->
    <footer class="ru-kiosk-col__outside">
      <div class="ru-kiosk-col__footer-row">
        <div class="ru-kiosk-col__balance">
          <span class="ru-kiosk-col__balance-label">Body</span>
          <span class="ru-kiosk-col__balance-value">{{ pointsBalance }}</span>
        </div>
      </div>

      <div class="ru-kiosk-col__footer-row" v-if="purchasedRewards.length">
        <div class="ru-kiosk-col__purchased">
          <span class="ru-kiosk-col__purchased-label">Zakúpené</span>
          <div class="ru-kiosk-col__purchased-chips">
            <span
              v-for="r in purchasedRewards"
              :key="String(r.rewardId)"
              class="ru-kiosk-col__chip"
              :title="r.title"
            >
              <span class="ru-kiosk-col__chip-icon">{{ r.icon }}</span>
              <span class="ru-kiosk-col__chip-title">{{ r.title }}</span>
              <span class="ru-kiosk-col__chip-count" v-if="r.count > 1">×{{ r.count }}</span>
            </span>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api } from '../api/client';
import { tasksApi } from '../api/tasks';
import { useChildOverview } from '../composables/useChildOverview';
import { DAY_ORDER } from '../utils/days';
import { setCachedOverview } from '../state/preloadCache';
import { emitRuDataChanged } from '../events/ruEvents';

const props = defineProps({
  child: { type: Object, required: true },
  day: { type: Number, default: () => new Date().getDay() },
  tasksMetaById: { type: Object, default: () => ({}) },
  allChildIds: { type: Array, default: () => [] },
});
const emit = defineEmits(['open-detail']);

const loading = ref(true);
const error = ref('');
const childData = ref(null);
const shiftOpenTaskId = ref(0);
const shiftLoadingTaskId = ref(0);
const shiftError = ref('');
const shiftChildrenByTask = ref({});

const accentColor = computed(() => props.child?.color || '#0ea5e9');
const accentLight = computed(() => `${accentColor.value}33`);

const emitOpenDetail = () => {
  const id = Number(props.child?.id || 0);
  if (!id) return;
  emit('open-detail', id);
};

const { load: loadOverview } = useChildOverview({
  api,
  dataRef: childData,
  loadingRef: loading,
  errorRef: error,
  revalidateMs: 8000,
});

const effectiveChildId = computed(() => String(props.child?.id || ''));
const selectedDay = computed(() => Number(props.day));
const todayDay = ref(new Date().getDay());
const isToday = computed(() => Number(selectedDay.value) === Number(todayDay.value));

function toYmd(d) {
  if (!(d instanceof Date) || Number.isNaN(d.getTime())) return '';
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function parseMysqlDateTime(input) {
  const raw = String(input || '').trim();
  if (!raw) return null;
  const d = new Date(raw);
  if (!Number.isNaN(d.getTime())) return d;
  const d2 = new Date(raw.replace(' ', 'T'));
  return Number.isNaN(d2.getTime()) ? null : d2;
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

const isTaskDone = (item) => {
  if (!item) return false;
  if (item.status !== 'completed') return false;
  // in kiosk we only toggle "today", but still render correctly if day changes at midnight
  const completedAt = item.completed_at || item.completedAt || '';
  const raw = String(completedAt || '').trim();
  const completedYmd =
    /^\d{4}-\d{2}-\d{2}/.test(raw) ? raw.slice(0, 10) : toYmd(parseMysqlDateTime(raw));

  const targetYmd = ymdForSelectedDay();
  if (completedYmd && targetYmd) return completedYmd === targetYmd;

  const d = parseMysqlDateTime(raw);
  if (!d) return false;
  return d.getDay() === Number(selectedDay.value);
};

const taskPoints = (item) => {
  const n = Number(item?.task_rating ?? item?.rating ?? 0);
  return Number.isFinite(n) && n > 0 ? n : 0;
};

const isRotatingTask = (item) => {
  const tid = Number(item?.task_id || 0);
  const meta = tid ? props.tasksMetaById?.[tid] : null;
  // Strict rule: show manual shift only when current assignment payload
  // explicitly says this task is rotating.
  const hasRotationFlag = item && Object.prototype.hasOwnProperty.call(item, 'rotation_enabled');
  if (!hasRotationFlag) return false;
  if (Number(item?.rotation_enabled ?? 0) !== 1) return false;
  if (!meta) return false;
  const taskChildIds = Array.isArray(meta.children_ids)
    ? meta.children_ids.map((id) => Number(id || 0)).filter((id) => id > 0)
    : [];
  return taskChildIds.length >= 2;
};

const closeShiftMenu = () => {
  shiftOpenTaskId.value = 0;
  shiftError.value = '';
};

const onDocClick = () => {
  closeShiftMenu();
};

const ensureShiftChildren = async (taskId) => {
  const tid = Number(taskId || 0);
  if (!tid) return [];
  if (Array.isArray(shiftChildrenByTask.value[tid])) {
    return shiftChildrenByTask.value[tid];
  }
  const task = await tasksApi.get(tid);
  const children = Array.isArray(task?.children)
    ? task.children
      .map((c) => ({ id: Number(c?.id || 0), name: String(c?.name || '') }))
      .filter((c) => c.id > 0)
    : [];
  shiftChildrenByTask.value = {
    ...(shiftChildrenByTask.value || {}),
    [tid]: children,
  };
  return children;
};

const shiftChildrenOptions = (item) => {
  const tid = Number(item?.task_id || 0);
  const currentChildId = Number(props.child?.id || 0);
  const all = Array.isArray(shiftChildrenByTask.value[tid]) ? shiftChildrenByTask.value[tid] : [];
  return all.filter((c) => Number(c.id) !== currentChildId);
};

const openShiftMenu = async (item) => {
  const tid = Number(item?.task_id || 0);
  if (!tid) return;
  if (shiftOpenTaskId.value === tid) {
    closeShiftMenu();
    return;
  }
  shiftError.value = '';
  shiftOpenTaskId.value = tid;
  try {
    await ensureShiftChildren(tid);
  } catch (e) {
    shiftError.value = e?.message || 'Nepodarilo sa načítať deti pre posun';
  }
};

const shiftTaskToChild = async (item, toChildId) => {
  const tid = Number(item?.task_id || 0);
  const cid = Number(toChildId || 0);
  if (!tid || !cid) return;
  if (shiftLoadingTaskId.value) return;
  shiftLoadingTaskId.value = tid;
  shiftError.value = '';
  try {
    const res = await tasksApi.shiftSingleTask(tid, cid);
    closeShiftMenu();
    await loadData();
    emitRuDataChanged({
      type: 'task_shift_single',
      task_id: tid,
      from_child_id: Number(res?.from_child_id || props.child?.id || 0),
      to_child_id: Number(res?.to_child_id || cid),
    });
  } catch (e) {
    shiftError.value = e?.message || 'Presun úlohy sa nepodaril';
  } finally {
    shiftLoadingTaskId.value = 0;
  }
};

async function toggleStatus(item) {
  if (!isToday.value) return;

  const wasDoneToday = isTaskDone(item);
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
    // Keep points balance in sync (kiosk footer)
    try {
      if (childData.value && typeof res?.points_balance === 'number') {
        childData.value.points_balance = res.points_balance;
      }
    } catch {}

    // Update cached overview snapshot (keeps kiosk columns in sync when switching pages)
    try {
      if (childData.value) {
        setCachedOverview(effectiveChildId.value, selectedDay.value, childData.value);
      }
    } catch {}

    emitRuDataChanged({
      type: 'task_status_changed',
      childId: String(effectiveChildId.value || ''),
      day: selectedDay.value,
    });
  } catch (e) {
    item.status = prevStatus;
    item.completed_at = prevCompletedAt;
    error.value = e?.message || 'Chyba pri zmene stavu';
  }
}

const povinne = computed(() => childData.value?.tasks?.povinne?.items || []);
const dobrovolne = computed(() => childData.value?.tasks?.dobrovolne?.items || []);
const pointsBalance = computed(() => {
  const n = Number(childData.value?.points_balance ?? 0);
  return Number.isFinite(n) ? n : 0;
});
const purchasedRewards = computed(() => {
  const purchases = Array.isArray(childData.value?.rewards?.active_purchases)
    ? childData.value.rewards.active_purchases
    : [];
  if (!purchases.length) return [];
  const map = new Map();
  for (const p of purchases) {
    const rid = Number(p?.reward_id ?? p?.rewardId ?? 0);
    if (!rid) continue;
    const prev = map.get(rid) || { rewardId: rid, title: String(p?.title || 'Odmena'), icon: String(p?.icon || '🎁'), count: 0 };
    prev.count += 1;
    // prefer latest title/icon if missing
    if (!prev.title && p?.title) prev.title = String(p.title);
    if ((!prev.icon || prev.icon === '🎁') && p?.icon) prev.icon = String(p.icon);
    map.set(rid, prev);
  }
  return Array.from(map.values()).sort((a, b) => b.count - a.count).slice(0, 8);
});
const totals = computed(() => {
  const all = [...(povinne.value || []), ...(dobrovolne.value || [])];
  const total = all.length;
  let completed = 0;
  for (const it of all) {
    if (isTaskDone(it)) completed++;
  }
  return { total, completed };
});

async function loadData() {
  const cid = effectiveChildId.value;
  if (!cid) return;
  await loadOverview({ childId: cid, day: selectedDay.value });
}

let dayTimer = null;
let refreshTimer = null;
const syncToday = () => {
  const prev = Number(todayDay.value);
  const now = new Date().getDay();
  if (Number(now) === prev) return;
  todayDay.value = now;
};

const isQuietHours = () => {
  try {
    const h = new Date().getHours(); // local time
    // No polling between 22:00 and 06:00
    return h >= 22 || h < 6;
  } catch {
    return false;
  }
};

onMounted(() => {
  loadData();
  try { document.addEventListener('click', onDocClick); } catch {}
  try {
    dayTimer = setInterval(syncToday, 60 * 1000);
    window.addEventListener('focus', syncToday);
    document.addEventListener('visibilitychange', syncToday);
  } catch {}

  // Kiosk: revalidate in the background so changes from other devices show up quickly.
  try {
    refreshTimer = setInterval(() => {
      if (isQuietHours()) return;
      const cid = effectiveChildId.value;
      if (!cid) return;
      // Background refresh: no loading flicker, but data updates when fetched.
      Promise.resolve(loadOverview({ childId: cid, day: selectedDay.value, force: true, background: true }))
        .catch(() => {});
    }, 8000);
  } catch {}
});

onBeforeUnmount(() => {
  try { document.removeEventListener('click', onDocClick); } catch {}
  try {
    if (dayTimer) clearInterval(dayTimer);
  } catch {}
  dayTimer = null;
  try {
    if (refreshTimer) clearInterval(refreshTimer);
  } catch {}
  refreshTimer = null;
  try { window.removeEventListener('focus', syncToday); } catch {}
  try { document.removeEventListener('visibilitychange', syncToday); } catch {}
});

watch(
  () => [effectiveChildId.value, selectedDay.value],
  () => {
    closeShiftMenu();
    loadData();
  }
);
</script>

<style scoped>
.ru-kiosk-col-wrap {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  min-width: 0;
}

.ru-kiosk-col {
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 14px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  overflow: hidden;
  /* Portrait tablet: maximize usable height */
  /* Leave space for the outside footer below the card */
  min-height: calc(100vh - 170px);
  display: flex;
  flex-direction: column;
}

.ru-kiosk-col__header {
  padding: 10px 10px 8px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
  background: transparent;
}

.ru-kiosk-col__who {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  text-align: center;
}

.ru-kiosk-col__avatar {
  width: 54px;
  height: 54px;
  border-radius: 999px;
  overflow: hidden;
  display: grid;
  place-items: center;
  color: #ffffff;
  font-weight: 900;
  flex-shrink: 0;
}
.ru-kiosk-col__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ru-kiosk-col__name {
  font-size: 22px;
  font-weight: 900;
  color: #0f172a;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.ru-kiosk-col__who-btn {
  width: 100%;
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
}

.ru-kiosk-col__body {
  padding: 10px;
  font-weight: 700;
  color: #0f172a;
}
.ru-kiosk-col__body--error {
  color: #b91c1c;
}
.ru-kiosk-col__body--scroll {
  padding: 10px;
  overflow: auto;
  flex: 1;
}

.ru-kiosk-col__group {
  margin-bottom: 10px;
}
.ru-kiosk-col__group-title {
  font-weight: 900;
  color: #0f172a;
  font-size: 14px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 6px;
}

.ru-kiosk-col__list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ru-kiosk-col__task {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  border: 0;
  background: #ffffff;
  border-radius: 12px;
  padding: 8px;
  cursor: pointer;
  text-align: left;
  position: relative;
}

.ru-kiosk-col__dot {
  width: 14px;
  height: 14px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.18);
  flex-shrink: 0;
}
.ru-kiosk-col__dot.on {
  background: var(--ru-accent, #0ea5e9);
}

.ru-kiosk-col__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.ru-kiosk-col__task-title {
  font-weight: 400;
  color: #0f172a;
  font-size: 18px;
  line-height: 1.15;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ru-kiosk-col__task-points {
  color: #d69116;
  font-weight: 300;
  font-size: 14px;
  line-height: 1;
  flex-shrink: 0;
}

.ru-kiosk-col__task-actions {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding-left: 10px;
}

.ru-kiosk-col__rotate-wrap {
  position: relative;
}

.ru-kiosk-col__rotate-btn {
  width: 20px;
  height: 20px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  border-radius: 999px;
  color: #475569;
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.ru-kiosk-col__rotate-btn:hover {
  border-color: #94a3b8;
  color: #0f172a;
}

.ru-kiosk-col__rotate-menu {
  position: absolute;
  right: 0;
  top: 24px;
  min-width: 130px;
  max-width: 220px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.12);
  border-radius: 10px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
  padding: 6px;
  z-index: 30;
  display: grid;
  gap: 4px;
}

.ru-kiosk-col__rotate-option {
  border: 0;
  background: #f8fafc;
  color: #0f172a;
  border-radius: 8px;
  padding: 7px 9px;
  text-align: left;
  font-weight: 700;
  cursor: pointer;
}

.ru-kiosk-col__rotate-option:hover {
  background: #eef2ff;
}

.ru-kiosk-col__rotate-option:disabled {
  opacity: 0.6;
  cursor: default;
}

.ru-kiosk-col__rotate-empty {
  font-size: 12px;
  color: #64748b;
  padding: 4px 6px;
}

.ru-kiosk-col__shift-error {
  margin-top: 8px;
  font-size: 12px;
  color: #b91c1c;
  font-weight: 700;
}

.ru-kiosk-col__outside {
  padding: 0 2px;
}
.ru-kiosk-col__footer-row + .ru-kiosk-col__footer-row {
  margin-top: 10px;
}

.ru-kiosk-col__balance {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 10px;
}
.ru-kiosk-col__balance-label {
  font-size: 14px;
  font-weight: 700;
  color: #64748b;
}
.ru-kiosk-col__balance-value {
  font-size: 22px;
  font-weight: 900;
  color: #d69116;
}

.ru-kiosk-col__purchased-label {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: #64748b;
  text-align: center;
  margin-bottom: 8px;
}
.ru-kiosk-col__purchased-chips {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
}
.ru-kiosk-col__chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.04);
  color: #0f172a;
  max-width: 100%;
}
.ru-kiosk-col__chip-icon {
  font-size: 16px;
  line-height: 1;
}
.ru-kiosk-col__chip-title {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
  max-width: 160px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ru-kiosk-col__chip-count {
  font-size: 13px;
  font-weight: 800;
  color: #64748b;
}

.ru-kiosk-col__empty {
  color: #94a3b8;
  font-weight: 800;
  font-style: italic;
  font-size: 14px;
}

.ru-kiosk-col__done {
  margin-top: 12px;
  padding: 8px 10px;
  border-radius: 12px;
  background: var(--accent-light, rgba(14, 165, 233, 0.12));
  color: #0f172a;
  font-weight: 900;
  text-align: center;
}
</style>

