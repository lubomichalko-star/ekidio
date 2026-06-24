<template>
  <section class="ru-card ru-admin-card">
    <div class="ru-card__body" v-if="!isParent">
      <p class="ru-error">Prístup len pre rodiča.</p>
    </div>

    <div class="ru-card__body" v-else-if="loading && !tasks.length">Načítavam…</div>
    <div class="ru-card__body" v-else-if="error">
      <p class="ru-error">{{ error }}</p>
    </div>

    <div class="ru-card__body" v-else>
      <div class="ru-topbar">
        <div class="ru-topbar__main">
          <button
            type="button"
            class="ru-btn ghost ru-filter-toggle"
            @click="filtersOpen = !filtersOpen"
            aria-label="Filter"
            title="Filter"
          >
            <span class="ru-filter-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="22" height="22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 7h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M17 7h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M13 7a2 2 0 1 0 4 0a2 2 0 0 0-4 0Z" stroke="currentColor" stroke-width="2"/>
                <path d="M4 12h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M11 12h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M7 12a2 2 0 1 0 4 0a2 2 0 0 0-4 0Z" stroke="currentColor" stroke-width="2"/>
                <path d="M4 17h7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M15 17h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M11 17a2 2 0 1 0 4 0a2 2 0 0 0-4 0Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </span>
            <span v-if="hasActiveFilters" class="ru-filter-dot" aria-label="Aktívny filter"></span>
          </button>

          <label class="ru-sort">
            <select v-model="sortBy" class="ru-sort__select" aria-label="Zoradiť">
              <option value="az">A–Z</option>
              <option value="points_desc">Body (najviac)</option>
              <option value="points_asc">Body (najmenej)</option>
            </select>
          </label>
        </div>
      </div>

      <div class="ru-filters" v-if="filtersOpen">
        <div class="ru-filter">
          <div class="ru-filter__chips">
            <button
              v-for="c in children"
              :key="c.id"
              type="button"
              class="ru-filter-chip ru-filter-chip--avatar"
              :class="{ active: String(selectedChildId) === String(c.id) }"
              @click="toggleSelectedChild(c.id)"
            >
              <span class="ru-filter-avatar" :style="{ background: c.color || '#0ea5e9' }">
                <span v-if="!c.avatar_url">{{ (c.name || '').charAt(0) }}</span>
                <img v-else :src="c.avatar_url" :alt="c.name || ''" />
              </span>
              <span class="ru-filter-name">{{ c.name }}</span>
            </button>
          </div>
        </div>

        <div class="ru-filter-row">
          <div class="ru-filter">
            <div class="ru-segment ru-segment--filters ru-segment--filters-2">
              <button
                type="button"
                class="ru-segment__item"
                :class="{ active: filterCategory === 'povinne' }"
                @click="toggleFilterCategory('povinne')"
              >
                Povinné
              </button>
              <button
                type="button"
                class="ru-segment__item"
                :class="{ active: filterCategory === 'dobrovolne' }"
                @click="toggleFilterCategory('dobrovolne')"
              >
                Dobrovoľné
              </button>
            </div>
          </div>

          <div class="ru-filter">
            <div class="ru-segment ru-segment--filters ru-segment--filters-2">
              <button
                type="button"
                class="ru-segment__item"
                :class="{ active: filterMode === 'rotate' }"
                @click="toggleFilterMode('rotate')"
              >
                Rotuje
              </button>
              <button
                type="button"
                class="ru-segment__item"
                :class="{ active: filterMode === 'norotate' }"
                @click="toggleFilterMode('norotate')"
              >
                Nerotuje
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="ru-task-list">
        <button
          class="ru-task-item"
          v-for="task in displayTasks"
          :key="task.id"
          type="button"
          @click="editTask(task)"
        >
          <div class="ru-task-item__icon-wrap">
            <img
              v-if="taskIconUrl(task)"
              :src="taskIconUrl(task)"
              alt=""
              class="ru-task-item__icon"
            />
          </div>
          <div class="ru-task-item__body">
            <div class="ru-task-item__title">{{ task.name }}</div>
            <div class="ru-task-item__desc" v-if="task.description">{{ task.description }}</div>
          </div>
          <div class="ru-task-item__points">
            <img :src="coinIcon" alt="" class="ru-coin" />
            <span class="ru-task-item__points-value">{{ task.rating || 0 }}</span>
          </div>
        </button>

        <div class="ru-empty ru-empty--list" v-if="!isEmptyAllTasks && !displayTasks.length">
          Žiadne úlohy podľa filtra
        </div>
      </div>
    </div>

    <div v-if="isParent && !loading && !error" class="ru-fab-stack">
      <div v-if="isEmptyAllTasks" class="ru-fab-hint" aria-hidden="true">
        <svg class="ru-empty-arrow" viewBox="0 0 180 180" fill="none">
          <path
            d="M26 34C54 30 85 42 109 65C128 83 140 107 147 128"
            pathLength="100"
          />
          <path
            d="M128 119L148 137L166 116"
            pathLength="100"
          />
        </svg>
      </div>
      <button class="ru-fab ru-fab--library" type="button" @click="openImportModal" aria-label="Pridať z knižnice">
        <svg class="ru-fab__library-icon" viewBox="0 0 32 32" fill="none" aria-hidden="true">
          <rect x="5" y="6" width="4" height="4" rx="1.2" />
          <rect x="5" y="14" width="4" height="4" rx="1.2" />
          <rect x="5" y="22" width="4" height="4" rx="1.2" />
          <path d="M12 8h10" />
          <path d="M12 16h10" />
          <path d="M12 24h4.5" />
          <circle cx="23.5" cy="23.5" r="6.8" />
          <path d="M23.5 19.9v5.4" />
          <path d="M21.1 22.9l2.4 2.4 2.4-2.4" />
        </svg>
      </button>
      <button class="ru-fab" type="button" @click="startAdd" aria-label="Pridať úlohu">+</button>
    </div>

    <RuModal
      v-if="showModal"
      :title="form.id ? 'Upraviť úlohu' : 'Pridať úlohu'"
      @close="closeModal"
    >
          <div class="ru-form-section ru-form-section--task-main">
            <div class="ru-field" v-if="taskIconOptions.length">
              <span class="ru-field__label">Ikona</span>
              <div class="ru-task-icon-picker">
                <button
                  v-for="opt in taskIconOptions"
                  :key="opt.id"
                  type="button"
                  class="ru-task-icon-picker__btn"
                  :class="{ active: form.icon === opt.id }"
                  :title="opt.id"
                  @click="form.icon = opt.id"
                >
                  <img :src="opt.url" :alt="opt.id" />
                </button>
              </div>
            </div>
            <label class="ru-field">
              <span class="ru-field__label">Názov úlohy</span>
              <input v-model="form.name" type="text" />
            </label>
            <label class="ru-field">
              <span class="ru-field__label">Popis úlohy</span>
              <textarea v-model="form.description"></textarea>
            </label>
            <div class="ru-field ru-field--points">
              <div class="ru-stepper">
                <button type="button" class="ru-circle-btn" @click="decRating" aria-label="Menej bodov">
                  −
                </button>
                <div class="ru-stepper__value" aria-label="Hodnota bodov">
                  <span class="ru-stepper__number">{{ Number(form.rating || 0) }}</span>
                  <img :src="coinIcon" alt="" class="ru-stepper__coin" />
                </div>
                <button type="button" class="ru-circle-btn" @click="incRating" aria-label="Viac bodov">
                  +
                </button>
              </div>
            </div>
          </div>

          <div class="ru-form-section grid">
            <div class="ru-field">
              <div class="ru-segment">
                <button
                  type="button"
                  class="ru-segment__item"
                  :class="{ active: form.task_category !== 'dobrovolne' }"
                  @click="form.task_category = 'povinne'"
                >
                  Povinné
                </button>
                <button
                  type="button"
                  class="ru-segment__item"
                  :class="{ active: form.task_category === 'dobrovolne' }"
                  @click="form.task_category = 'dobrovolne'"
                >
                  Dobrovoľné
                </button>
              </div>
            </div>
            <div class="ru-field">
              <div class="ru-segment ru-segment--filters-2">
                <button
                  type="button"
                  class="ru-segment__item"
                  :class="{ active: assignmentMode === 'rotate' }"
                  @click="assignmentMode = 'rotate'"
                >
                  Rotuje
                </button>
                <button
                  type="button"
                  class="ru-segment__item"
                  :class="{ active: assignmentMode === 'norotate' }"
                  @click="assignmentMode = 'norotate'"
                >
                  Nerotuje
                </button>
              </div>
              <p class="ru-help">
                {{ assignmentMode === 'rotate'
                  ? 'Úloha sa priraďuje 1 dieťaťu a rotuje.'
                  : 'Úloha sa priraďuje vybraným deťom (bez rotácie).' }}
              </p>
            </div>
          </div>

          <div class="ru-form-section">
            <div class="ru-children-select">
              <button
                v-for="c in children"
                :key="c.id"
                type="button"
                class="ru-child-avatar-btn"
                :class="{ active: form.assigned_children.includes(c.id) }"
                @click="toggleChild(c.id)"
              >
                <div class="ru-avatar ru-avatar--pick" :style="{ background: c.color || '#0ea5e9' }">
                  <span v-if="!c.avatar_url">{{ (c.name || '').charAt(0) }}</span>
                  <img v-else :src="c.avatar_url" :alt="c.name || ''" />
                </div>
                <div class="ru-child-avatar-name">{{ c.name }}</div>
              </button>
            </div>
            <p class="ru-error" v-if="modalError">{{ modalError }}</p>
          </div>

          <div class="ru-form-section">
            <div class="ru-days-toggle">
              <button
                v-for="d in days"
                :key="d.value"
                type="button"
                class="ru-day-toggle"
                :class="{ active: form.days_of_week.includes(String(d.value)) }"
                @click="toggleDay(d.value)"
              >
                {{ d.label }}
              </button>
            </div>
          </div>

          <div class="ru-form-section" v-if="form.id && assignmentMode === 'rotate'">
            <div class="ru-field">
              <span>Manuálny posun úlohy na dieťa (aktuálne obdobie)</span>
              <div class="ru-actions-row">
                <select v-model="manualShiftChildId">
                  <option value="" disabled>Vyber dieťa…</option>
                  <option
                    v-for="cid in form.assigned_children"
                    :key="'shift-child-' + cid"
                    :value="String(cid)"
                  >
                    {{ childNameById(cid) }}
                  </option>
                </select>
                <button
                  class="ru-btn ghost"
                  type="button"
                  @click="manualShiftTask"
                  :disabled="manualShiftLoading || !manualShiftChildId || form.assigned_children.length < 2"
                >
                  {{ manualShiftLoading ? 'Presúvam…' : 'Presunúť úlohu' }}
                </button>
              </div>
              <p class="ru-help">Presunie túto úlohu na vybrané dieťa pre aktuálne rotačné obdobie.</p>
              <p class="ru-help" v-if="form.assigned_children.length < 2">Pre posun musí mať rotačná úloha aspoň 2 priradené deti.</p>
            </div>
          </div>
      <template #footer>
          <div class="ru-modal-actions">
            <button
              v-if="form.id"
              class="ru-btn ghost danger"
              @click="deleteTaskById(form.id)"
            >
              Vymazať
            </button>
            <button
              class="ru-btn ru-btn--primary ru-btn--full"
              @click="saveTask"
            >
              {{ form.id ? 'Uložiť' : 'Pridať' }}
            </button>
          </div>
      </template>
    </RuModal>

    <RuModal v-if="showImportModal" title="Knižnica úloh" @close="closeImportModal">
      <div class="ru-form-section">
        <div v-if="taskLibraryItems.length" class="ru-library-list">
          <label v-for="item in taskLibraryItems" :key="item.id" class="ru-library-item">
            <input
              type="checkbox"
              :value="item.id"
              v-model="selectedLibraryTaskIds"
              :disabled="importLoading"
            />
            <div class="ru-library-item__content">
              <div class="ru-library-item__title">{{ item.name }}</div>
              <div class="ru-library-item__meta">
                {{ item.task_category === 'dobrovolne' ? 'Dobrovoľná' : 'Povinná' }}
                ·
                {{ item.rotation_enabled ? 'Rotuje' : 'Bez rotácie' }}
                ·
                {{ item.rating || 0 }} bodov
              </div>
              <div v-if="item.description" class="ru-library-item__desc">{{ item.description }}</div>
            </div>
          </label>
        </div>
        <p v-else-if="!importSourcesLoading" class="ru-card__subtitle">Knižnica úloh je zatiaľ prázdna.</p>
        <p class="ru-error" v-if="importError">{{ importError }}</p>
      </div>
      <template #footer>
        <button class="ru-btn ru-btn--ghost" type="button" @click="closeImportModal" :disabled="importLoading">
          Zrušiť
        </button>
        <button class="ru-btn ru-btn--primary" type="button" @click="runImport" :disabled="importLoading || !selectedLibraryTaskIds.length">
          {{ importLoading ? 'Pridávam…' : 'Pridať z knižnice' }}
        </button>
      </template>
    </RuModal>
  </section>
</template>

<script setup>
import { emitRuDataChanged, onRuDataChanged } from '../events/ruEvents';
import { onMounted, onActivated, onBeforeUnmount, ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { tasksApi } from '../api/tasks';
import { childrenApi } from '../api/children';
import coinPng from '../images/star.png';
import RuModal from '../components/RuModal.vue';
import { getDefaultTaskIconId, getTaskIconUrl, taskIconOptions } from '../lib/taskIcons';
import { getCachedChildren, getCachedTasks, setCachedChildren, setCachedTasks } from '../state/preloadCache';

const props = defineProps({
  role: { type: String, default: 'child' },
  childId: { type: [String, Number], default: '' },
  localized: { type: Object, default: () => ({}) },
});

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const error = ref('');
const tasks = ref([]);
const children = ref([]);
// IMPORTANT: role must come from the app/runtime auth (App.vue + /auth/me), not directly from localStorage.
// Otherwise views can drift after login/logout without reload.
const isParent = computed(() => {
  if (props.role === 'parent') return true;
  if (props.role === 'child') return false;
  return !!(props.localized?.isParent && !props.localized?.forceChild);
});
const coinIcon = coinPng;

const filtersOpen = ref(false);
const sortBy = ref('az'); // az | points_desc | points_asc

const selectedChildId = ref(''); // empty = all
const filterCategory = ref(''); // empty = all | povinne | dobrovolne
const filterMode = ref(''); // empty = all | rotate | norotate

const showModal = ref(false);
const modalError = ref('');
const showImportModal = ref(false);
const importSourcesLoading = ref(false);
const taskLibrary = ref({ title: '', count: 0 });
const taskLibraryItems = ref([]);
const selectedLibraryTaskIds = ref([]);
const importLoading = ref(false);
const importError = ref('');
const manualShiftChildId = ref('');
const manualShiftLoading = ref(false);
const form = ref({
  id: 0,
  name: '',
  description: '',
  rating: 0,
  task_category: 'povinne',
  rotation_enabled: 0,
  shared_task: 0,
  days_of_week: ['1', '2', '3', '4', '5'],
  assigned_children: [],
  icon: getDefaultTaskIconId(),
});

const assignmentMode = computed({
  get() {
    return Number(form.value.rotation_enabled) === 1 ? 'rotate' : 'norotate';
  },
  set(mode) {
    // UI only supports: rotate / no-rotate
    form.value.shared_task = 0;
    form.value.rotation_enabled = mode === 'rotate' ? 1 : 0;
  }
});

const days = [
  { value: '1', label: 'PO' },
  { value: '2', label: 'UT' },
  { value: '3', label: 'ST' },
  { value: '4', label: 'ŠT' },
  { value: '5', label: 'PI' },
  { value: '6', label: 'SO' },
  { value: '0', label: 'NE' },
];

const childNameById = (id) => {
  const nid = Number(id);
  const found = children.value.find((c) => Number(c.id) === nid);
  return found?.name || `#${nid}`;
};

const toggleDay = (val) => {
  const v = String(val);
  const cur = form.value.days_of_week || [];
  if (cur.includes(v)) {
    form.value.days_of_week = cur.filter((x) => x !== v);
  } else {
    form.value.days_of_week = [...cur, v];
  }
};

const clampRating = (n) => Math.max(0, Math.min(100, Number(n || 0)));
const incRating = () => {
  form.value.rating = clampRating((form.value.rating || 0) + 1);
};
const decRating = () => {
  form.value.rating = clampRating((form.value.rating || 0) - 1);
};

const toggleChild = (childId) => {
  const id = Number(childId);
  const cur = Array.isArray(form.value.assigned_children) ? form.value.assigned_children : [];
  if (cur.includes(id)) {
    form.value.assigned_children = cur.filter((x) => x !== id);
  } else {
    form.value.assigned_children = [...cur, id];
  }
  if (Array.isArray(form.value.assigned_children) && form.value.assigned_children.length) {
    modalError.value = '';
  }
};

const toggleSelectedChild = (childId) => {
  const next = String(childId);
  selectedChildId.value = String(selectedChildId.value) === next ? '' : next;
};

const toggleFilterCategory = (cat) => {
  filterCategory.value = filterCategory.value === cat ? '' : cat;
};

const toggleFilterMode = (mode) => {
  filterMode.value = filterMode.value === mode ? '' : mode;
};

const isSharedTask = (task) => Number(task?.shared_task) === 1;
// Consider shared_task as "nerotuje" (legacy data / old mode "pre všetky")
const isRotateTask = (task) => Number(task?.rotation_enabled) === 1 && !isSharedTask(task);

const taskIconUrl = (task) => getTaskIconUrl(task?.icon);

const getAssignedChildIds = (task) => {
  // prefer expanded children objects
  if (Array.isArray(task?.children)) {
    return task.children
      .map((c) => Number(c?.id))
      .filter((x) => Number.isFinite(x) && x > 0);
  }
  // fallback: already an array of ids
  if (Array.isArray(task?.assigned_children)) {
    return task.assigned_children
      .map((id) => Number(id))
      .filter((x) => Number.isFinite(x) && x > 0);
  }
  // fallback: comma-separated
  if (typeof task?.assigned_children === 'string') {
    return task.assigned_children
      .split(',')
      .map((s) => Number(String(s).trim()))
      .filter((x) => Number.isFinite(x) && x > 0);
  }
  return [];
};

const taskMode = (task) => (isRotateTask(task) ? 'rotate' : 'norotate');

const normalizedCategory = (task) => (task?.task_category === 'dobrovolne' ? 'dobrovolne' : 'povinne');

const filteredTasks = computed(() => {
  const child = selectedChildId.value;
  const cat = filterCategory.value;
  const mode = filterMode.value;

  return (tasks.value || []).filter((t) => {
    if (cat && normalizedCategory(t) !== cat) return false;
    if (mode && taskMode(t) !== mode) return false;

    if (child) {
      const childIdNum = Number(child);
      if (!Number.isFinite(childIdNum) || childIdNum <= 0) return true;
      const ids = getAssignedChildIds(t);
      return ids.includes(childIdNum);
    }
    return true;
  });
});

const hasActiveFilters = computed(() => !!(selectedChildId.value || filterCategory.value || filterMode.value));

const displayTasks = computed(() => {
  const list = [...(filteredTasks.value || [])];
  const coll = new Intl.Collator('sk', { sensitivity: 'base' });

  if (sortBy.value === 'points_desc') {
    return list.sort((a, b) => {
      const pa = Number(a?.rating || 0);
      const pb = Number(b?.rating || 0);
      if (pb !== pa) return pb - pa;
      return coll.compare(a?.name || '', b?.name || '');
    });
  }
  if (sortBy.value === 'points_asc') {
    return list.sort((a, b) => {
      const pa = Number(a?.rating || 0);
      const pb = Number(b?.rating || 0);
      if (pa !== pb) return pa - pb;
      return coll.compare(a?.name || '', b?.name || '');
    });
  }
  // default az
  return list.sort((a, b) => coll.compare(a?.name || '', b?.name || ''));
});

const loadData = async ({ force = false, background = false } = {}) => {
  if (!isParent.value) return;

  const cachedTasks = getCachedTasks();
  const cachedChildren = getCachedChildren();
  if (!force && Array.isArray(cachedTasks) && Array.isArray(cachedChildren)) {
    tasks.value = cachedTasks;
    children.value = cachedChildren;
    loading.value = false;
    if (
      selectedChildId.value &&
      !children.value.find((c) => String(c.id) === String(selectedChildId.value))
    ) {
      selectedChildId.value = '';
    }
    loadData({ force: true, background: true });
    return;
  }

  if (!background) {
    loading.value = true;
    error.value = '';
  }
  try {
    [tasks.value, children.value] = await Promise.all([
      tasksApi.list(),
      childrenApi.list()
    ]);
    setCachedTasks(tasks.value);
    setCachedChildren(children.value);
    // keep selected child valid
    if (
      selectedChildId.value &&
      !children.value.find((c) => String(c.id) === String(selectedChildId.value))
    ) {
      selectedChildId.value = '';
    }
  } catch (e) {
    if (!background) error.value = e?.message || 'Chyba pri načítaní úloh';
  } finally {
    if (!background) loading.value = false;
  }
};

const isEmptyAllTasks = computed(() => Array.isArray(tasks.value) && tasks.value.length === 0);

let offData = null;

const openImportModal = async () => {
  showImportModal.value = true;
  importError.value = '';
  taskLibrary.value = { title: '', count: 0 };
  taskLibraryItems.value = [];
  selectedLibraryTaskIds.value = [];
  importSourcesLoading.value = true;
  try {
    const summary = await tasksApi.librarySummary();
    taskLibrary.value = summary && typeof summary === 'object' ? summary : { title: '', count: 0 };
    taskLibraryItems.value = Array.isArray(summary?.items) ? summary.items : [];
  } catch (e) {
    importError.value = e?.message || 'Nepodarilo sa načítať knižnicu úloh';
  } finally {
    importSourcesLoading.value = false;
  }
};

const closeImportModal = () => {
  showImportModal.value = false;
  selectedLibraryTaskIds.value = [];
};

const runImport = async () => {
  if (!selectedLibraryTaskIds.value.length) {
    importError.value = 'Vyber aspoň jednu úlohu z knižnice.';
    return;
  }
  importLoading.value = true;
  importError.value = '';
  try {
    await tasksApi.importFromLibrary(selectedLibraryTaskIds.value);
    closeImportModal();
    await loadData();
    try {
      emitRuDataChanged({ type: 'tasks_imported' });
    } catch {}
  } catch (e) {
    importError.value = e?.message || 'Import sa nepodaril';
  } finally {
    importLoading.value = false;
  }
};

const startAdd = () => {
  modalError.value = '';
  manualShiftChildId.value = '';
  form.value = {
    id: 0,
    name: '',
    description: '',
    rating: 0,
    task_category: 'povinne',
    rotation_enabled: 1,
    shared_task: 0,
    days_of_week: ['1', '2', '3', '4', '5'],
    assigned_children: [],
    icon: getDefaultTaskIconId(),
  };
  showModal.value = true;
};

const editTask = (task) => {
  modalError.value = '';
  manualShiftChildId.value = '';
  form.value = {
    id: task.id,
    name: task.name,
    description: task.description || '',
    rating: task.rating || 0,
    task_category: task.task_category || 'povinne',
    rotation_enabled: isRotateTask(task) ? 1 : 0,
    shared_task: 0,
    days_of_week: (task.days_of_week || '').split(',').filter(Boolean),
    assigned_children: (task.children || []).map((c) => c.id),
    icon: task.icon || getDefaultTaskIconId(),
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  modalError.value = '';
  manualShiftChildId.value = '';
};

const saveTask = async () => {
  try {
    const payload = { ...form.value };
    payload.shared_task = 0;
    // Validate: must select at least one child
    const selected = Array.isArray(payload.assigned_children)
      ? payload.assigned_children.map((x) => Number(x)).filter((x) => Number.isFinite(x) && x > 0)
      : [];
    if (!selected.length) {
      modalError.value = 'Vyber aspoň jedno dieťa.';
      return;
    }
    payload.assigned_children = selected;
    const wasNew = !payload.id;
    const res = await tasksApi.save(payload);
    const taskId = payload.id || (res && res.id);
    showModal.value = false;
    await loadData();
    // If the new task doesn't show up, it's usually because an active filter hides it.
    // Make sure user sees the task they just created.
    if (wasNew && taskId) {
      const visibleNow = (displayTasks.value || []).some((t) => String(t?.id) === String(taskId));
      if (!visibleNow) {
        selectedChildId.value = '';
        filterCategory.value = '';
        filterMode.value = '';
        // Keep sort as-is.
      }
    }
    try {
      const type =
        wasNew && Number(payload.rotation_enabled) === 1
          ? 'task_rotating_added'
          : wasNew && Number(payload.rotation_enabled) === 0
            ? 'task_nonrotating_added'
            : 'task_changed';
      emitRuDataChanged({ type, id: taskId || 0 });
    } catch {}
  } catch (e) {
    error.value = e?.message || 'Chyba pri ukladaní';
  }
};

const manualShiftTask = async () => {
  if (!form.value?.id) return;
  const toChildId = Number(manualShiftChildId.value || 0);
  if (!toChildId) return;
  manualShiftLoading.value = true;
  modalError.value = '';
  try {
    await tasksApi.shiftSingleTask(form.value.id, toChildId);
    await loadData();
    showModal.value = false;
    emitRuDataChanged({ type: 'task_shift_single', id: form.value.id, to_child_id: toChildId });
  } catch (e) {
    modalError.value = e?.message || 'Konflikt pri manuálnom posune úlohy';
  } finally {
    manualShiftLoading.value = false;
  }
};

const deleteTask = async (task) => {
  if (!confirm(`Naozaj zmazať úlohu "${task.name}"?`)) return;
  try {
    await tasksApi.delete(task.id);
    await loadData();
    try {
      emitRuDataChanged({ type: 'task_deleted', id: task.id });
    } catch {}
  } catch (e) {
    error.value = e?.message || 'Chyba pri mazaní';
  }
};

const deleteTaskById = async (id) => {
  if (!confirm('Naozaj zmazať túto úlohu?')) return;
  try {
    await tasksApi.delete(id);
    showModal.value = false;
    await loadData();
    try {
      emitRuDataChanged({ type: 'task_deleted', id });
    } catch {}
  } catch (e) {
    error.value = e?.message || 'Chyba pri mazaní';
  }
};

const labelCategory = (cat) => (cat === 'dobrovolne' ? 'Dobrovoľné' : 'Povinné');
const labelType = () => '';
const isDayActive = (daysString, value) => {
  if (!daysString) return false;
  const arr = daysString.split(',').map((d) => d.trim());
  return arr.includes(String(value));
};

onMounted(async () => {
  if (!isParent) return;
  await loadData();
  handleAddQuery();

  // KeepAlive: this view may stay mounted while user edits children/rewards elsewhere.
  // Listen for cross-view changes and refresh task + children lists.
  offData = onRuDataChanged((e) => {
    const type = e?.detail?.type || '';
    // For any task/child change, reload so assignment UI includes newest children.
    if (
      type === 'child_changed' ||
      type === 'tasks_imported' ||
      String(type || '').startsWith('task_') ||
      String(type || '').startsWith('reset_')
    ) {
      loadData();
    }
  });
});

onBeforeUnmount(() => {
  try { offData?.(); } catch {}
  offData = null;
});

const handleAddQuery = () => {
  // If navigated with ?add=1, open "Add task" modal immediately (onboarding).
  try {
    if (String(route.query?.add || '') === '1') {
      startAdd();
      router.replace({ query: { ...route.query, add: undefined } });
    }
    if (String(route.query?.import || '') === '1') {
      openImportModal();
      router.replace({ query: { ...route.query, import: undefined } });
    }
  } catch {}
};

onActivated(() => {
  if (isParent.value) {
    loadData({ force: true, background: !!tasks.value.length });
  }
  handleAddQuery();
});
</script>

<style scoped>
.ru-admin-card {
  position: relative;
}
.ru-fab-stack {
  position: fixed;
  right: 24px;
  bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  z-index: 50;
}
.ru-fab-hint {
  position: absolute;
  right: 68px;
  bottom: 104px;
  width: 150px;
  height: 150px;
  pointer-events: none;
}
.ru-fab {
  position: static;
  width: 64px;
  height: 64px;
  border-radius: 50%;
  border: none;
  background: var(--ru-accent, #0ea5e9);
  color: white;
  font-size: 32px;
  display: grid;
  place-items: center;
  box-shadow: 0 20px 40px -20px rgba(14, 165, 233, 0.8);
  cursor: pointer;
}
.ru-fab--library {
  font-size: 20px;
}
.ru-fab__library-icon {
  width: 28px;
  height: 28px;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.ru-fab__library-icon rect,
.ru-fab__library-icon circle {
  stroke: currentColor;
}
.ru-empty-arrow {
  width: 100%;
  height: 100%;
  overflow: visible;
}
.ru-empty-arrow path {
  stroke: var(--ru-accent, #0ea5e9);
  stroke-width: 10;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 100;
  stroke-dashoffset: 100;
  animation: ru-empty-arrow-draw 1.5s ease-in-out infinite;
}
.ru-empty-arrow path:last-child {
  animation-delay: 0.12s;
}
@keyframes ru-empty-arrow-draw {
  0% {
    stroke-dashoffset: 100;
    opacity: 0.2;
    transform: translate3d(-8px, -8px, 0);
  }
  45%,
  70% {
    stroke-dashoffset: 0;
    opacity: 1;
    transform: translate3d(0, 0, 0);
  }
  100% {
    stroke-dashoffset: 0;
    opacity: 0.2;
    transform: translate3d(8px, 8px, 0);
  }
}

@media (max-width: 768px) {
  .ru-fab-stack {
    right: 16px;
    bottom: calc(84px + env(safe-area-inset-bottom, 0px));
  }
  .ru-fab-hint {
    right: 58px;
    bottom: 92px;
    width: 124px;
    height: 124px;
  }
  .ru-fab {
    width: 56px;
    height: 56px;
    font-size: 28px;
  }
  .ru-fab__plus {
    font-size: 22px;
  }
  .ru-fab__list {
    width: 16px;
    height: 16px;
  }
}
.ru-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}
.ru-topbar__main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex: 1;
  min-width: 0;
}
.ru-filter-toggle {
  position: relative;
  height: 44px;
  padding: 0 14px;
  border: 0;
  border-radius: 14px;
  font-weight: 800;
}
.ru-filter-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: var(--ru-accent, #0ea5e9);
  display: inline-block;
  margin-left: 8px;
}
.ru-sort {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.ru-filter-icon {
  display: grid;
  place-items: center;
}
.ru-sort__select {
  height: 44px;
  border-radius: 14px;
  padding: 0 12px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  font-weight: 800;
  color: #0f172a;
}

.ru-filters {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 10px 0 14px;
  padding: 12px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.ru-filter-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
@media (max-width: 640px) {
  .ru-filter-row {
    grid-template-columns: 1fr;
  }
}
.ru-filter__label {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin-bottom: 6px;
}
.ru-filter__chips {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}
.ru-filter-chip {
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  border-radius: 999px;
  padding: 8px 12px;
  font-weight: 700;
  color: #0f172a;
  cursor: pointer;
}
.ru-filter-chip.active {
  border-color: var(--ru-accent, #0ea5e9);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.18);
}
.ru-filter-chip--avatar {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 6px 10px 6px 6px;
}
.ru-filter-avatar {
  width: 30px;
  height: 30px;
  border-radius: 999px;
  overflow: hidden;
  display: grid;
  place-items: center;
  color: #fff;
  font-weight: 900;
}
.ru-filter-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ru-filter-name {
  font-size: 13px;
  font-weight: 700;
}
.ru-segment--filters-2 {
  grid-template-columns: 1fr 1fr;
}
.ru-task-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ru-task-item {
  width: 100%;
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 16px;
  padding: 12px 14px;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  text-align: left;
  overflow: hidden;
}
.ru-task-item__icon-wrap {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.ru-task-item__icon {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}
.ru-task-item__body {
  min-width: 0;
  flex: 1;
}
.ru-task-item__title {
  font-weight: 600;
  font-size: 16px;
  color: #0f172a;
  line-height: 1.2;
}
.ru-task-item__desc {
  margin-top: 4px;
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.25;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.ru-task-item__points {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  align-self: flex-start;
  padding-top: 2px;
}
.ru-task-item__points-value {
  font-weight: 700;
  font-size: 16px;
  color: #0f172a;
}
.ru-task-icon-picker {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(52px, 1fr));
  gap: 8px;
}
.ru-task-icon-picker__btn {
  width: 100%;
  aspect-ratio: 1;
  border: 2px solid rgba(15, 23, 42, 0.10);
  border-radius: 12px;
  background: #f8fafc;
  padding: 8px;
  cursor: pointer;
  display: grid;
  place-items: center;
}
.ru-task-icon-picker__btn.active {
  border-color: #0ea5e9;
  background: #e0f2fe;
  box-shadow: 0 0 0 1px rgba(14, 165, 233, 0.25);
}
.ru-task-icon-picker__btn img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.ru-empty--list {
  padding: 14px;
  border-radius: 16px;
  background: #ffffff;
  border: 1px dashed rgba(15, 23, 42, 0.18);
  color: #64748b;
  font-weight: 800;
  text-align: center;
}
.ru-task-card__meta {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 6px;
}
.ru-task-card__actions {
  display: flex;
  gap: 12px;
}
.ru-child-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  background: #e0f2fe;
  color: #075985;
  border-radius: 999px;
  font-size: 12px;
}
.ru-child-chip.selectable {
  cursor: pointer;
  border: 1px solid transparent;
}
.ru-child-chip input {
  margin-right: 6px;
}
.ru-days {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.ru-day {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  background: #f1f5f9;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}
.ru-days-toggle {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-top: 6px;
  flex-wrap: wrap;
}
.ru-day-toggle {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  font-weight: 800;
  color: #0f172a;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.ru-day-toggle.active {
  background: var(--ru-accent, #0ea5e9);
  color: #fff;
  border-color: var(--ru-accent, #0ea5e9);
}
.ru-segment {
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: #f1f5f9;
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 14px;
  padding: 4px;
  gap: 4px;
}
.ru-segment__item {
  border: 0;
  background: transparent;
  padding: 10px 10px;
  border-radius: 12px;
  font-weight: 800;
  color: #0f172a;
  cursor: pointer;
}
.ru-segment__item.active {
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.10);
}
.ru-help {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 13px;
}
.ru-stepper {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
}
.ru-stepper__value {
  min-width: 72px;
  height: 48px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.ru-field--points {
  align-items: flex-start;
}
.ru-stepper__number {
  font-weight: 900;
  font-size: 18px;
  color: #0f172a;
}
.ru-stepper__coin {
  width: 18px;
  height: 18px;
}
.ru-circle-btn {
  width: 48px;
  height: 48px;
  border-radius: 999px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  color: #0f172a;
  font-size: 22px;
  font-weight: 900;
  display: grid;
  place-items: center;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.ru-circle-btn:active {
  transform: translateY(1px);
}
.ru-children-select {
  margin-top: 8px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}
@media (max-width: 360px) {
  .ru-children-select {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.ru-children-select.disabled {
  opacity: 0.6;
}
.ru-child-avatar-btn {
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  border-radius: 16px;
  padding: 10px 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}
.ru-child-avatar-btn.active {
  border-color: var(--ru-accent, #0ea5e9);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.20);
}
.ru-avatar--pick {
  width: 54px;
  height: 54px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  overflow: hidden;
  color: #ffffff;
  font-weight: 800;
  font-size: 18px;
}
.ru-avatar--pick img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ru-child-avatar-name {
  width: 100%;
  text-align: center;
  font-weight: 800;
  font-size: 12px;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
/* ru-modal-actions + ru-btn variants are global (ru-base.css) */

.ru-tag-input {
  margin-top: 8px;
  min-height: 48px;
  padding: 8px 10px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  cursor: text;
}
.ru-tag-input__field {
  border: 0;
  outline: none;
  min-width: 140px;
  flex: 1;
  font-size: 14px;
  padding: 6px 4px;
}
.ru-tag {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(14, 165, 233, 0.12);
  color: #075985;
  border: 1px solid rgba(14, 165, 233, 0.25);
  border-radius: 999px;
  padding: 6px 10px;
  font-weight: 800;
  font-size: 12px;
}
.ru-tag.danger {
  background: rgba(239, 68, 68, 0.12);
  color: #991b1b;
  border-color: rgba(239, 68, 68, 0.25);
}
.ru-tag__x {
  width: 22px;
  height: 22px;
  border-radius: 999px;
  border: 0;
  background: rgba(15, 23, 42, 0.08);
  color: #0f172a;
  font-weight: 900;
  cursor: pointer;
  display: grid;
  place-items: center;
}
.ru-suggest {
  margin-top: 8px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  box-shadow: 0 8px 30px -20px rgba(15, 23, 42, 0.35);
  overflow: hidden;
}
.ru-suggest__item {
  width: 100%;
  text-align: left;
  padding: 12px 12px;
  border: 0;
  background: transparent;
  cursor: pointer;
  font-weight: 800;
  color: #0f172a;
}
.ru-suggest__item:hover {
  background: #f1f5f9;
}
.ru-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
}
.ru-radio-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ru-radio {
  display: flex;
  align-items: center;
  gap: 6px;
}
.ru-modal {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}
.ru-modal__dialog {
  background: white;
  border-radius: 28px;
  width: min(560px, calc(100% - 32px));
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 30px 60px -35px rgba(15, 23, 42, 0.5);
  overflow: hidden;
}
.ru-modal__header {
  padding: 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}
.ru-modal__body {
  padding: 0 24px 24px;
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ru-modal__footer {
  padding: 16px 24px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
.ru-modal__close {
  border: none;
  background: transparent;
  font-size: 26px;
  line-height: 1;
  color: #0f172a;
  cursor: pointer;
}
.ru-form-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f1f5f9;
}
.ru-form-section:last-child {
  border-bottom: none;
}
.ru-form-section.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}
.ru-form-section--task-main {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  align-items: start;
}
.ru-form-section--task-main .ru-field,
.ru-form-section--task-main .ru-stepper {
  min-width: 0;
}
.ru-field__label {
  font-weight: 800;
  color: #0f172a;
  font-size: 14px;
  line-height: 1.2;
}
.ru-modal__footer .ru-btn.ghost {
  background: transparent;
  color: #0f172a;
  border: 1px solid #e5e7eb;
}
.ru-modal__footer .ru-btn.ghost.danger {
  color: #b91c1c;
  border-color: #fecaca;
}

@media (max-width: 640px) {
  .ru-modal__dialog {
    width: 100%;
    height: 100%;
    border-radius: 0;
    max-height: none;
  }
  .ru-modal__body {
    padding: 0 16px 16px;
  }
}
.ru-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ru-field input,
.ru-field textarea,
.ru-field select {
  padding: 12px 14px;
  border-radius: 14px;
  border: 1px solid #e5e7eb;
  font-size: 16px;
  font-weight: 700;
  line-height: 1.35;
  font-family: inherit;
  color: #0f172a;
  width: 100%;
  box-sizing: border-box;
}
.ru-field textarea {
  min-height: 92px;
  resize: vertical;
}
.ru-field input::placeholder,
.ru-field textarea::placeholder,
.ru-field select::placeholder {
  color: #94a3b8;
  font-weight: 600;
}
.ru-input {
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 14px;
}
.ru-list {
  max-height: 220px;
  overflow: auto;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ru-list label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}
.ru-library-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 8px;
}
.ru-library-item {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 12px;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-radius: 14px;
  background: #fff;
}
.ru-library-item input {
  margin-top: 3px;
}
.ru-library-item__content {
  min-width: 0;
  flex: 1;
}
.ru-library-item__title {
  font-weight: 800;
  color: #0f172a;
}
.ru-library-item__meta {
  margin-top: 4px;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}
.ru-library-item__desc {
  margin-top: 4px;
  color: #475569;
  font-size: 13px;
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
.ru-badge.ghost {
  background: #e5e7eb;
  color: #374151;
}
.ru-pill {
  display: none;
}

.ru-day-pill {
  width: 35px;
  height: 35px;
  padding: 0;
  border-radius: 999px;
  background: #e5e7eb;
  color: #cdcdcd;
  font-weight: 500;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.ru-day-pill.active {
  background: var(--ru-accent, #0ea5e9);
  color: white;
}

.ru-points {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 800;
  color: #d69116;
}
.ru-points__value {
  font-size: 18px;
}
.ru-coin {
  width: 25px;
  height: 25px;
  object-fit: contain;
}
.ru-children-avatars {
  display: flex;
  gap: 6px;
  margin-top: 8px;
}
.ru-avatar--sm {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  color: white;
  font-weight: 700;
  overflow: hidden;
}
.ru-avatar--sm img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ru-task-card__desc {
  margin: 0;
  color: #4b5563;
  font-size: 13px;
}
</style>

