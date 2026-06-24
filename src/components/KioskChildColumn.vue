<template>
  <div
    class="ru-kiosk-col-wrap"
    :style="{
      '--accent': accentColor,
      '--accent-light': accentLight,
      '--child-accent': accentColor,
    }"
  >
    <section class="ru-kiosk-col">
      <header class="ru-kiosk-col__header">
        <button class="ru-kiosk-col__who ru-kiosk-col__who-btn" type="button" @click="emitOpenDetail">
          <ChildAvatarBadge :child="child" :points="pointsBalance" />
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
        <div class="ru-kiosk-col__tasks">
          <div class="ru-kiosk-col__group ru-kiosk-col__group--povinne">
            <div v-if="povinne.length" class="ru-task-list">
            <div
              v-for="item in povinne"
              :key="item.id"
              class="ru-task-row"
              :class="{ 'is-done': isTaskDone(item) }"
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
                <div class="ru-task-title-wrap">
                  <div class="ru-task-title">{{ item.task_name }}</div>
                  <div class="ru-inline-rotate-wrap" v-if="isRotatingTask(item)">
                    <button
                      type="button"
                      class="ru-inline-rotate-btn"
                      title="Presunúť rotačnú úlohu na iné dieťa"
                      @click.stop="openShiftMenu(item)"
                    >
                      →
                    </button>
                    <div
                      v-if="shiftOpenTaskId === Number(item.task_id || 0)"
                      class="ru-inline-rotate-menu"
                      @click.stop
                    >
                      <div class="ru-inline-rotate-hint">Presunúť úlohu na</div>
                      <button
                        v-for="target in shiftChildrenOptions(item)"
                        :key="`povinne-shift-${item.id}-${target.id}`"
                        type="button"
                        class="ru-inline-rotate-option"
                        :disabled="shiftLoadingTaskId === Number(item.task_id || 0)"
                        @click.stop="shiftTaskToChild(item, target.id)"
                      >
                        {{ target.name }}
                      </button>
                      <div class="ru-inline-rotate-empty" v-if="!shiftChildrenOptions(item).length">
                        Úloha má len jedno dieťa
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <label v-if="isToday" class="ru-task-check">
                <input
                  type="checkbox"
                  :checked="isTaskDone(item)"
                  @change="toggleStatus(item)"
                />
                <span></span>
              </label>
              <span v-else class="ru-status-dot" :class="{ done: isTaskDone(item) }"></span>
            </div>
            </div>
            <div v-else class="ru-kiosk-col__empty">Žiadne povinné úlohy.</div>
          </div>

          <div class="ru-kiosk-col__group ru-kiosk-col__group--dobrovolne">
            <div class="ru-kiosk-col__group-title">Dobrovoľné</div>
            <div v-if="dobrovolne.length" class="ru-task-list">
            <div
              v-for="item in dobrovolne"
              :key="item.id"
              class="ru-task-row"
              :class="{ 'is-done': isTaskDone(item) }"
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
                <div class="ru-task-title-wrap">
                  <div class="ru-task-title">{{ item.task_name }}</div>
                  <div class="ru-inline-rotate-wrap" v-if="isRotatingTask(item)">
                    <button
                      type="button"
                      class="ru-inline-rotate-btn"
                      title="Presunúť rotačnú úlohu na iné dieťa"
                      @click.stop="openShiftMenu(item)"
                    >
                      →
                    </button>
                    <div
                      v-if="shiftOpenTaskId === Number(item.task_id || 0)"
                      class="ru-inline-rotate-menu"
                      @click.stop
                    >
                      <div class="ru-inline-rotate-hint">Presunúť úlohu na</div>
                      <button
                        v-for="target in shiftChildrenOptions(item)"
                        :key="`dobrovolne-shift-${item.id}-${target.id}`"
                        type="button"
                        class="ru-inline-rotate-option"
                        :disabled="shiftLoadingTaskId === Number(item.task_id || 0)"
                        @click.stop="shiftTaskToChild(item, target.id)"
                      >
                        {{ target.name }}
                      </button>
                      <div class="ru-inline-rotate-empty" v-if="!shiftChildrenOptions(item).length">
                        Úloha má len jedno dieťa
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <label v-if="isToday" class="ru-task-check">
                <input
                  type="checkbox"
                  :checked="isTaskDone(item)"
                  @change="toggleStatus(item)"
                />
                <span></span>
              </label>
              <span v-else class="ru-status-dot" :class="{ done: isTaskDone(item) }"></span>
            </div>
            </div>
            <div v-else class="ru-kiosk-col__empty">Žiadne dobrovoľné úlohy.</div>
          </div>
        </div>

        <div class="ru-kiosk-col__shift-error" v-if="shiftError">{{ shiftError }}</div>

        <div class="ru-kiosk-col__done" v-if="totals.total > 0 && totals.completed === totals.total">
          Hotovo!
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api } from '../api/client';
import { tasksApi } from '../api/tasks';
import ChildAvatarBadge from './ChildAvatarBadge.vue';
import { useChildOverview } from '../composables/useChildOverview';
import { getTaskIconUrl } from '../lib/taskIcons';
import { setCachedOverview } from '../state/preloadCache';
import { emitRuDataChanged } from '../events/ruEvents';
import { getWeekStartForDate, toYmd, ymdForWeekDay } from '../utils/days';

const props = defineProps({
  child: { type: Object, required: true },
  day: { type: Number, default: () => new Date().getDay() },
  tasksMetaById: { type: Object, default: () => ({}) },
  allChildIds: { type: Array, default: () => [] },
});
const emit = defineEmits(['open-detail', 'layout-change']);

const notifyLayoutChange = () => {
  try {
    emit('layout-change');
  } catch {}
};

const loading = ref(true);
const error = ref('');
const childData = ref(null);
const shiftOpenTaskId = ref(0);
const shiftLoadingTaskId = ref(0);
const shiftError = ref('');
const shiftChildrenByTask = ref({});

const accentColor = computed(() => props.child?.color || '#0ea5e9');
const accentLight = computed(() => `${accentColor.value}33`);
const taskIconUrl = (item) => getTaskIconUrl(item?.task_icon || item?.icon || '');

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
const weekStart = computed(() => childData.value?.week_range?.start || getWeekStartForDate());
const isToday = computed(
  () => ymdForWeekDay(weekStart.value, selectedDay.value) === toYmd(new Date())
);

function parseMysqlDateTime(input) {
  const raw = String(input || '').trim();
  if (!raw) return null;
  const d = new Date(raw);
  if (!Number.isNaN(d.getTime())) return d;
  const d2 = new Date(raw.replace(' ', 'T'));
  return Number.isNaN(d2.getTime()) ? null : d2;
}

function ymdForSelectedDay() {
  return ymdForWeekDay(weekStart.value, selectedDay.value);
}

const isTaskDone = (item) => {
  if (!item) return false;
  if (item.status !== 'completed') return false;
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

const isRotatingTask = (item) => {
  const tid = Number(item?.task_id || 0);
  const meta = tid ? props.tasksMetaById?.[tid] : null;
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
    try {
      if (childData.value && typeof res?.points_balance === 'number') {
        childData.value.points_balance = res.points_balance;
      }
    } catch {}

    try {
      if (childData.value) {
        setCachedOverview(
          effectiveChildId.value,
          selectedDay.value,
          childData.value,
          weekStart.value
        );
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
  await loadOverview({
    childId: cid,
    day: selectedDay.value,
    weekStart: getWeekStartForDate(),
  });
}

let refreshTimer = null;

onMounted(() => {
  loadData();
  try { document.addEventListener('click', onDocClick); } catch {}

  try {
    refreshTimer = setInterval(() => {
      const h = new Date().getHours();
      if (h >= 22 || h < 6) return;
      const cid = effectiveChildId.value;
      if (!cid) return;
      Promise.resolve(
        loadOverview({
          childId: cid,
          day: selectedDay.value,
          weekStart: getWeekStartForDate(),
          force: true,
          background: true,
        })
      ).catch(() => {});
    }, 8000);
  } catch {}
});

onBeforeUnmount(() => {
  try { document.removeEventListener('click', onDocClick); } catch {}
  try {
    if (refreshTimer) clearInterval(refreshTimer);
  } catch {}
  refreshTimer = null;
});

watch(
  () => [effectiveChildId.value, selectedDay.value],
  () => {
    closeShiftMenu();
    loadData();
  }
);

watch(
  () => [loading.value, povinne.value.length, dobrovolne.value.length],
  () => {
    if (loading.value) return;
    nextTick(notifyLayoutChange);
  }
);
</script>

<style scoped>
.ru-kiosk-col-wrap {
  display: flex;
  flex-direction: column;
  width: 100%;
  min-width: 0;
  height: 100%;
  min-height: 0;
}

.ru-kiosk-col {
  background: #e6e3db;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 14px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  overflow: hidden;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.ru-kiosk-col__header {
  padding: 12px 10px 10px;
  border-bottom: 0 solid rgba(15, 23, 42, 0.08);
  background: transparent;
}

.ru-kiosk-col__who {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-align: center;
}

.ru-kiosk-col__name {
  font-size: 18px;
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
  min-height: 0;
}

.ru-kiosk-col__tasks {
  display: block;
}

.ru-kiosk-col__group {
  margin-bottom: 0;
}

.ru-kiosk-col__group--povinne {
  box-sizing: border-box;
}

.ru-kiosk-col__group--dobrovolne {
  padding-top: 16px;
}

.ru-kiosk-col__group-title {
  font-weight: 900;
  color: #0f172a;
  font-size: 14px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.ru-task-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ru-task-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: nowrap;
  padding: 8px 6px;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 1px 1px 2px #0f172a26;
  overflow: hidden;
}

.ru-task-row.is-done .ru-task-title {
  color: #94a3b8;
  text-decoration: line-through;
}

.ru-task__icon-wrap {
  width: 40px;
  height: 40px;
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
  margin: 0;
  color: #0f172a;
  font-size: 15px;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

.ru-task-title-wrap {
  min-width: 0;
  display: inline-flex;
  align-items: center;
  gap: 2px;
  max-width: 100%;
}

.ru-task-check {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  cursor: pointer;
}

.ru-status-dot {
  width: 24px;
  height: 24px;
  border-radius: 999px;
  border: 2px solid #d1d5db;
  background: #f8fafc;
  flex-shrink: 0;
}
.ru-status-dot.done {
  border-color: var(--child-accent, var(--ru-accent, #0ea5e9));
  background: var(--child-accent, var(--ru-accent, #0ea5e9));
}

.ru-inline-rotate-wrap {
  position: relative;
  flex-shrink: 0;
}
.ru-inline-rotate-btn {
  width: 18px;
  height: 18px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  border-radius: 999px;
  color: #475569;
  font-size: 13px;
  line-height: 1;
  font-weight: 900;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.ru-inline-rotate-menu {
  position: absolute;
  left: 0;
  top: 22px;
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
.ru-inline-rotate-hint {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  padding: 2px 6px 4px;
}
.ru-inline-rotate-option {
  border: 0;
  background: #f8fafc;
  color: #0f172a;
  border-radius: 8px;
  padding: 7px 9px;
  text-align: left;
  font-weight: 700;
  cursor: pointer;
}
.ru-inline-rotate-option:disabled {
  opacity: 0.6;
  cursor: default;
}
.ru-inline-rotate-empty {
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
