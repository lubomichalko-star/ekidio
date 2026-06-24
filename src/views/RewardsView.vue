<template>
  <section
    class="ru-card"
    :class="{ 'ru-admin-card': isParent }"
    :style="{
      '--ru-card-max-width': isParent ? '760px' : '560px',
      '--accent': accentColor,
      '--accent-light': accentLight
    }"
  >
    <header class="ru-card__header" v-if="!isParent">
      <div class="ru-header-left">
        <div class="ru-avatar circle" :style="{ background: childDisplay?.color || '#0ea5e9' }">
          <span v-if="!childDisplay?.avatar_url">
            {{ childDisplay?.name ? childDisplay.name.charAt(0) : '?' }}
          </span>
          <img v-else :src="childDisplay.avatar_url" alt="avatar" />
        </div>
        <div class="ru-header-info">
          <h2>{{ childDisplay?.name || 'Dieťa' }}</h2>
        </div>
      </div>
      <div class="ru-header-actions">
        <div class="ru-chip ru-chip--points lg">
          <strong>{{ childData?.points_balance ?? '–' }}</strong>
          <img :src="coinIcon" alt="coin" class="ru-coin" />
        </div>
      </div>
    </header>

    <template v-if="isParent">
      <div class="ru-card__body" v-if="loading && !rewards.length">Načítavam…</div>
      <div class="ru-card__body" v-else-if="error">
        <p class="ru-error">{{ error }}</p>
      </div>
      <div class="ru-card__body" v-else>
        <div class="ru-task-list">
          <button
            class="ru-task-item"
            v-for="reward in rewards"
            :key="reward.id"
            type="button"
            @click="editReward(reward)"
          >
            <div class="ru-task-item__icon-wrap ru-task-item__icon-wrap--emoji">
              <span aria-hidden="true">{{ reward.icon || '🎁' }}</span>
            </div>
            <div class="ru-task-item__body">
              <div class="ru-task-item__title">{{ reward.title }}</div>
              <div class="ru-task-item__desc" v-if="reward.details">{{ reward.details }}</div>
            </div>
            <div class="ru-task-item__points">
              <img :src="coinIcon" alt="" class="ru-coin" />
              <span class="ru-task-item__points-value">{{ reward.points_cost }}</span>
            </div>
          </button>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="ru-card__body" v-if="childError">
        <p class="ru-error">{{ childError }}</p>
      </div>
      <div class="ru-card__body" v-else-if="childLoading">Načítavam…</div>
      <div class="ru-card__body" v-else>
        <div class="ru-task-list">
          <div
            class="ru-task-item ru-reward-row"
            v-for="reward in childRewardsSorted"
            :key="reward.id"
            :class="{ disabled: !canBuy(reward), purchased: activeCount(reward.id) > 0 }"
          >
            <div class="ru-task-item__icon-wrap ru-task-item__icon-wrap--emoji">
              <span aria-hidden="true">{{ reward.icon || '🎁' }}</span>
            </div>
            <div class="ru-task-item__body">
              <div class="ru-task-item__title">{{ reward.title }}</div>
              <div class="ru-task-item__desc" v-if="reward.details">{{ reward.details }}</div>
              <div class="ru-reward-row__actions">
                <button
                  class="ru-btn-buy"
                  :disabled="!canBuy(reward) || purchaseLoading === reward.id"
                  @click="purchaseReward(reward)"
                >
                  <span v-if="purchaseLoading === reward.id">...</span>
                  <span v-else>{{ canBuy(reward) ? 'Kúpiť' : 'Málo bodov' }}</span>
                </button>
                <button
                  class="ru-btn-buy ru-btn-buy--use"
                  v-if="activeCount(reward.id) > 0"
                  :disabled="useLoading === reward.id || !firstActivePurchaseId(reward.id)"
                  @click="usePurchasedReward(reward)"
                >
                  <span v-if="useLoading === reward.id">...</span>
                  <span v-else>Uplatniť</span>
                </button>
              </div>
            </div>
            <div class="ru-task-item__points">
              <span class="ru-reward-row__badge" v-if="activeCount(reward.id) > 0">
                {{ activeCount(reward.id) }}×
              </span>
              <img :src="coinIcon" alt="" class="ru-coin" />
              <span class="ru-task-item__points-value">{{ reward.points_cost }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-if="isParent && !loading && !error" class="ru-fab-stack">
      <div v-if="!rewards.length" class="ru-fab-hint" aria-hidden="true">
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
      <button class="ru-fab" type="button" @click="startAdd" aria-label="Pridať odmenu">+</button>
    </div>

    <RuModal
      v-if="showModal"
      :title="form.id ? 'Upraviť odmenu' : 'Pridať odmenu'"
      @close="closeModal"
    >
          <div class="ru-form-section ru-form-section--reward-main">
            <label class="ru-field">
              <span class="ru-field__label">Názov odmeny</span>
              <input v-model="form.title" type="text" />
            </label>
            <label class="ru-field">
              <span class="ru-field__label">Popis odmeny</span>
              <textarea v-model="form.details"></textarea>
            </label>
          </div>
          <div class="ru-form-section grid">
            <label class="ru-field">
              <span class="ru-field__label">Ikona</span>
              <input v-model="form.icon" type="text" placeholder="🎁" />
            </label>
            <label class="ru-field ru-field--points">
              <input v-model.number="form.points_cost" type="number" min="0" placeholder="Body" />
            </label>
          </div>
      <template #footer>
          <div class="ru-modal-actions">
            <button
              v-if="form.id"
              class="ru-btn ghost danger"
              @click="deleteReward(form)"
            >
              Vymazať
            </button>
            <button class="ru-btn ru-btn--primary ru-btn--full" @click="saveReward">
              {{ form.id ? 'Uložiť' : 'Pridať' }}
            </button>
          </div>
      </template>
    </RuModal>

    <RuModal v-if="showImportModal" title="Knižnica odmien" @close="closeImportModal">
      <div class="ru-form-section">
        <div v-if="rewardLibraryItems.length" class="ru-library-list">
          <label v-for="item in rewardLibraryItems" :key="item.id" class="ru-library-item">
            <input
              type="checkbox"
              :value="item.id"
              v-model="selectedLibraryRewardIds"
              :disabled="importLoading"
            />
            <div class="ru-library-item__content">
              <div class="ru-library-item__title">
                <span class="ru-library-item__icon">{{ item.icon || '🎁' }}</span>
                {{ item.title }}
              </div>
              <div class="ru-library-item__meta">
                {{ item.category || 'Bez kategórie' }} · {{ item.points_cost || 0 }} bodov
              </div>
              <div v-if="item.details" class="ru-library-item__desc">{{ item.details }}</div>
            </div>
          </label>
        </div>
        <p v-else-if="!importSourcesLoading" class="ru-card__subtitle">Knižnica odmien je zatiaľ prázdna.</p>
        <p class="ru-error" v-if="importError">{{ importError }}</p>
      </div>
      <template #footer>
        <button class="ru-btn ru-btn--ghost" type="button" @click="closeImportModal" :disabled="importLoading">
          Zrušiť
        </button>
        <button class="ru-btn ru-btn--primary" type="button" @click="runImport" :disabled="importLoading || !selectedLibraryRewardIds.length">
          {{ importLoading ? 'Pridávam…' : 'Pridať z knižnice' }}
        </button>
      </template>
    </RuModal>
  </section>
</template>

<script setup>
import { emitRuDataChanged } from '../events/ruEvents';
import { computed, onMounted, onActivated, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { rewardsApi } from '../api/rewards';
import { api } from '../api/client';
import RuModal from '../components/RuModal.vue';
import { getCachedRewards, setCachedRewards, syncChildOverviewSnapshot } from '../state/preloadCache';
import { useEffectiveChildId, useChildIdLoadTrigger } from '../composables/useEffectiveChildId';
import { useKeepAliveChildOverview } from '../composables/useKeepAliveChildOverview';

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
    default: 'child',
  },
  childId: {
    type: [String, Number],
    default: '',
  },
  localized: {
    type: Object,
    default: () => ({}),
  },
  appReady: {
    type: Boolean,
    default: false,
  },
});

const route = useRoute();
const router = useRouter();

const localized = computed(() => props.localized || {});
const children = computed(() => localized.value.children || []);
const localAccent = ref(safeLsGet('ru-accent'));
const parentAccentFixed = '#5abb6f';
import coinPng from '../images/star.png';
const coinIcon = coinPng;
const effectiveChildId = useEffectiveChildId({
  childId: computed(() => props.childId),
  route,
  localized,
});

const isParent = computed(() => {
  if (props.role === 'parent') return true;
  if (props.role === 'child') return false;
  if (localized.value && localized.value.isParent && !localized.value.forceChild) return true;
  return false;
});

const childDisplay = computed(() => {
  const fromData = childData.value?.child;
  if (fromData) return fromData;
  const id = effectiveChildId.value;
  if (id && children.value?.length) {
    const found = children.value.find((c) => String(c.id) === String(id));
    if (found) return found;
  }
  return children.value[0] || null;
});

const loading = ref(true);
const error = ref('');
const rewards = ref([]);

const showModal = ref(false);
const form = ref({
  id: 0,
  title: '',
  category: '',
  details: '',
  icon: '🎁',
  points_cost: 0,
});

const childLoading = ref(true);
const childError = ref('');
const purchaseLoading = ref(null);
const useLoading = ref(null);

const {
  childData,
  loading: childOverviewLoading,
  error: childOverviewError,
  loadOverview: loadChildOverview,
  applySnapshot: applyChildOverviewSnapshot,
  applyPatch: applyChildOverviewPatch,
} = useKeepAliveChildOverview({
  api,
  effectiveChildId,
  enabled: computed(() => !isParent.value),
});

watch(childOverviewLoading, (v) => {
  childLoading.value = v;
});
watch(childOverviewError, (v) => {
  childError.value = v;
});

const showImportModal = ref(false);
const importSourcesLoading = ref(false);
const rewardLibrary = ref({ title: '', count: 0 });
const rewardLibraryItems = ref([]);
const selectedLibraryRewardIds = ref([]);
const importLoading = ref(false);
const importError = ref('');

const accentColor = computed(() =>
  isParent.value
    ? parentAccentFixed
    : childDisplay.value?.color || localAccent.value || '#0ea5e9'
);
const accentLight = computed(() => `${accentColor.value}33`);

const loadData = async ({ force = false, background = false } = {}) => {
  if (!isParent.value) return;
  if (!force) {
    const cached = getCachedRewards();
    if (Array.isArray(cached)) {
      rewards.value = cached;
      loading.value = false;
      loadData({ force: true, background: true });
      return;
    }
  }
  if (!background) {
    loading.value = true;
    error.value = '';
  }
  try {
    rewards.value = await rewardsApi.list();
    setCachedRewards(rewards.value);
  } catch (e) {
    if (!background) error.value = e?.message || 'Chyba pri načítaní odmien';
  } finally {
    if (!background) loading.value = false;
  }
};

const startAdd = () => {
  if (!isParent.value) return;
  form.value = { id: 0, title: '', category: '', details: '', icon: '🎁', points_cost: 0 };
  showModal.value = true;
};

const editReward = (reward) => {
  if (!isParent.value) return;
  form.value = { ...reward };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const saveReward = async () => {
  if (!isParent.value) return;
  try {
    await rewardsApi.save(form.value);
    showModal.value = false;
    await loadData({ force: true });
    try {
      emitRuDataChanged({ type: 'reward_changed' });
    } catch {}
  } catch (e) {
    error.value = e?.message || 'Chyba pri ukladaní odmeny';
  }
};

const deleteReward = async (reward) => {
  if (!isParent.value) return;
  if (!confirm(`Naozaj zmazať odmenu "${reward.title}"?`)) return;
  try {
    await rewardsApi.delete(reward.id);
    showModal.value = false;
    form.value = { id: 0, title: '', category: '', details: '', icon: '🎁', points_cost: 0 };
    await loadData({ force: true });
    try {
      emitRuDataChanged({ type: 'reward_changed' });
    } catch {}
  } catch (e) {
    error.value = e?.message || 'Chyba pri mazaní odmeny';
  }
};

const loadChildData = async () => {
  await loadChildOverview();
};

const canBuy = (reward) => {
  if (!childData.value) return false;
  return (childData.value.points_balance || 0) >= parseInt(reward.points_cost || 0, 10);
};

const activeCount = (rewardId) => {
  if (!childData.value || !childData.value.rewards || !childData.value.rewards.active_counts) return 0;
  const counts = childData.value.rewards.active_counts;
  return Number(counts[rewardId] ?? counts[String(rewardId)] ?? 0);
};

const mergePurchaseResult = (prev, reward, res) => {
  const rewards = { ...(prev.rewards || {}) };
  const rid = Number(reward?.id || 0);

  if (res?.active_counts) {
    rewards.active_counts = { ...res.active_counts };
  } else if (rid) {
    const counts = { ...(rewards.active_counts || {}) };
    const key = String(rid);
    counts[key] = Number(counts[key] ?? counts[rid] ?? 0) + 1;
    rewards.active_counts = counts;
  }

  let purchases = Array.isArray(res?.active_purchases)
    ? [...res.active_purchases]
    : [...(rewards.active_purchases || [])];

  const purchaseId = Number(res?.purchase_id || res?.purchase?.id || 0);
  if (purchaseId && rid && !purchases.some((p) => Number(p?.id || 0) === purchaseId)) {
    purchases.push({
      id: purchaseId,
      reward_id: rid,
      title: reward?.title || '',
      icon: reward?.icon || '',
      points_cost: Number(reward?.points_cost || 0),
    });
  }
  rewards.active_purchases = purchases;

  return {
    ...prev,
    points_balance: res?.points_balance ?? prev.points_balance,
    points_today: res?.points_today ?? prev.points_today,
    rewards,
  };
};

const childRewardsSorted = computed(() => {
  const list = Array.isArray(childData.value?.rewards?.items) ? [...childData.value.rewards.items] : [];
  return list.sort((a, b) => {
    const ac = activeCount(a?.id);
    const bc = activeCount(b?.id);
    if (bc !== ac) return bc - ac; // purchased rewards first
    return String(a?.title || '').localeCompare(String(b?.title || ''), 'sk', { sensitivity: 'base' });
  });
});

const firstActivePurchaseId = (rewardId) => {
  const purchases = Array.isArray(childData.value?.rewards?.active_purchases)
    ? childData.value.rewards.active_purchases
    : [];
  const rid = Number(rewardId || 0);
  const match = purchases.find((p) => Number(p?.reward_id ?? p?.rewardId ?? 0) === rid);
  return Number(match?.id || 0);
};

const purchaseReward = async (reward) => {
  if (!canBuy(reward) || purchaseLoading.value === reward.id) return;
  purchaseLoading.value = reward.id;
  try {
    const res = await api.purchaseReward(effectiveChildId.value, reward.id);
    applyChildOverviewPatch((prev) => mergePurchaseResult(prev, reward, res));
    if (childData.value) {
      syncChildOverviewSnapshot(effectiveChildId.value, childData.value);
    }
    await loadChildOverview({ force: true, background: true });
    emitRuDataChanged({
      type: 'reward_purchased',
      childId: String(effectiveChildId.value || ''),
      points_balance: childData.value?.points_balance,
      points_today: childData.value?.points_today,
    });
  } catch (e) {
    alert(e?.message || 'Chyba pri nákupe odmeny');
  } finally {
    purchaseLoading.value = null;
  }
};

const usePurchasedReward = async (reward) => {
  const purchaseId = firstActivePurchaseId(reward?.id);
  if (!purchaseId || useLoading.value === reward?.id) return;
  useLoading.value = reward.id;
  try {
    await api.markRewardUsed(purchaseId);
    applyChildOverviewPatch((prev) => {
      const rewards = { ...(prev.rewards || {}) };
      const purchases = Array.isArray(rewards.active_purchases) ? [...rewards.active_purchases] : [];
      rewards.active_purchases = purchases.filter((p) => Number(p?.id || 0) !== Number(purchaseId));

      const counts = { ...(rewards.active_counts || {}) };
      const key = String(reward.id);
      const prevCount = Number(counts[key] ?? 0);
      counts[key] = Math.max(0, prevCount - 1);
      rewards.active_counts = counts;

      return { ...prev, rewards };
    });
    if (childData.value) {
      syncChildOverviewSnapshot(effectiveChildId.value, childData.value);
      emitRuDataChanged({
        type: 'reward_used',
        childId: String(effectiveChildId.value || ''),
        points_balance: childData.value?.points_balance,
      });
    }
    await loadChildOverview({ force: true, background: true });
  } catch (e) {
    alert(e?.message || 'Chyba pri uplatnení odmeny');
  } finally {
    useLoading.value = null;
  }
};

const openImportModal = async () => {
  showImportModal.value = true;
  importError.value = '';
  rewardLibrary.value = { title: '', count: 0 };
  rewardLibraryItems.value = [];
  selectedLibraryRewardIds.value = [];
  importSourcesLoading.value = true;
  try {
    const summary = await rewardsApi.librarySummary();
    rewardLibrary.value = summary && typeof summary === 'object' ? summary : { title: '', count: 0 };
    rewardLibraryItems.value = Array.isArray(summary?.items) ? summary.items : [];
  } catch (e) {
    importError.value = e?.message || 'Nepodarilo sa načítať knižnicu odmien';
  } finally {
    importSourcesLoading.value = false;
  }
};

const closeImportModal = () => {
  showImportModal.value = false;
  selectedLibraryRewardIds.value = [];
};

const runImport = async () => {
  if (!selectedLibraryRewardIds.value.length) {
    importError.value = 'Vyber aspoň jednu odmenu z knižnice.';
    return;
  }
  importLoading.value = true;
  importError.value = '';
  try {
    await rewardsApi.importFromLibrary(selectedLibraryRewardIds.value);
    closeImportModal();
    await loadData({ force: true });
    try {
      emitRuDataChanged({ type: 'rewards_imported' });
    } catch {}
  } catch (e) {
    importError.value = e?.message || 'Import sa nepodaril';
  } finally {
    importLoading.value = false;
  }
};

const handleQueryActions = () => {
  try {
    if (String(route.query?.import || '') === '1') {
      openImportModal();
      router.replace({ query: { ...route.query, import: undefined } });
    }
  } catch {}
};

onMounted(() => {
  if (isParent.value) {
    loadData();
    handleQueryActions();
  }
});

useChildIdLoadTrigger(
  effectiveChildId,
  () => {
    if (!isParent.value) loadChildData();
  },
  {
    appReady: () => props.appReady,
    onMissing: () => {
      if (isParent.value) return;
      childError.value = 'Chýba ID dieťaťa';
      childLoading.value = false;
    },
  }
);

onActivated(() => {
  if (isParent.value) {
    handleQueryActions();
  }
});

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
.ru-task-item__icon-wrap--emoji {
  font-size: 32px;
  line-height: 1;
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
.ru-reward-row {
  cursor: default;
  align-items: flex-start;
}
.ru-reward-row.disabled {
  opacity: 0.72;
}
.ru-reward-row__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}
.ru-reward-row__actions .ru-btn-buy {
  margin-top: 0;
}
.ru-reward-row__badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  background: #f59e0b;
  color: #ffffff;
  font-weight: 900;
  font-size: 11px;
  line-height: 1;
  margin-right: 4px;
}
.ru-card:not(.ru-admin-card) .ru-reward-row.purchased {
  border-color: #f59e0b;
  background: linear-gradient(180deg, #fff7e6 0%, #ffffff 45%);
  box-shadow: 0 10px 24px -16px rgba(245, 158, 11, 0.9);
}
.ru-card:not(.ru-admin-card) .ru-reward-row.purchased .ru-task-item__title {
  color: #7c2d12;
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

/* Child view readability */
.ru-card:not(.ru-admin-card) .ru-card__body {
  font-size: 16px;
}

.ru-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.ru-header-info h2 {
  margin: 0;
}
.ru-header-actions {
  display: flex;
  align-items: center;
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
.ru-btn-buy {
  margin-top: 4px;
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: var(--ru-accent, #0ea5e9);
  color: white;
  font-weight: 700;
  cursor: pointer;
}
.ru-btn-buy:disabled {
  background: #e5e7eb;
  color: #6b7280;
  cursor: not-allowed;
}
/* (legacy PIN styles removed) */

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
  width: min(520px, calc(100% - 32px));
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 30px 60px -35px rgba(15, 23, 42, 0.5);
}
.ru-modal__header {
  padding: 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}
.ru-modal__close {
  border: none;
  background: transparent;
  font-size: 26px;
  line-height: 1;
  cursor: pointer;
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
.ru-form-section--reward-main .ru-field,
.ru-form-section--reward-main .ru-stepper {
  min-width: 0;
}
.ru-form-section.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}
.ru-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ru-field__label {
  font-weight: 800;
  color: #0f172a;
  font-size: 14px;
  line-height: 1.2;
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
.ru-field--points {
  justify-content: flex-end;
}
.ru-btn.ghost {
  background: transparent;
  border: 1px solid #e5e7eb;
  color: #0f172a;
}
.ru-btn.ghost.danger {
  color: #b91c1c;
  border-color: #fecaca;
}

.ru-modal-actions {
  width: 100%;
  display: flex;
  gap: 10px;
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
  display: flex;
  align-items: center;
  gap: 8px;
}
.ru-library-item__icon {
  font-size: 18px;
  line-height: 1;
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
</style>
