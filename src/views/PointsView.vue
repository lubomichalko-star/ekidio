<template>
  <section class="ru-card">
    <header class="ru-card__header">
      <div>
        <h2>Body</h2>
        <p class="ru-card__subtitle">Správa bodov (len rodič)</p>
      </div>
      <div class="ru-header-actions">
        <select v-model="selectedChildId" class="ru-select" @change="loadData">
          <option v-for="c in localizedChildren" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <button class="ru-btn" @click="openAdd">Pridať body</button>
        <button class="ru-btn ghost" @click="openDeduct">Odobrať body</button>
      </div>
    </header>

    <div class="ru-card__body" v-if="!isParent">
      <p class="ru-error">Prístup len pre rodiča.</p>
    </div>

    <div class="ru-card__body" v-else-if="loading">Načítavam…</div>
    <div class="ru-card__body" v-else-if="error">
      <p class="ru-error">{{ error }}</p>
    </div>

    <div class="ru-card__body" v-else>
      <div class="ru-stats">
        <div class="ru-stat">
          <div class="ru-stat__label">Aktuálne body</div>
          <div class="ru-stat__value">{{ data.points_balance }}</div>
        </div>
        <div class="ru-stat">
          <div class="ru-stat__label">Dnes</div>
          <div class="ru-stat__value">{{ data.points_today }}</div>
        </div>
        <div class="ru-stat">
          <div class="ru-stat__label">Týždeň</div>
          <div class="ru-stat__value">{{ data.points_week }}</div>
          <div class="ru-stat__sub">
            +{{ data.week_summary?.earned || 0 }} / -{{ data.week_summary?.lost || 0 }}
          </div>
        </div>
      </div>

      <h4>História (posledných 7 dní)</h4>
      <div class="ru-table" v-if="data.history && data.history.length">
        <div class="ru-table__head">
          <span>Dátum</span>
          <span>Body</span>
          <span>Popis</span>
          <span>Akcia</span>
        </div>
        <div class="ru-table__row" v-for="entry in data.history" :key="entry.id">
          <span>{{ formatDate(entry.created_at) }}</span>
          <span :class="{'pos': entry.points > 0, 'neg': entry.points < 0}">
            {{ entry.points > 0 ? '+' : ''}}{{ entry.points }}
          </span>
          <span>{{ entry.reason || entry.task_name || '—' }}</span>
          <span>
            <button class="ru-link danger" @click="deleteEntry(entry)">Zmazať</button>
          </span>
        </div>
      </div>
      <p v-else class="ru-empty">Žiadna história za posledných 7 dní.</p>
    </div>

    <RuModal
      v-if="showModal"
      :title="modalMode === 'add' ? 'Pridať body' : 'Odobrať body'"
      @close="closeModal"
    >
          <label class="ru-field">
            <span>Body</span>
            <input v-model.number="modalPoints" type="number" />
          </label>
          <label class="ru-field">
            <span>Dôvod</span>
            <input v-model="modalReason" type="text" />
          </label>
      <template #footer>
          <button class="ru-btn" @click="submitModal">
            {{ modalMode === 'add' ? 'Pridať' : 'Odobrať' }}
          </button>
      </template>
    </RuModal>
  </section>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { pointsApi } from '../api/points';
import RuModal from '../components/RuModal.vue';

const props = defineProps({
  role: { type: String, default: 'child' },
  childId: { type: [String, Number], default: '' },
  localized: { type: Object, default: () => ({}) },
});

const localizedChildren = computed(() => props.localized?.children || []);
// IMPORTANT: role must come from runtime auth (App.vue + /auth/me), not from direct localStorage reads.
const isParent = computed(() => {
  if (props.role === 'parent') return true;
  if (props.role === 'child') return false;
  return !!(props.localized?.isParent && !props.localized?.forceChild);
});

const selectedChildId = ref(null);

// Keep selection valid if children list arrives/changes (e.g. after login/logout).
onMounted(() => {
  if (!selectedChildId.value && localizedChildren.value.length) {
    selectedChildId.value = localizedChildren.value[0].id;
  }
});
const loading = ref(true);
const error = ref('');
const data = ref({});

const showModal = ref(false);
const modalMode = ref('add');
const modalPoints = ref(0);
const modalReason = ref('');

const formatDate = (str) => {
  if (!str) return '';
  return new Date(str).toLocaleString();
};

const loadData = async () => {
  if (!isParent) return;
  if (!selectedChildId.value) {
    error.value = 'Nie je vybraté dieťa';
    return;
  }
  loading.value = true;
  error.value = '';
  try {
    data.value = await pointsApi.overview(selectedChildId.value);
  } catch (e) {
    error.value = e?.message || 'Chyba pri načítaní bodov';
  } finally {
    loading.value = false;
  }
};

const openAdd = () => {
  modalMode.value = 'add';
  modalPoints.value = 0;
  modalReason.value = '';
  showModal.value = true;
};

const openDeduct = () => {
  modalMode.value = 'deduct';
  modalPoints.value = 0;
  modalReason.value = '';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const submitModal = async () => {
  try {
    if (modalMode.value === 'add') {
      await pointsApi.add(selectedChildId.value, modalPoints.value, modalReason.value);
    } else {
      await pointsApi.deduct(selectedChildId.value, modalPoints.value, modalReason.value);
    }
    showModal.value = false;
    await loadData();
  } catch (e) {
    error.value = e?.message || 'Chyba pri ukladaní bodov';
  }
};

const deleteEntry = async (entry) => {
  if (!confirm('Naozaj zmazať záznam?')) return;
  try {
    // Optimistic UI: remove immediately
    const prev = Array.isArray(data.value?.history) ? data.value.history : [];
    data.value = {
      ...(data.value || {}),
      history: prev.filter((x) => String(x?.id) !== String(entry.id)),
    };
    await pointsApi.deleteEntry(entry.id);
    await loadData();
  } catch (e) {
    error.value = e?.message || 'Chyba pri mazaní záznamu';
  }
};

onMounted(() => {
  if (isParent.value) {
    // Ensure a child is selected before loading
    if (!selectedChildId.value && localizedChildren.value.length) {
      selectedChildId.value = localizedChildren.value[0].id;
    }
    loadData();
  }
});
</script>

<style scoped>
.ru-card {
  background: transparent;
  border: 0;
  border-radius: 0;
  box-shadow: none;
  padding: 0 14px 16px;
  max-width: 760px;
  margin: 0 auto;
}
.ru-card__header {
  position: relative;
  z-index: 1;
  background: transparent;
}
.ru-card__body {
  font-size: 16px;
}

.ru-header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}
.ru-select {
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.ru-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
  margin-bottom: 12px;
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
.pos {
  color: #16a34a;
  font-weight: 700;
}
.neg {
  color: #b91c1c;
  font-weight: 700;
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
  width: 420px;
  max-width: calc(100vw - 40px);
  overflow: hidden;
  box-shadow: 0 20px 60px -30px rgba(15, 23, 42, 0.4);
}
.ru-modal__header,
.ru-modal__footer {
  padding: 12px 16px;
  border-bottom: 1px solid #e5e7eb;
}
.ru-modal__footer {
  border-top: 1px solid #e5e7eb;
  border-bottom: none;
}
.ru-modal__body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ru-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ru-field input {
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 14px;
}
</style>

