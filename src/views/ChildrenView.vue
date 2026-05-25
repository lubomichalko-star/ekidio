<template>
  <section class="ru-card ru-admin-card">
    <div class="ru-card__body" v-if="loading">Načítavam…</div>
    <div class="ru-card__body" v-else-if="error">
      <p class="ru-error">{{ error }}</p>
    </div>

    <div class="ru-card__body" v-else>
      <div class="ru-followup" v-if="showFirstChildFollowup">
        <div class="ru-followup__title">Pokračujte pridaním Úloh.</div>
        <div class="ru-followup__actions">
          <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="goToTasks(true)">
            Pridať úlohy
          </button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToTasksImport">
            Pridať z knižnice
          </button>
        </div>
      </div>
      <div class="ru-followup" v-if="!children.length">
        <div class="ru-followup__title">Začnime</div>
        <div class="ru-followup__text">
          Zatiaľ nemáte pridané žiadne deti. Najprv pridajte aspoň jedno dieťa, potom mu priraďte úlohy.
        </div>
        <div class="ru-followup__actions">
          <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="openAddChooser">
            Pridať dieťa
          </button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToTasks()">
            Prejsť na úlohy
          </button>
        </div>
      </div>

      <div class="ru-family-section">
        <div class="ru-family-section__header">
          <h3>Deti</h3>
        </div>
        <div class="ru-children-list" ref="listEl">
          <div
            class="ru-child-card"
            v-for="child in children"
            :key="child.id"
            :data-id="child.id"
            @click="editChild(child)"
          >
            <span class="ru-drag-handle" role="button" aria-label="Presunúť" tabindex="0" @click.stop>
              ⋮⋮
            </span>
            <div class="ru-avatar ru-avatar--card" :style="{ background: child.color || '#0ea5e9' }">
              <span v-if="!child.avatar_url">{{ child.name.charAt(0) }}</span>
              <img v-else :src="child.avatar_url" :alt="child.name" />
            </div>
            <div class="ru-child-card__name">{{ child.name }}</div>
          </div>
        </div>
      </div>

      <div class="ru-family-section">
        <div class="ru-family-section__header">
          <h3>Rodičia / Starí rodičia</h3>
          <button class="ru-btn ghost ru-btn--sm" type="button" @click="chooseAddParent">
            Pozvať
          </button>
        </div>

        <div class="ru-family-members" v-if="familyMembers.length || invites.length || familyMembersLoading || invitesLoading">
          <div class="ru-family-member-card" v-for="member in familyMembers" :key="`member-${member.user_id}`">
            <div class="ru-family-member-card__avatar">
              {{ String(member.display_name || '?').charAt(0) }}
            </div>
            <div class="ru-family-member-card__main">
              <div class="ru-family-member-card__name">
                {{ member.display_name || 'Dospelý člen' }}
              </div>
              <div class="ru-family-member-card__meta">{{ member.email || 'Člen rodiny' }}</div>
            </div>
            <div class="ru-family-member-card__badge" v-if="member.is_owner">Správca</div>
          </div>

          <div class="ru-family-member-card ru-family-member-card--invite" v-for="inv in invites" :key="`invite-${inv.id}`">
            <div class="ru-family-member-card__avatar ru-family-member-card__avatar--invite">✉</div>
            <div class="ru-family-member-card__main">
              <div class="ru-family-member-card__name">{{ inv.email }}</div>
              <div class="ru-family-member-card__meta" v-if="inv.expires_at">Pozvánka odoslaná · platí do {{ inv.expires_at }}</div>
              <div class="ru-family-member-card__meta" v-else>Pozvánka odoslaná</div>
            </div>
            <button class="ru-btn ghost danger ru-btn--sm" type="button" @click="revokeInvite(inv.id)">
              Zrušiť
            </button>
          </div>

          <div class="ru-family-note" v-if="familyMembersLoading || invitesLoading">Načítavam…</div>
        </div>

        <div class="ru-family-note" v-else>
          Zatiaľ tu nie sú ďalší dospelí členovia ani odoslané pozvánky.
        </div>
      </div>
    </div>

    <button class="ru-fab" @click="openAddChooser">+</button>

    <RuModal
      v-if="showAddChooser"
      title="Pridať"
      @close="closeAddChooser"
    >
      <p class="ru-card__subtitle">Vyber, čo chceš pridať.</p>
      <div class="ru-add-actions">
        <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="chooseAddChild">
          Pridať dieťa
        </button>
        <button class="ru-btn ghost ru-btn--full" type="button" @click="chooseAddParent">
          Pridať rodiča
        </button>
      </div>
    </RuModal>

    <RuModal
      v-if="showInviteModal"
      title="Pridať rodiča"
      @close="closeInviteModal"
    >
      <p class="ru-card__subtitle">
        Zadaj email. Pozvanému príde email s pozývacím kódom (platí 7 dní).
      </p>

      <label class="ru-field">
        <span>Email</span>
        <input v-model="inviteEmail" type="email" autocomplete="email" placeholder="napr. mama@email.sk" />
      </label>
      <p class="ru-form-msg error" v-if="inviteError">{{ inviteError }}</p>
      <p class="ru-form-msg success" v-if="inviteSuccess">{{ inviteSuccess }}</p>

      <div class="ru-invite-code" v-if="inviteCode">
        <div class="ru-invite-code__label">Pozývací kód (pre istotu):</div>
        <div class="ru-invite-code__value">{{ inviteCode }}</div>
        <button class="ru-btn ghost ru-btn--full" type="button" @click="copyInviteCode(inviteCode)">
          Skopírovať kód
        </button>
      </div>

      <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="sendInvite" :disabled="inviteSending">
        {{ inviteSending ? 'Odosielam…' : 'Poslať pozvánku' }}
      </button>

      <div class="ru-invites" v-if="invitesLoading || (invites && invites.length)">
        <div class="ru-card__subtitle" style="margin-top: 10px;">Aktívne pozvánky</div>
        <div class="ru-invite-row" v-if="invitesLoading">Načítavam…</div>
        <div class="ru-invite-row" v-for="inv in invites" :key="inv.id">
          <div class="ru-invite-row__main">
            <div class="ru-invite-row__email">{{ inv.email }}</div>
            <div class="ru-invite-row__meta" v-if="inv.expires_at">
              Platí do: {{ inv.expires_at }}
            </div>
          </div>
          <button class="ru-btn ghost danger" type="button" @click="revokeInvite(inv.id)">
            Zrušiť
          </button>
        </div>
      </div>
    </RuModal>

    <RuModal
      v-if="showModal"
      :title="form.id ? 'Upraviť dieťa' : 'Pridať dieťa'"
      @close="closeModal"
    >
          <label class="ru-field">
            <span class="ru-field__label">Meno</span>
            <input v-model="form.name" type="text" />
          </label>
          <div class="ru-login-code" v-if="form.login_code">
            <div class="ru-login-code__label">Kód pre prihlásenie dieťaťa do aplikácie.</div>
            <div class="ru-login-code__value">{{ form.login_code }}</div>
          </div>
          <div class="ru-avatar-editor">
            <div class="ru-section__header">
              <h3>Avatar</h3>
            </div>
            <div class="ru-avatar-preview">
              <div class="ru-avatar circle ru-avatar--modal" :style="{ background: form.color || '#0ea5e9' }">
                <span v-if="!form.avatar_url">{{ form.name ? form.name.charAt(0) : '•' }}</span>
                <img v-else :src="form.avatar_url" :alt="form.name || 'avatar'" />
              </div>
              <p class="ru-card__subtitle">Zmeň fotku profilu</p>
              <div class="ru-avatar-actions">
                <label class="ru-btn ghost ru-file-btn" :class="{ disabled: savingAvatar }">
                  <input type="file" accept="image/*" @change="onAvatarFile" :disabled="savingAvatar" />
                  <span>{{ savingAvatar ? 'Nahrávam…' : 'Vybrať fotku' }}</span>
                </label>
                <button
                  class="ru-btn ghost danger"
                  v-if="form.avatar_url"
                  :disabled="savingAvatar"
                  @click="removeAvatar"
                >
                  Odstrániť
                </button>
              </div>
            </div>
          </div>
      <template #footer>
          <div class="ru-modal-actions">
            <button
              v-if="form.id"
              class="ru-btn ghost danger"
              @click="confirmDelete"
            >
              Zmazať dieťa
            </button>
            <button
              class="ru-btn ru-btn--primary ru-btn--full"
              @click="saveChild"
            >
              {{ form.id ? 'Uložiť' : 'Pridať' }}
            </button>
          </div>
      </template>
    </RuModal>
  </section>
</template>

<script setup>
import { emitRuDataChanged } from '../events/ruEvents';
import { ref, onMounted, onBeforeUnmount, nextTick, onActivated } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { childrenApi } from '../api/children';
import { api } from '../api/client';
import RuModal from '../components/RuModal.vue';
import Sortable from 'sortablejs';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const error = ref('');
const children = ref([]);
const showFirstChildFollowup = ref(false);
const showModal = ref(false);
const showAddChooser = ref(false);
const showInviteModal = ref(false);
const form = ref({
  id: 0,
  name: '',
  color: '#4CAF50',
  password: '',
  avatar_url: '',
  login_code: ''
});

// Family invites (additional adults)
const inviteEmail = ref('');
const inviteSending = ref(false);
const inviteError = ref('');
const inviteSuccess = ref('');
const inviteCode = ref('');
const invites = ref([]);
const invitesLoading = ref(false);
const familyMembers = ref([]);
const familyMembersLoading = ref(false);

const savingAvatar = ref(false);
const listEl = ref(null);
let sortable = null;
const savingOrder = ref(false);

const loadChildren = async () => {
  loading.value = true;
  error.value = '';
  try {
    const [childrenRes, membersRes, invitesRes] = await Promise.allSettled([
      childrenApi.list(),
      api.listFamilyMembers(),
      api.listFamilyInvites(),
    ]);

    if (childrenRes.status === 'fulfilled') {
      children.value = Array.isArray(childrenRes.value) ? childrenRes.value : [];
    } else {
      throw childrenRes.reason;
    }

    familyMembers.value = membersRes.status === 'fulfilled' && Array.isArray(membersRes.value)
      ? membersRes.value
      : [];
    invites.value = invitesRes.status === 'fulfilled' && Array.isArray(invitesRes.value)
      ? invitesRes.value
      : [];
  } catch (e) {
    error.value = e?.message || 'Chyba pri načítaní detí';
  } finally {
    loading.value = false;
    // Wait until the list is actually rendered (v-if depends on loading).
    await nextTick();
    initSortable();
  }
};

const loadFamilyMembers = async () => {
  familyMembersLoading.value = true;
  try {
    const list = await api.listFamilyMembers();
    familyMembers.value = Array.isArray(list) ? list : [];
  } catch {
    familyMembers.value = [];
  } finally {
    familyMembersLoading.value = false;
  }
};

const initSortable = () => {
  try {
    if (!listEl.value) return;
    if (sortable) {
      sortable.destroy();
      sortable = null;
    }
    sortable = Sortable.create(listEl.value, {
      animation: 180,
      handle: '.ru-drag-handle',
      ghostClass: 'ru-child-card--ghost',
      dragClass: 'ru-child-card--drag',
      forceFallback: true,
      fallbackOnBody: true,
      delay: 150,
      delayOnTouchOnly: true,
      touchStartThreshold: 5,
      onEnd: async () => {
        try {
          const ids = Array.from(listEl.value.querySelectorAll('[data-id]')).map((el) => Number(el.dataset.id));
          // Update local list order immediately
          const map = new Map(children.value.map((c) => [Number(c.id), c]));
          children.value = ids.map((id) => map.get(id)).filter(Boolean);
          // Persist to server
          savingOrder.value = true;
          await childrenApi.reorder(ids);
        } catch (e) {
          // Reload authoritative order from server if something fails
          try {
            children.value = await childrenApi.list();
          } catch {}
        } finally {
          savingOrder.value = false;
        }
      },
    });
  } catch {}
};

const openAddChooser = () => {
  showAddChooser.value = true;
};
const closeAddChooser = () => {
  showAddChooser.value = false;
};
const chooseAddChild = () => {
  closeAddChooser();
  startAdd();
};
const chooseAddParent = async () => {
  closeAddChooser();
  showInviteModal.value = true;
  await loadInvites();
};
const closeInviteModal = () => {
  showInviteModal.value = false;
  inviteError.value = '';
  inviteSuccess.value = '';
  inviteCode.value = '';
};

const startAdd = () => {
  form.value = { id: 0, name: '', color: '#4CAF50', password: '', avatar_url: '', login_code: '' };
  showModal.value = true;
};

const editChild = (child) => {
  form.value = {
    id: child.id,
    name: child.name,
    color: child.color || '#4CAF50',
    password: '',
    avatar_url: child.avatar_url || '',
    login_code: child.login_code || ''
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const goToTasks = (openAdd = false) => {
  try {
    router.push({ name: 'tasks', query: openAdd ? { add: '1' } : {} });
  } catch {}
};
const goToTasksImport = () => {
  try {
    router.push({ name: 'tasks', query: { import: '1' } });
  } catch {}
};

const onAvatarFile = async (e) => {
  const file = e.target.files && e.target.files[0];
  if (!file) return;
  savingAvatar.value = true;
  try {
    const res = await api.uploadChildAvatar(file);
    const url = res?.url || '';
    form.value.avatar_url = url;
    // If editing existing child, save immediately like in Settings.
    if (form.value.id) {
      await api.saveChildAvatar(form.value.id, url);
    }
  } catch (err) {
    alert(err?.message || 'Chyba pri nahrávaní avatara');
  } finally {
    savingAvatar.value = false;
    // allow selecting the same file again
    e.target.value = '';
  }
};

const removeAvatar = async () => {
  savingAvatar.value = true;
  try {
    form.value.avatar_url = '';
    if (form.value.id) {
      await api.saveChildAvatar(form.value.id, '');
    }
  } catch (err) {
    alert(err?.message || 'Chyba pri ukladaní avatara');
  } finally {
    savingAvatar.value = false;
  }
};

const saveChild = async () => {
  try {
    const wasEmptyBefore = !children.value?.length && !form.value.id;
    await childrenApi.save(form.value);
    showModal.value = false;
    await loadChildren();
    if (wasEmptyBefore) {
      showFirstChildFollowup.value = true;
    }
    try {
      emitRuDataChanged({ type: 'child_changed' });
    } catch {}
  } catch (e) {
    error.value = e?.message || 'Chyba pri ukladaní';
  }
};

const confirmDelete = async () => {
  if (!form.value.id) return;
  const name = form.value.name || 'dieťa';
  if (!confirm(`Naozaj zmazať ${name}?`)) return;
  try {
    await childrenApi.delete(form.value.id);
    showModal.value = false;
    await loadChildren();
    try {
      emitRuDataChanged({ type: 'child_changed' });
    } catch {}
  } catch (e) {
    error.value = e?.message || 'Chyba pri mazaní';
  }
};

const handleAddQuery = () => {
  // If navigated with ?add=1, open "Add child" modal immediately (onboarding).
  try {
    if (String(route.query?.add || '') === '1') {
      startAdd();
      // Clear query so it won't reopen on refresh/back.
      router.replace({ query: { ...route.query, add: undefined } });
    }
  } catch {}
};

const loadInvites = async () => {
  invitesLoading.value = true;
  inviteError.value = '';
  try {
    invites.value = await api.listFamilyInvites();
  } catch {
    invites.value = [];
  } finally {
    invitesLoading.value = false;
  }
};

const sendInvite = async () => {
  inviteError.value = '';
  inviteSuccess.value = '';
  inviteCode.value = '';
  if (inviteSending.value) return;
  const email = String(inviteEmail.value || '').trim();
  if (!email || !email.includes('@')) {
    inviteError.value = 'Zadaj platný email.';
    return;
  }
  inviteSending.value = true;
  try {
    const res = await api.createFamilyInvite(email);
    inviteCode.value = res?.invite?.code || '';
    inviteSuccess.value = res?.email_sent
      ? 'Pozvánka bola odoslaná.'
      : 'Email sa nepodarilo odoslať. Skopíruj kód a pošli ho ručne.';
    inviteEmail.value = '';
    await Promise.all([loadInvites(), loadFamilyMembers()]);
  } catch (e) {
    inviteError.value = e?.message || 'Pozvánku sa nepodarilo poslať';
  } finally {
    inviteSending.value = false;
  }
};

const revokeInvite = async (id) => {
  if (!confirm('Zrušiť túto pozvánku?')) return;
  try {
    await api.revokeFamilyInvite(id);
    await Promise.all([loadInvites(), loadFamilyMembers()]);
  } catch (e) {
    alert(e?.message || 'Pozvánku sa nepodarilo zrušiť');
  }
};

const copyInviteCode = async (code) => {
  const c = String(code || '').trim();
  if (!c) return;
  try {
    await navigator.clipboard.writeText(c);
    alert('Skopírované.');
  } catch {
    prompt('Skopíruj kód:', c);
  }
};

onMounted(async () => {
  await loadChildren();
  handleAddQuery();
});

onActivated(() => {
  // When KeepAlive is enabled, onMounted won't run again on return.
  handleAddQuery();
});

onBeforeUnmount(() => {
  try {
    if (sortable) sortable.destroy();
  } catch {}
  sortable = null;
});
</script>

<style scoped>
.ru-admin-card {
  position: relative;
}
.ru-fab {
  position: fixed;
  bottom: 24px;
  right: 24px;
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
  z-index: 50;
}

.ru-followup {
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 16px;
  padding: 14px;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  margin-bottom: 12px;
  position: relative;
}
.ru-followup__title {
  font-weight: 900;
  color: #0f172a;
  margin-bottom: 4px;
}
.ru-followup__text {
  color: #64748b;
  font-weight: 700;
  font-size: 13px;
  margin-bottom: 10px;
}
.ru-followup__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.ru-add-actions {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-top: 10px;
}

.ru-family-section {
  margin-top: 18px;
}

.ru-family-section__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.ru-family-section__header h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #bebebe;
}

@media (max-width: 768px) {
  .ru-fab {
    bottom: calc(84px + env(safe-area-inset-bottom, 0px));
    right: 16px;
    width: 56px;
    height: 56px;
    font-size: 28px;
  }
  .ru-followup__actions {
    grid-template-columns: 1fr;
  }
  .ru-family-section__header {
    align-items: flex-start;
    flex-direction: column;
  }
}
.ru-children-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 14px;
}
.ru-child-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 16px 12px 14px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  cursor: pointer;
  user-select: none;
  text-align: center;
}
.ru-child-card--ghost {
  opacity: 0.7;
}
.ru-child-card--drag {
  opacity: 0.95;
}
.ru-drag-handle {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #f8fafc;
  color: #475569;
  font-weight: 900;
  cursor: grab;
  display: grid;
  place-items: center;
}
.ru-drag-handle:active {
  cursor: grabbing;
}
.ru-avatar--card {
  width: 104px;
  height: 104px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  color: #fff;
  font-weight: 900;
  font-size: 34px;
  overflow: hidden;
}
.ru-avatar--card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ru-child-card__name {
  font-weight: 800;
  color: #0f172a;
  font-size: 16px;
  line-height: 1.25;
  width: 100%;
  word-break: break-word;
}
.ru-family-members {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ru-family-member-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.ru-family-member-card__avatar {
  width: 46px;
  height: 46px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: var(--ru-accent, #0ea5e9);
  color: #fff;
  font-weight: 900;
  font-size: 18px;
  flex-shrink: 0;
}
.ru-family-member-card__avatar--invite {
  background: #f8fafc;
  color: #475569;
}
.ru-family-member-card__main {
  min-width: 0;
  flex: 1;
}
.ru-family-member-card__name {
  font-weight: 800;
  color: #0f172a;
  font-size: 15px;
  word-break: break-word;
}
.ru-family-member-card__meta {
  margin-top: 2px;
  color: #64748b;
  font-weight: 700;
  font-size: 12px;
  word-break: break-word;
}
.ru-family-member-card__badge {
  padding: 6px 10px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  font-weight: 800;
  font-size: 12px;
  flex-shrink: 0;
}
.ru-family-member-card--invite {
  align-items: flex-start;
}
.ru-family-note {
  padding: 14px;
  border-radius: 16px;
  border: 1px dashed rgba(15, 23, 42, 0.16);
  color: #64748b;
  font-weight: 700;
  font-size: 13px;
  background: #ffffff;
}
.ru-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
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
  background: #ffffff;
  color: #0f172a;
  font-size: 16px;
  font-weight: 700;
  line-height: 1.35;
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
}
.ru-avatar-editor {
  margin-top: 16px;
}
.ru-login-code {
  margin: 10px 0 2px;
  padding: 12px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.ru-login-code__label {
  color: #64748b;
  font-weight: 700;
  font-size: 13px;
  margin-bottom: 8px;
}
.ru-login-code__value {
  font-weight: 900;
  letter-spacing: 3px;
  color: #0f172a;
  background: #f1f5f9;
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 999px;
  padding: 10px 12px;
  font-size: 18px;
  text-align: center;
}

.ru-form-msg {
  margin: 8px 0;
  font-weight: 700;
  font-size: 13px;
}
.ru-form-msg.error {
  color: #b91c1c;
}
.ru-form-msg.success {
  color: #166534;
}

.ru-invite-code {
  margin: 10px 0;
  padding: 12px;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.ru-invite-code__label {
  color: #64748b;
  font-weight: 800;
  font-size: 13px;
  margin-bottom: 8px;
}
.ru-invite-code__value {
  font-weight: 900;
  letter-spacing: 1px;
  color: #0f172a;
  background: #f1f5f9;
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 14px;
  padding: 10px 12px;
  font-size: 14px;
  text-align: center;
  word-break: break-all;
  margin-bottom: 10px;
}
.ru-invite-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 0;
  border-top: 1px solid rgba(15, 23, 42, 0.08);
}
.ru-invite-row__main {
  min-width: 0;
  flex: 1;
}
.ru-invite-row__email {
  font-weight: 900;
  color: #0f172a;
  font-size: 14px;
  word-break: break-word;
}
.ru-invite-row__meta {
  color: #64748b;
  font-weight: 700;
  font-size: 12px;
  margin-top: 2px;
}
.ru-section {
  margin-top: 14px;
}
.ru-avatar-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 18px 16px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.ru-avatar--modal {
  width: 104px;
  height: 104px;
  border-radius: 999px;
  overflow: hidden;
  display: grid;
  place-items: center;
  color: #fff;
  font-weight: 900;
  font-size: 28px;
}
.ru-avatar--modal img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ru-avatar-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
}
.ru-file-btn input[type="file"] {
  display: none;
}
/* ru-modal-actions is global (ru-base.css) */

@media (max-width: 640px) {
  .ru-children-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .ru-avatar-preview {
    padding: 16px 12px;
  }
  .ru-family-member-card {
    align-items: flex-start;
    flex-wrap: wrap;
  }
  .ru-family-member-card__badge {
    margin-left: 58px;
  }
  .ru-avatar--card {
    width: 96px;
    height: 96px;
    font-size: 30px;
  }
}
</style>

