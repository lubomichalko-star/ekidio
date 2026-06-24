<template>
  <section class="ru-card ru-overview-card">
    <header class="ru-card__header avatars-header" :class="{ 'mobile-open': mobilePickerOpen }">
      <div class="ru-overview-avatars" v-if="!isMobile && validChildren.length">
        <button
          v-for="child in validChildren"
          :key="`overview-child-${child.id}`"
          type="button"
          class="ru-avatar-btn"
          :class="{ active: String(child.id) === selectedChildId }"
          @click="selectChild(child.id)"
        >
          <ChildAvatarBadge
            :child="child"
            :points="childPointsBalance(child.id)"
            :active="String(child.id) === selectedChildId"
          />
          <span class="ru-avatar-name">{{ child?.name || 'Dieťa' }}</span>
        </button>
      </div>

      <div class="ru-overview-mobile-top" v-if="isMobile && selectedChildForHeader">
        <div class="ru-mobile-picker-wrap">
          <button class="ru-mobile-child-btn" type="button" @click="toggleMobilePicker">
            <span
              class="ru-mobile-child-avatar-hit"
              role="button"
              tabindex="0"
              aria-label="Body"
              @click.stop="openPointsModal"
              @keydown.enter.stop.prevent="openPointsModal"
            >
              <ChildAvatarBadge
                :child="selectedChildForHeader"
                :points="childPointsBalance(selectedChildId)"
              />
            </span>
            <span class="ru-mobile-child-name">{{ selectedChildForHeader?.name || 'Dieťa' }}</span>
            <span class="ru-mobile-child-caret" v-if="overviewDropdownChildren.length > 1">▾</span>
          </button>
          <div class="ru-mobile-child-menu" v-if="mobilePickerOpen">
            <button
              v-for="child in overviewDropdownChildren"
              :key="`mobile-child-${child.id}`"
              type="button"
              class="ru-mobile-child-option"
              :class="{ active: String(child.id) === selectedChildId }"
              @click="pickMobileChild(child.id)"
            >
              <span>{{ child?.name || 'Dieťa' }}</span>
              <span
                class="ru-avatar circle ru-mobile-child-option__avatar"
                :class="{ active: String(child.id) === selectedChildId }"
                :style="{
                  background: child?.color || '#0ea5e9',
                  borderColor: child?.color || '#0ea5e9',
                  '--child-accent': child?.color || '#0ea5e9',
                }"
              >
                <span v-if="!child?.avatar_url">{{ String(child?.name || '?').charAt(0) }}</span>
                <img
                  v-else
                  :src="child.avatar_url"
                  :alt="child?.name || 'Dieťa'"
                  loading="eager"
                  decoding="async"
                />
              </span>
            </button>
          </div>
        </div>

        <div class="ru-mobile-avatar-preload" aria-hidden="true">
          <img
            v-for="child in overviewDropdownChildren"
            :key="`mobile-preload-${child.id}`"
            v-if="child?.avatar_url"
            :src="child?.avatar_url || ''"
            alt=""
            loading="eager"
            decoding="async"
          />
        </div>
      </div>

    </header>

    <div class="ru-card__body" v-if="!isParent">
      <p class="ru-error">Prístup len pre rodiča.</p>
    </div>

    <div class="ru-card__body" v-else-if="!appReady || childrenLoading">Načítavam…</div>
    <div class="ru-card__body" v-else-if="error && !children.length">
      <p class="ru-error">{{ error }}</p>
    </div>
    <div class="ru-card__body" v-else-if="!children.length">
      <div class="ru-empty-state">
        <h3>Začnime</h3>
        <p class="ru-empty-state__text">
          Zatiaľ nemáte pridané žiadne deti. Najprv pridajte aspoň jedno dieťa, potom mu priraďte úlohy.
        </p>
        <div class="ru-empty-state__actions">
          <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="goToChildren(true)">
            Pridať dieťa
          </button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToTasks">
            Prejsť na úlohy
          </button>
        </div>
      </div>
    </div>

    <div class="ru-card__body" v-else-if="loading && !childData">Načítavam…</div>
    <div class="ru-card__body" v-else-if="error">
      <p class="ru-error">{{ error }}</p>
    </div>

    <div class="ru-card__body" v-else-if="childData">
      <div class="ru-overview-points" v-if="!isMobileParentSingleMode">
        <button type="button" class="ru-overview-points-btn" @click="openPointsModal" aria-label="Body">
          <PointsBadgeChip
            :points="pointsData.points_balance ?? childData.points_balance ?? 0"
            size="lg"
          />
        </button>
      </div>

      <div class="ru-section ru-earned-rewards" v-if="recentRewards.length">
        <div class="ru-section__header ru-earned-rewards__header">
          <h3>Získané odmeny</h3>
        </div>

        <div class="ru-earned-rewards__strip">
          <button
            v-for="reward in recentRewards"
            :key="reward.id"
            class="ru-earned-reward-card"
            type="button"
            @click="useReward(reward)"
            :disabled="useRewardLoading === reward.id"
            :style="rewardCardStyle(reward)"
          >
            <div class="ru-earned-reward-card__icon">{{ reward.icon || '🎁' }}</div>
            <div class="ru-earned-reward-card__meta">
              <div class="ru-earned-reward-card__title">{{ reward.title }}</div>
            </div>
          </button>
        </div>
      </div>

      <div class="ru-empty-state" v-if="showTasksOnboarding">
        <h3>Pokračuj</h3>
        <p class="ru-empty-state__text">
          Máš pridané dieťa, ale ešte nemáš vytvorené žiadne úlohy. Vytvor úlohy a priraď ich deťom.
        </p>
        <div class="ru-empty-state__actions">
          <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="goToTasks()">
            Pridať úlohy
          </button>
        </div>
      </div>

      <WeekDayBar
        v-if="selectedChildId"
        v-model:selected-day="selectedDay"
        v-model:week-start="selectedWeekStart"
      />

      <div class="ru-task-groups">
        <div class="ru-section" v-for="group in taskGroups" :key="group.key">
          <div class="ru-section__header">
            <h3>{{ group.label }}</h3>
          </div>
          <div v-if="group.tasks.items.length" class="ru-task-list">
            <div v-for="item in group.tasks.items" :key="item.id" class="ru-task-row">
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
                  <div class="ru-task-title">{{ item.task_name || item.name }}</div>
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
                        :key="`overview-shift-${item.id}-${target.id}`"
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
                <div class="ru-task-points-sub">{{ taskPointsEarnLabel(item) }}</div>
              </div>
              <label v-if="isToday" class="ru-task-check">
                <input
                  type="checkbox"
                  :checked="isTaskDone(item)"
                  :disabled="!isToday"
                  @change="toggleStatus(item)"
                />
                <span></span>
              </label>
              <span v-else class="ru-status-dot" :class="{ done: isTaskDone(item) }"></span>
            </div>
          </div>
          <p class="ru-empty" v-else>{{ childName || 'Dieťa' }} nemá na tento deň priradenú žiadnu úlohu</p>
        </div>
      </div>
      <div class="ru-inline-rotate-error" v-if="shiftError">{{ shiftError }}</div>

      <div class="ru-day-total" v-if="totalPossiblePoints > 0">
        Spolu možné získať za tento deň: <strong>{{ totalPossiblePoints }}</strong>
      </div>
    </div>
    <RuModal
      v-if="showPointsModal"
      :title="`Body – ${childName}`"
      @close="closePointsModal"
    >
        <div v-if="pointsLoading">Načítavam…</div>
        <div v-else>
          <div class="ru-stats">
            <div class="ru-stat">
              <div class="ru-stat__label">Celkovo body</div>
              <div class="ru-stat__value">{{ pointsData.points_balance ?? '–' }}</div>
            </div>
            <div class="ru-stat">
              <div class="ru-stat__label">Dnes</div>
              <div class="ru-stat__value">{{ pointsData.points_today ?? '–' }}</div>
            </div>
            <div class="ru-stat">
              <div class="ru-stat__label">Týždeň</div>
              <div class="ru-stat__value">{{ pointsData.points_week ?? '–' }}</div>
              <div class="ru-stat__sub">
                +{{ pointsData.week_summary?.earned || 0 }} / -{{ pointsData.week_summary?.lost || 0 }}
              </div>
            </div>
          </div>

          <div class="ru-inline-adjust">
            <h4 class="ru-modal-section-title">Korigovanie bodov</h4>
            <div class="ru-inline-adjust__form">
              <label class="ru-field">
                <span>Body</span>
                <input v-model.number="adjustPoints" type="number" />
              </label>
              <label class="ru-field">
                <span>Dôvod</span>
                <input v-model="adjustReason" type="text" />
              </label>
              <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="submitAdjust" :disabled="submitAdjustLoading">
                Uprav body
              </button>
            </div>
          </div>

          <h4 class="ru-modal-section-title">História bodov (posledných 7 dní)</h4>
          <div class="ru-table" v-if="pointsData.history && pointsData.history.length">
            <div class="ru-table__head">
              <span>Dátum</span>
              <span>Body</span>
              <span>Popis</span>
              <span>Akcia</span>
            </div>
            <div class="ru-table__row" v-for="entry in pointsData.history" :key="entry.id">
              <span>{{ formatDate(entry.created_at) }}</span>
              <span :class="{ pos: Number(entry.points) > 0, neg: Number(entry.points) < 0 }">
                {{ Number(entry.points) > 0 ? '+' : '' }}{{ Number(entry.points) || 0 }}
              </span>
              <span>{{ formatReason(entry) }}</span>
              <span>
                <button class="ru-btn ru-btn--icon danger" type="button" @click="deletePointsEntry(entry)" aria-label="Zmazať">X</button>
              </span>
            </div>
          </div>
          <p v-else class="ru-empty center">Žiadna história za posledných 7 dní.</p>
        </div>
    </RuModal>

  </section>
</template>

<script setup>
import { computed, onActivated, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api/client';
import { pointsApi } from '../api/points';
import { childrenApi } from '../api/children';
import { tasksApi } from '../api/tasks';
import { getTaskIconUrl } from '../lib/taskIcons';
import { taskPointsEarnLabel } from '../utils/points';
import ChildAvatarBadge from '../components/ChildAvatarBadge.vue';
import PointsBadgeChip from '../components/PointsBadgeChip.vue';
import RuModal from '../components/RuModal.vue';
import WeekDayBar from '../components/WeekDayBar.vue';
import {
  getCachedChildren,
  getCachedOverview,
  getCachedPointsOverview,
  getCachedPointsOverviewAgeMs,
  getCachedTasks,
  invalidateCachedOverview,
  invalidateCachedPointsOverview,
  prefetchChildOverviewWeek,
  setCachedOverview,
  setCachedChildren,
  setCachedPointsOverview,
  setCachedTasks
} from '../state/preloadCache';
import {
  getWeekStartForDate,
  isYmdPastOrToday,
  toYmd,
  ymdForWeekDay,
} from '../utils/days';
import { useChildOverview } from '../composables/useChildOverview';
import { emitRuDataChanged, onRuAuthChanged, onRuDataChanged, onRuForceOverviewRefresh } from '../events/ruEvents';

const props = defineProps({
  role: { type: String, default: 'parent' },
  childId: { type: [String, Number], default: '' },
  localized: { type: Object, default: () => ({}) },
  appReady: { type: Boolean, default: false },
});

const taskIconUrl = (item) => getTaskIconUrl(item?.task_icon || item?.icon || '');

const isParent = computed(() => {
  if (props.role === 'parent') return true;
  if (props.role === 'child') return false;
  return !!(props.localized?.isParent && !props.localized?.forceChild);
});

const router = useRouter();
const goToChildren = (openAdd = false) => {
  try {
    router.push({ name: 'family', query: openAdd ? { add: '1' } : {} });
  } catch {}
};
const goToTasks = (openAdd = false) => {
  try {
    router.push({ name: 'tasks', query: openAdd ? { add: '1' } : {} });
  } catch {}
};

const cachedInitial = getCachedChildren();
const childrenList = ref(Array.isArray(cachedInitial) ? cachedInitial : []);
const children = computed(() => childrenList.value || []);
const childrenLoading = ref(!(Array.isArray(cachedInitial) && cachedInitial.length > 0));

let childrenReqId = 0;
const loadChildren = async () => {
  const reqId = ++childrenReqId;
  if (!isParent.value) {
    childrenList.value = [];
    childrenLoading.value = false;
    return;
  }

  const cached = getCachedChildren();
  if (Array.isArray(cached)) {
    if (reqId === childrenReqId) {
      childrenList.value = cached;
      childrenLoading.value = false;
    }
    if (cached.length) return;
  }

  if (reqId === childrenReqId) childrenLoading.value = true;
  try {
    const list = await childrenApi.list();
    if (reqId !== childrenReqId) return;
    childrenList.value = Array.isArray(list) ? list : [];
    setCachedChildren(childrenList.value);
  } catch (e) {
    if (reqId !== childrenReqId) return;
    childrenList.value = [];
    error.value = e?.message || 'Chyba pri načítaní detí';
  } finally {
    if (reqId === childrenReqId) childrenLoading.value = false;
  }
};

const bootstrapOverview = async () => {
  if (!isParent.value || !props.appReady) return;
  if (!hasExplicitChild.value) {
    applyParentOverviewPreference();
  }
  await loadChildren();
  loadTasksCount();
  if (!selectedChildId.value && children.value.length) {
    selectedChildId.value = normalizeId(children.value[0].id);
  }
};
const normalizeId = (val) => {
  if (val === null || val === undefined) return '';
  const str = String(val);
  return str === '0' ? '' : str;
};
const OVERVIEW_PREF_MODE_KEY = 'ru_overview_mode';
const OVERVIEW_PREF_CHILD_KEY = 'ru_overview_selected_child';
const readOverviewPref = () => {
  try {
    return {
      mode: String(localStorage.getItem(OVERVIEW_PREF_MODE_KEY) || ''),
      childId: normalizeId(localStorage.getItem(OVERVIEW_PREF_CHILD_KEY) || ''),
    };
  } catch {
    return { mode: '', childId: '' };
  }
};
const saveOverviewPref = ({ mode = '', childId = '' } = {}) => {
  try {
    if (mode) localStorage.setItem(OVERVIEW_PREF_MODE_KEY, String(mode));
    if (childId) localStorage.setItem(OVERVIEW_PREF_CHILD_KEY, String(childId));
    if (!childId) localStorage.removeItem(OVERVIEW_PREF_CHILD_KEY);
  } catch {}
};
const clearOverviewPref = () => {
  try {
    localStorage.removeItem(OVERVIEW_PREF_MODE_KEY);
    localStorage.removeItem(OVERVIEW_PREF_CHILD_KEY);
  } catch {}
};

const selectedChildId = ref('');
const mobilePickerOpen = ref(false);
const isMobile = ref(false);
const hasExplicitChild = computed(() => !!normalizeId(props.childId));
const isMobileParentSingleMode = computed(() =>
  isParent.value &&
  !hasExplicitChild.value &&
  isMobile.value
);
const validChildren = computed(() =>
  (Array.isArray(children.value) ? children.value : []).filter(
    (c) => c && typeof c === 'object' && Number(c?.id || 0) > 0
  )
);
const selectedChildForHeader = computed(() => {
  const list = validChildren.value;
  if (!list.length) return null;
  const found = list.find((c) => String(c.id) === String(selectedChildId.value));
  return found || list[0];
});
const overviewDropdownChildren = computed(() => validChildren.value);

const childPointsBalance = (childId) => {
  const cid = String(childId || '');
  if (!cid) return 0;
  if (cid === String(selectedChildId.value)) {
    const live = Number(pointsData.value?.points_balance ?? childData.value?.points_balance);
    if (Number.isFinite(live)) return live;
  }
  const cachedPoints = getCachedPointsOverview(cid);
  if (cachedPoints && typeof cachedPoints.points_balance === 'number') {
    return cachedPoints.points_balance;
  }
  const cachedOverview = getCachedOverview(cid, selectedDay.value, selectedWeekStart.value);
  if (cachedOverview && typeof cachedOverview.points_balance === 'number') {
    return cachedOverview.points_balance;
  }
  return 0;
};

watch(
  () => props.childId,
  (val) => {
    const normalized = normalizeId(val);
    if (normalized) {
      selectedChildId.value = normalized;
    }
  },
  { immediate: true }
);

watch(
  () => children.value,
  (list) => {
    const safeList = (Array.isArray(list) ? list : []).filter((c) => c && typeof c === 'object' && Number(c?.id || 0) > 0);
    if (!safeList.length) {
      selectedChildId.value = '';
      return;
    }

    const exists = safeList.some((c) => String(c.id) === selectedChildId.value);
    if (!selectedChildId.value || !exists) {
      selectedChildId.value = normalizeId(safeList[0].id);
    }
    if (selectedChildId.value) {
      saveOverviewPref({ mode: 'detail', childId: selectedChildId.value });
    }
  },
  { immediate: true }
);
watch(
  () => isMobileParentSingleMode.value,
  (on) => {
    if (!on) return;
    const list = Array.isArray(children.value) ? children.value : [];
    if (!selectedChildId.value && list.length) {
      selectedChildId.value = normalizeId(list[0].id);
    }
  },
  { immediate: true }
);

const selectChild = (id) => {
  const normalized = normalizeId(id);
  if (normalized && normalized !== selectedChildId.value) {
    selectedWeekStart.value = getWeekStartForDate();
    selectedDay.value = todayDay.value;
    selectedChildId.value = normalized;
  }
  mobilePickerOpen.value = false;
  if (normalized) saveOverviewPref({ mode: 'detail', childId: normalized });
};
const pickMobileChild = (id) => {
  selectChild(id);
};
const toggleMobilePicker = () => {
  if (overviewDropdownChildren.value.length < 2) return;
  mobilePickerOpen.value = !mobilePickerOpen.value;
};
const applyParentOverviewPreference = () => {
  if (!isParent.value || hasExplicitChild.value) return;
  const pref = readOverviewPref();
  if (pref.childId) {
    selectedChildId.value = pref.childId;
    return;
  }
};

const todayDay = ref(new Date().getDay());
const selectedWeekStart = ref(getWeekStartForDate());
const selectedDay = ref(todayDay.value);
const isToday = computed(() => ymdForSelectedDay() === toYmd(new Date()));
const isDayPastOrTodayLocal = (day) => isYmdPastOrToday(ymdForWeekDay(selectedWeekStart.value, day));

const loading = ref(false);
const error = ref('');
const childData = ref(null);
const shiftOpenTaskId = ref(0);
const shiftLoadingTaskId = ref(0);
const shiftError = ref('');
const shiftChildrenByTask = ref({});

const tasksCount = ref(null); // null = unknown
const allTasks = ref([]);
let tasksReqId = 0;
const loadTasksCount = async () => {
  const reqId = ++tasksReqId;
  if (!isParent.value) return;
  const cachedTasks = getCachedTasks();
  if (Array.isArray(cachedTasks)) {
    if (reqId === tasksReqId) {
      tasksCount.value = cachedTasks.length;
      allTasks.value = cachedTasks;
    }
    return;
  }
  try {
    const list = await tasksApi.list();
    if (reqId !== tasksReqId) return;
    tasksCount.value = Array.isArray(list) ? list.length : 0;
    allTasks.value = Array.isArray(list) ? list : [];
    if (Array.isArray(list)) setCachedTasks(list);
  } catch {
    // keep null/last value; don't block UI
  }
};
const showTasksOnboarding = computed(() => !!children.value.length && tasksCount.value === 0);
const tasksMetaById = computed(() => {
  const map = {};
  const list = Array.isArray(allTasks.value) ? allTasks.value : [];
  for (const t of list) {
    const id = Number(t?.id || 0);
    if (!id) continue;
    const childrenIds = Array.isArray(t?.children)
      ? t.children.map((c) => Number(c?.id || 0)).filter((cid) => cid > 0)
      : [];
    map[id] = {
      rotation_enabled: Number(t?.rotation_enabled ?? 0),
      children_count: childrenIds.length,
      children_ids: childrenIds,
    };
  }
  return map;
});

function parseMysqlDateTime(input) {
  const raw = String(input || '').trim();
  if (!raw) return null;
  const d = new Date(raw);
  if (!Number.isNaN(d.getTime())) return d;
  const d2 = new Date(raw.replace(' ', 'T'));
  return Number.isNaN(d2.getTime()) ? null : d2;
}

function ymdForSelectedDay() {
  return ymdForWeekDay(selectedWeekStart.value, selectedDay.value);
}

const isTaskDone = (item) => {
  if (!item) return false;
  if (item.status !== 'completed') return false;
  if (!isDayPastOrTodayLocal(selectedDay.value)) return false;
  const completedAt = item.completed_at || item.completedAt || '';
  const raw = String(completedAt || '').trim();
  const completedYmd =
    /^\d{4}-\d{2}-\d{2}/.test(raw) ? raw.slice(0, 10) : toYmd(parseMysqlDateTime(raw));
  const targetYmd = ymdForSelectedDay();
  if (completedYmd && targetYmd) return completedYmd === targetYmd;

  // Fallback for older payloads without week_range.
  const d = parseMysqlDateTime(raw);
  if (!d) return false;
  return d.getDay() === Number(selectedDay.value);
};

const isRotatingTask = (item) => {
  const tid = Number(item?.task_id || 0);
  if (!tid) return false;
  if (Number(item?.rotation_enabled ?? 0) !== 1) return false;
  const meta = tasksMetaById.value?.[tid];
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
  const currentChildId = Number(selectedChildId.value || 0);
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
    await loadChildData({ force: true, background: true });
    emitRuDataChanged({
      type: 'task_shift_single',
      task_id: tid,
      from_child_id: Number(res?.from_child_id || selectedChildId.value || 0),
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
  // Toggle based on per-day done-state (same logic as ChildView).
  const wasDoneToday = isTaskDone(item);
  const newStatus = wasDoneToday ? 'todo' : 'completed';
  const prevStatus = item.status;
  const prevCompletedAt = item.completed_at;
  try {
    // Optimistic UI
    item.status = newStatus;
    if (newStatus === 'completed') {
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
    // Keep points header in sync (if it's already loaded)
    if (pointsData.value) {
      if (typeof res?.points_balance === 'number') pointsData.value.points_balance = res.points_balance;
      if (typeof res?.points_today === 'number') pointsData.value.points_today = res.points_today;
      // Update history so "earned today" reflects instantly without a refetch.
      const pointsAdded = Number(res?.points_added) || 0;
      if (pointsAdded) {
        const prev = Array.isArray(pointsData.value?.history) ? pointsData.value.history : [];
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        const nextEntry = {
          id: `local_${Date.now()}_${Math.random().toString(16).slice(2)}`,
          points: pointsAdded,
          type: 'task',
          created_at: now,
          reason: pointsAdded > 0 ? `Splnená úloha: ${item?.task_name || item?.name || ''}` : `Zrušená úloha: ${item?.task_name || item?.name || ''}`,
        };
        pointsData.value = { ...(pointsData.value || {}), history: [nextEntry, ...prev].slice(0, 120) };
        try {
          setCachedPointsOverview(selectedChildId.value, pointsData.value);
        } catch {}
      }
    }

    // Update cached overview snapshot for this child/day so other pages can reuse it.
    try {
      if (childData.value) {
        setCachedOverview(
          selectedChildId.value,
          selectedDay.value,
          childData.value,
          selectedWeekStart.value
        );
      }
    } catch {}

    // Mark overview as stale for any other kept-alive views, refresh on next open.
    try {
      localStorage.setItem('ru_overview_stale', '1');
    } catch {}

    emitRuDataChanged({
      type: 'task_status_changed',
      childId: String(selectedChildId.value || ''),
      day: selectedDay.value,
    });
  } catch (e) {
    // Revert optimistic UI if API fails
    item.status = prevStatus;
    item.completed_at = prevCompletedAt;
    error.value = e?.message || 'Chyba pri zmene stavu';
  }
}

const OVERVIEW_REVALIDATE_MS = 8000;

const { load: loadOverview } = useChildOverview({
  api,
  dataRef: childData,
  loadingRef: loading,
  errorRef: error,
  revalidateMs: OVERVIEW_REVALIDATE_MS,
});

// Points overview (shown in the header + in modal). Keep it cached so UI doesn't fill in late.
const showPointsModal = ref(false);
const pointsLoading = ref(false);
const pointsData = ref({});
const adjustPoints = ref(0);
const adjustReason = ref('');
const submitAdjustLoading = ref(false);

let pointsReqId = 0;
const loadPointsOverview = async ({ force = false, background = false } = {}) => {
  const cid = String(selectedChildId.value || '');
  if (!cid) return null;
  const myReqId = ++pointsReqId;

  const cached = getCachedPointsOverview(cid);
  const age = getCachedPointsOverviewAgeMs(cid);

  // Instant UI: show cached immediately; refresh in background when stale.
  if (cached && !force) {
    if (myReqId === pointsReqId) {
      pointsData.value = cached;
    }
    if (Number.isFinite(age) && age < OVERVIEW_REVALIDATE_MS) return cached;
    background = true;
  }

  if (!background && myReqId === pointsReqId) pointsLoading.value = true;

  try {
    const fresh = await pointsApi.overview(cid);
    if (myReqId !== pointsReqId) return null;
    pointsData.value = fresh || {};
    setCachedPointsOverview(cid, pointsData.value);
    return pointsData.value;
  } catch (e) {
    if (myReqId !== pointsReqId) return null;
    if (!background) error.value = e?.message || 'Chyba pri načítaní bodov';
    throw e;
  } finally {
    if (!background && myReqId === pointsReqId) pointsLoading.value = false;
  }
};

const loadChildData = async ({ force = false, background = false } = {}) => {
  if (!selectedChildId.value || !isParent.value) return;
  const useBackground = background || !!childData.value;
  await Promise.allSettled([
    loadOverview({
      childId: selectedChildId.value,
      day: selectedDay.value,
      weekStart: selectedWeekStart.value,
      force,
      background: useBackground,
    }),
    loadPointsOverview({ force, background: useBackground || !!pointsData.value?.history }),
  ]);
};

let prefetchReqId = 0;
const prefetchOverviewWeek = (childId, { skipDay = null, weekStart = null } = {}) => {
  const cid = String(childId || '');
  if (!cid) return;
  const reqId = ++prefetchReqId;
  const ws = weekStart || selectedWeekStart.value || getWeekStartForDate();
  prefetchChildOverviewWeek(cid, api, { skipDay, weekStart: ws }).then(() => {
    if (reqId !== prefetchReqId) return;
  });
};

watch(
  () => [selectedChildId.value, selectedDay.value, selectedWeekStart.value],
  () => {
    closeShiftMenu();
    if (selectedChildId.value) {
      loadChildData({ background: !!childData.value });
      prefetchOverviewWeek(selectedChildId.value, {
        skipDay: selectedDay.value,
        weekStart: selectedWeekStart.value,
      });
    }
  },
  { immediate: true }
);

watch(
  () => [isParent.value, props.appReady],
  () => {
    bootstrapOverview();
  },
  { immediate: true }
);

onMounted(() => {
  const syncMobile = () => {
    try {
      isMobile.value = window.innerWidth <= 768;
    } catch {
      isMobile.value = false;
    }
  };
  syncMobile();
  try { window.addEventListener('resize', syncMobile); } catch {}

  const onAuthChanged = () => {
    selectedChildId.value = '';
    clearOverviewPref();
    error.value = '';
    bootstrapOverview();
  };
  const offAuth = onRuAuthChanged(onAuthChanged);

  const onDataChanged = (e) => {
    // After creating/editing children/tasks/rewards, refresh the overview without full page reload.
    // NOTE: task_status_changed is handled optimistically,
    // so we must NOT refetch here, otherwise the UI "refreshes" and feels laggy.
    const detail = e?.detail || {};
    if (detail?.type === 'task_status_changed') {
      return;
    }
    if (detail?.type === 'task_shift_single') {
      loadChildData({ force: true, background: true });
      return;
    }
    mobilePickerOpen.value = false;
    loadChildren();
    loadChildData();
    loadTasksCount();
  };
  const offData = onRuDataChanged(onDataChanged);

  // Allow manual refresh when user clicks "Prehľad" again (route doesn't change).
  const onForce = () => {
    if (isParent.value && !hasExplicitChild.value) {
      applyParentOverviewPreference();
    }
    loadChildData({ force: true, background: true });
  };
  const offForce = onRuForceOverviewRefresh(onForce);

  onBeforeUnmount(() => {
    try { window.removeEventListener('resize', syncMobile); } catch {}
    try { offAuth?.(); } catch {}
    try { offData?.(); } catch {}
    try { offForce?.(); } catch {}
  });
});

onActivated(() => {
  if (!isParent.value || !props.appReady) return;

  // Parent behavior: restore previous overview selection when available.
  if (!hasExplicitChild.value) {
    applyParentOverviewPreference();
  }

  // If something changed elsewhere (e.g. child checked a task), refresh overview on next open.
  try {
    if (localStorage.getItem('ru_overview_stale') === '1') {
      localStorage.removeItem('ru_overview_stale');
      childData.value = null;
      bootstrapOverview().then(() => {
        loadChildData({ force: true, background: true });
      });
      return;
    }
  } catch {}
  if (childData.value) {
    loadChildData({ force: true, background: true });
    return;
  }
  loadChildData({ background: false });
});

onMounted(() => {
  try { document.addEventListener('click', closeShiftMenu); } catch {}
});

// If app stays open across midnight, keep "today" accurate and avoid stale UI.
let dayTimer = null;
const syncToday = () => {
  const prev = Number(todayDay.value);
  const now = new Date().getDay();
  if (Number(now) === prev) return;
  todayDay.value = now;
  if (Number(selectedDay.value) === prev) {
    selectedDay.value = now;
    selectedWeekStart.value = getWeekStartForDate();
    loadChildData({ force: true, background: true });
  }
};

onMounted(() => {
  try {
    dayTimer = setInterval(syncToday, 60 * 1000);
    window.addEventListener('focus', syncToday);
    document.addEventListener('visibilitychange', syncToday);
  } catch {}
});

onBeforeUnmount(() => {
  try { document.removeEventListener('click', closeShiftMenu); } catch {}
  try {
    if (dayTimer) clearInterval(dayTimer);
  } catch {}
  dayTimer = null;
  try { window.removeEventListener('focus', syncToday); } catch {}
  try { document.removeEventListener('visibilitychange', syncToday); } catch {}
});

const rewardMap = computed(() => {
  const map = new Map();
  (childData.value?.rewards?.items || []).forEach((reward) => {
    map.set(String(reward.id), reward);
  });
  return map;
});

const activeRewards = computed(() => {
  const purchases = childData.value?.rewards?.active_purchases || [];
  const map = rewardMap.value;
  return purchases.map((purchase) => {
    const fallback = map.get(String(purchase.reward_id));
    return {
      id: purchase.id,
      rewardId: purchase.reward_id,
      title: purchase.title || fallback?.title || 'Odmena',
      icon: purchase.icon || fallback?.icon || '🎁',
      pointsCost: Number(purchase.points_cost ?? fallback?.points_cost)
    };
  });
});

const recentRewards = computed(() => (activeRewards.value || []).slice(0, 10));

// Pastel backgrounds for reward cards (soft, low-contrast).
const rewardGradients = [
  ['#FDE2E4', '#FAD2E1'], // pink
  ['#FBE7C6', '#FDEBC8'], // peach
  ['#D4F1F4', '#CFFAFE'], // aqua
  ['#D7E3FC', '#E0EAFF'], // blue
  ['#E2D6F9', '#EDE9FE'], // purple
  ['#D3F8E2', '#DCFCE7'], // green
  ['#FFF1C1', '#FEF3C7'], // yellow
];

const hashStr = (input) => {
  const s = String(input ?? '');
  let h = 0;
  for (let i = 0; i < s.length; i++) {
    h = (h * 31 + s.charCodeAt(i)) >>> 0;
  }
  return h;
};

const rewardCardStyle = (reward) => {
  const idx = hashStr(reward?.rewardId ?? reward?.id) % rewardGradients.length;
  const [a, b] = rewardGradients[idx] || rewardGradients[0];
  return {
    background: `linear-gradient(135deg, ${a} 0%, ${b} 100%)`
  };
};

const useRewardLoading = ref(null);
const useReward = async (reward) => {
  if (!reward?.id || useRewardLoading.value === reward.id) return;
  if (!window.confirm('Naozaj chceš uplatniť odmenu?')) return;

  useRewardLoading.value = reward.id;
  try {
    await api.markRewardUsed(reward.id);
    // Optimistic UI: remove the used purchase immediately so user doesn't need refresh.
    // Then revalidate in background to stay consistent with server state.
    try {
      const rewards = childData.value?.rewards;
      if (rewards) {
        const prevPurchases = Array.isArray(rewards.active_purchases) ? rewards.active_purchases : [];
        rewards.active_purchases = prevPurchases.filter((p) => String(p?.id) !== String(reward.id));

        if (rewards.active_counts && reward.rewardId !== undefined && reward.rewardId !== null) {
          const key = String(reward.rewardId);
          const prev = Number(rewards.active_counts[key] ?? 0);
          rewards.active_counts[key] = Math.max(0, prev - 1);
        }

        // Keep cache in sync for the currently selected day snapshot.
        setCachedOverview(
          selectedChildId.value,
          selectedDay.value,
          childData.value,
          selectedWeekStart.value
        );
      }
    } catch {}
    // After marking a reward as used, the server state changes immediately,
    // but our overview is cached (instant UI). Force refetch so user doesn't need refresh.
    try {
      invalidateCachedOverview(selectedChildId.value);
    } catch {}
    // Revalidate quietly to avoid flicker; optimistic update already updated the visible list.
    loadChildData({ force: true, background: true });
  } catch (e) {
    error.value = e?.message || 'Odmenu sa nepodarilo uplatniť';
  } finally {
    useRewardLoading.value = null;
  }
};

const taskGroups = computed(() => {
  const tasks = childData.value?.tasks || {};
  return [
    { key: 'povinne', label: 'Povinné úlohy', tasks: tasks.povinne || { items: [], completed: 0, total: 0 } },
    { key: 'dobrovolne', label: 'Dobrovoľné úlohy', tasks: tasks.dobrovolne || { items: [], completed: 0, total: 0 } }
  ];
});

const totalPossiblePoints = computed(() => {
  let total = 0;
  (taskGroups.value || []).forEach((g) => {
    const items = g?.tasks?.items || [];
    items.forEach((it) => {
      total += Number(it?.task_rating) || 0;
    });
  });
  return total;
});

const childName = computed(() => {
  const id = selectedChildId.value;
  const match = children.value.find((c) => String(c.id) === String(id));
  return match ? match.name : '';
});

const formatDate = (str) => {
  if (!str) return '';
  return new Date(str).toLocaleDateString();
};

const openPointsModal = async () => {
  showPointsModal.value = true;
  // show cached immediately (if any) + revalidate while modal is open
  await loadPointsOverview({ force: false, background: false });
};

const closePointsModal = () => {
  showPointsModal.value = false;
};

const formatReason = (entry) => {
  const raw = String(entry?.reason || entry?.task_name || '—');
  // Strip common prefixes from older reason strings
  return raw
    .replace(/^Splnená úloha:\s*/i, '')
    .replace(/^Zrušená úloha:\s*/i, '')
    .replace(/^Nesplnená povinná úloha:\s*/i, '')
    .replace(/^Nesplnená povinná úloha\s*\(včera\):\s*/i, '')
    .replace(/^Nesplnená sobotná povinná úloha:\s*/i, '')
    .trim();
};

const submitAdjust = async () => {
  if (!selectedChildId.value) return;
  try {
    if (submitAdjustLoading.value) return;
    submitAdjustLoading.value = true;
    const delta = Number(adjustPoints.value) || 0;
    if (!delta) return;
    const abs = Math.abs(delta);
    if (delta > 0) {
      await pointsApi.add(selectedChildId.value, abs, adjustReason.value);
    } else {
      await pointsApi.deduct(selectedChildId.value, abs, adjustReason.value);
    }
    adjustPoints.value = 0;
    adjustReason.value = '';
    try {
      invalidateCachedPointsOverview(selectedChildId.value);
    } catch {}
    await loadPointsOverview({ force: true, background: false });
    await loadChildData();
  } catch (e) {
    error.value = e?.message || 'Chyba pri ukladaní bodov';
  } finally {
    submitAdjustLoading.value = false;
  }
};

const deletePointsEntry = async (entry) => {
  if (!entry?.id) return;
  if (!window.confirm('Naozaj zmazať záznam?')) return;
  try {
    // Optimistic UI: remove immediately so user doesn't see stale rows for a few seconds.
    const prev = Array.isArray(pointsData.value?.history) ? pointsData.value.history : [];
    pointsData.value = {
      ...(pointsData.value || {}),
      history: prev.filter((x) => String(x?.id) !== String(entry.id)),
    };
    try {
      setCachedPointsOverview(selectedChildId.value, pointsData.value);
    } catch {}

    await pointsApi.deleteEntry(entry.id);

    // Ensure we don't show cached data after mutation.
    try {
      invalidateCachedPointsOverview(selectedChildId.value);
    } catch {}
    await loadPointsOverview({ force: true, background: false });
    await loadChildData();
  } catch (e) {
    error.value = e?.message || 'Chyba pri mazaní záznamu';
  }
};

</script>

<style scoped>
.ru-overview-card {
  --ru-card-max-width: 760px;
}

.ru-overview-card .ru-card__body {
  padding-top: 0 !important;
}

.ru-card__header {
  padding: 0;
}

.avatars-header {
  overflow-x: auto;
  padding: 8px 0 4px;
}

.avatars-header.mobile-open {
  overflow: visible;
  position: relative;
  z-index: 70;
}

.ru-overview-avatars {
  display: flex;
  align-items: flex-start;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px 12px;
  width: 100%;
  padding: 4px 0 8px;
}

.ru-overview-mobile-top {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  width: 100%;
  gap: 10px;
  padding: 2px 0;
}

.ru-mobile-picker-wrap {
  position: relative;
  flex: 0 0 auto;
  width: fit-content;
  min-width: 0;
  max-width: none;
}

.ru-mobile-avatar-preload {
  position: absolute;
  width: 0;
  height: 0;
  overflow: hidden;
  pointer-events: none;
  opacity: 0;
}

.ru-mobile-child-btn {
  border: 0;
  background: transparent;
  border-radius: 14px;
  min-height: 0;
  padding: 4px 8px 4px 4px;
  display: flex;
  align-items: center;
  gap: 10px;
  text-align: left;
}

.ru-mobile-child-name {
  order: 2;
  font-weight: 800;
  font-size: 18px;
  line-height: 1.1;
  color: #0f172a;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ru-mobile-child-caret {
  order: 3;
  margin-left: 2px;
  color: #0f172a;
  font-size: 20px;
  font-weight: 900;
  line-height: 1;
}

.ru-mobile-child-btn :deep(.ru-child-avatar-badge) {
  order: 1;
  flex-shrink: 0;
}

.ru-mobile-child-avatar-hit {
  order: 1;
  flex-shrink: 0;
  cursor: pointer;
}

.ru-mobile-child-menu {
  position: absolute;
  left: 0;
  right: auto;
  top: calc(100% + 6px);
  z-index: 120;
  min-width: 100%;
  display: grid;
  gap: 6px;
  animation: ruDropDownIn 140ms ease-out;
}

.ru-mobile-child-option {
  border: 1px solid rgba(15, 23, 42, 0.1);
  background: #ffffff;
  border-radius: 14px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-weight: 700;
  color: #0f172a;
  text-align: left;
  box-shadow: 0 12px 24px -16px rgba(15, 23, 42, 0.35);
}

.ru-mobile-child-option.active {
  background: #e0f2fe;
}

.ru-mobile-child-option__avatar {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 3px solid var(--child-accent, #0ea5e9);
  overflow: hidden;
  display: grid;
  place-items: center;
  color: #ffffff;
  font-size: 16px;
  font-weight: 800;
  flex-shrink: 0;
  box-sizing: border-box;
}
.ru-mobile-child-option__avatar.active {
  box-shadow:
    0 0 0 2px #ffffff,
    0 0 0 5px var(--child-accent, #0ea5e9);
}
.ru-mobile-child-option__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

@keyframes ruDropDownIn {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.ru-overview-kiosk-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 16px;
}

.ru-days-bar--all-children {
  margin-top: 0;
  margin-bottom: 14px;
}

/* In overview mode we want shorter cards than full kiosk. */
.ru-overview-kiosk-grid :deep(.ru-kiosk-col) {
  min-height: 560px;
}

.ru-overview-kiosk-grid :deep(.ru-kiosk-col__task-title) {
  font-size: 16px;
}

.ru-overview-kiosk-grid :deep(.ru-kiosk-col__name) {
  font-size: 20px;
}

.ru-avatar-btn {
  border: 1px solid transparent;
  background: transparent;
  text-align: center;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 12px;
  min-width: 104px;
}
.ru-avatar-btn:not(.active) {
  opacity: 0.55;
}
.ru-avatar-btn.active {
  border-color: transparent;
  background: transparent;
  opacity: 1;
}
.ru-avatar-btn:not(.active) :deep(.ru-child-avatar-badge__avatar img) {
  filter: grayscale(1) saturate(0.1) contrast(0.95);
}
.ru-avatar-btn.active :deep(.ru-child-avatar-badge__avatar img) {
  filter: none;
}
.ru-avatar-name {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  max-width: 110px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ru-card__body {
  font-size: 16px;
}

.ru-overview-points {
  display: flex;
  justify-content: center;
  margin: 16px 0 24px;
}

.ru-overview-points-btn {
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
}

.ru-overview-points-btn:focus-visible {
  outline: 2px solid var(--ru-accent, #0ea5e9);
  outline-offset: 4px;
  border-radius: 999px;
}

/* ru-days-bar / ru-day-pill styles are global (ru-base.css) */

.ru-earned-rewards {
  margin: 24px 0 18px;
}
.ru-earned-rewards__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.ru-earned-rewards__header h3 {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
  text-transform: none;
  letter-spacing: normal;
}
.ru-earned-rewards__strip {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding: 12px 2px 6px;
  scroll-snap-type: x mandatory;
}
.ru-earned-reward-card {
  scroll-snap-align: start;
  flex: 0 0 auto;
  width: 150px;
  border-radius: 18px;
  border: 1px solid #a47313;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  padding: 12px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  background: linear-gradient(135deg, rgb(251, 231, 198) 0%, rgb(198 156 75) 100%) !important;
  box-shadow: none;
}
.ru-earned-reward-card:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.ru-earned-reward-card__icon {
  font-size: 44px;
  line-height: 1;
  filter: none;
}
.ru-earned-reward-card__meta {
  background: transparent;
  border-radius: 14px;
  padding: 0;
  color: #0f172a;
}
.ru-earned-reward-card__title {
  font-weight: 400;
  font-size: 14px;
  line-height: 1.2;
  white-space: normal;
  overflow: visible;
  text-overflow: unset;
  word-break: break-word;
}
.ru-earned-reward-card__cost {
  margin-top: 4px;
  font-weight: 400;
  font-size: 12px;
  opacity: 0.9;
  color: rgba(15, 23, 42, 0.75);
}

.ru-task-groups {
  display: flex;
  flex-direction: column;
  gap: 20px;
  /* background: #fff; */
  /* padding: 0 10px 10px; */
  border-radius: 16px;
  /* border: 1px solid #e3e3e3; */
}

.ru-day-total {
  margin-top: 10px;
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
}
.ru-task-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ru-task-row {
  display: flex;
  align-items: center;
  gap: 24px;
  flex-wrap: nowrap;
  padding: 10px 8px;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  overflow: hidden;
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
  margin: 0;
  color: #0f172a;
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
.ru-task-points-sub {
  margin-top: 2px;
  font-weight: 700;
  font-size: 13px;
  color: #16a34a;
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
.ru-inline-rotate-btn:hover {
  border-color: #94a3b8;
  color: #0f172a;
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
  z-index: 20;
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
.ru-inline-rotate-option:hover {
  background: #eef2ff;
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
.ru-inline-rotate-error {
  margin-top: 8px;
  font-size: 12px;
  color: #b91c1c;
  font-weight: 700;
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
  border-color: var(--ru-accent, #0ea5e9);
  background: var(--ru-accent, #0ea5e9);
}

.ru-task-check {
  width: 36px;
  height: 36px;
}

.ru-section__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.ru-empty {
  margin: 0;
  color: #94a3b8;
}
.ru-empty.center {
  text-align: center;
  width: 100%;
  display: inline-block;
}
.ru-empty.center {
  text-align: center;
  width: 100%;
  display: inline-block;
}
.ru-empty-state {
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 16px;
  padding: 16px;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.ru-empty-state h3 {
  margin: 0 0 6px;
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
}
.ru-empty-state__text {
  margin: 0 0 12px;
  color: #64748b;
  font-weight: 700;
  font-size: 13px;
}
.ru-empty-state__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.ru-error {
  color: #b91c1c;
  font-weight: 600;
}

.ru-coin {
  width: 25px;
  height: 25px;
  object-fit: contain;
}
.ru-modal {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  display: grid;
  place-items: center;
  z-index: 9999;
}
.ru-modal__dialog {
  background: white;
  border-radius: 12px;
  width: min(640px, calc(100vw - 32px));
  max-height: calc(100vh - 40px);
  overflow: auto;
  box-shadow: 0 20px 60px -30px rgba(15, 23, 42, 0.4);
}
.ru-modal__header,
.ru-modal__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}
.ru-modal__header h3 {
  margin: 0;
}
.ru-modal__body {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.ru-modal-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.ru-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.ru-btn {
  background: var(--ru-accent, #0ea5e9);
  color: white;
  border: none;
  padding: 12px 14px;
  border-radius: 14px;
  font-weight: 800;
  cursor: pointer;
}
.ru-btn--primary {
  background: var(--ru-accent, #0ea5e9);
  color: #ffffff;
  box-shadow: 0 20px 40px -20px rgba(14, 165, 233, 0.70);
}
.ru-btn--full {
  width: 100%;
  justify-content: center;
}
.ru-btn.danger {
  background: #ef444400;
  color: #e02020;
  box-shadow: 0 20px 40px -20px #ef444499;
}
.ru-btn--icon {
  width: 36px;
  height: 36px;
  padding: 0;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
}
.ru-btn:disabled {
  background: #93c5fd;
  cursor: not-allowed;
  box-shadow: none;
}
.ru-inline-adjust {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ru-modal-section-title {
  margin: 0;
  padding: 0;
  font-weight: 900;
  font-size: 18px;
  color: #0f172a;
}
.ru-inline-adjust__form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  align-items: end;
  margin-bottom: 20px;
}
.ru-inline-adjust__form .ru-btn {
  grid-column: 1 / -1;
  width: 100%;
}
.ru-stat {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px;
  background: #f8fafc;
}
.ru-stat__label {
  color: #6b7280;
  font-size: 12px;
}
.ru-stat__value {
  font-size: 20px;
  font-weight: 800;
  color: #d69116;
}
.ru-stat__sub {
  color: #475569;
  font-size: 12px;
}
.ru-table {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}
.ru-table__head,
.ru-table__row {
  display: grid;
  grid-template-columns: 160px 80px 1fr 80px;
  padding: 10px;
  gap: 10px;
}
.ru-table__head {
  background: #f1f5f9;
  font-weight: 700;
}
.ru-table__row:nth-child(odd) {
  background: #f8fafc;
}
.ru-table__row span:last-child {
  text-align: right;
}
.ru-modal__footer {
  border-top: 1px solid #e5e7eb;
  justify-content: flex-end;
  gap: 10px;
}
.ru-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ru-field input {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
}
.ru-modal__dialog h4 {
  margin: 0;
}
.pos {
  color: #16a34a;
  font-weight: 700;
}
.neg {
  color: #b91c1c;
  font-weight: 700;
}
.ru-modal__dialog button.ru-link {
  font-size: 20px;
  line-height: 1;
}
@media (max-width: 640px) {
  .ru-table__head,
  .ru-table__row {
    grid-template-columns: 120px 60px 1fr 60px;
  }
  .ru-stat {
    padding: 8px;
  }
  .ru-stat__label {
    font-size: 11px;
  }
  .ru-stat__value {
    font-size: 16px;
  }
  .ru-stat__sub {
    font-size: 11px;
  }
  .ru-modal-section-title {
    font-size: 16px;
  }
  .ru-empty-state__actions {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .ru-earned-rewards__header {
    display: none;
  }

  .avatars-header {
    overflow: visible;
  }

  .ru-overview-kiosk-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  .ru-task-points {
    font-size: 16px;
  }
  .ru-earned-reward-card {
    width: 140px;
  }
}

@media (max-width: 1200px) {
  .ru-overview-kiosk-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  }
}
</style>

