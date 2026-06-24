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

    <div class="ru-card__body" v-if="isParent">
      <div class="ru-task-list">
        <button class="ru-task-item" type="button" @click="openPanel('tasks')">
          <div class="ru-task-item__body">
            <div class="ru-task-item__title">Nastavenia úloh a rotácie</div>
          </div>
          <div class="ru-settings-item__chev" aria-hidden="true">›</div>
        </button>

        <button class="ru-task-item" type="button" @click="openPanel('kiosk')">
          <div class="ru-task-item__body">
            <div class="ru-task-item__title">Kiosk režim</div>
          </div>
          <div class="ru-settings-item__chev" aria-hidden="true">›</div>
        </button>

        <button class="ru-task-item" type="button" @click="openPanel('profile')">
          <div class="ru-task-item__body">
            <div class="ru-task-item__title">Profil</div>
          </div>
          <div class="ru-settings-item__chev" aria-hidden="true">›</div>
        </button>

        <button class="ru-task-item" type="button" @click="openPanel('language')">
          <div class="ru-task-item__body">
            <div class="ru-task-item__title">{{ t('settings.language.title') }}</div>
          </div>
          <div class="ru-settings-item__chev" aria-hidden="true">›</div>
        </button>

        <button class="ru-task-item" type="button" @click="openPanel('about')">
          <div class="ru-task-item__body">
            <div class="ru-task-item__title">{{ t('settings.aboutApp.title') }}</div>
          </div>
          <div class="ru-settings-item__chev" aria-hidden="true">›</div>
        </button>
        <button class="ru-btn danger ru-btn--sm ru-btn--full" type="button" @click="doLogout">
          {{ t('nav.logout') }}
        </button>
      </div>
    </div>
    <div class="ru-card__body" v-else-if="childError">
      <p class="ru-error">{{ childError }}</p>
    </div>
    <div class="ru-card__body" v-else-if="childLoading">
      <p>Načítavam…</p>
    </div>
    <div class="ru-card__body" v-else>
      <div class="ru-section">
        <div class="ru-section__header">
          <h3>Avatar</h3>
        </div>
        <div class="ru-avatar-preview">
          <div class="ru-avatar circle" :style="{ background: accentColor }">
            <span v-if="!previewAvatar">{{ childDisplay?.name?.charAt(0) || '•' }}</span>
            <img v-else :src="previewAvatar" alt="avatar" />
          </div>
          <p class="ru-card__subtitle">Zmeň si fotku profilu</p>
          <div class="ru-avatar-actions">
            <label class="ru-btn ghost ru-file-btn" :class="{ disabled: savingAvatar }">
              <input type="file" accept="image/*" @change="onFile" :disabled="savingAvatar" />
              <span>{{ savingAvatar ? 'Nahrávam…' : 'Vybrať fotku' }}</span>
            </label>
            <button class="ru-btn ghost danger" v-if="previewAvatar" :disabled="savingAvatar" @click="removeAvatar">
              Odstrániť
            </button>
          </div>
        </div>
      </div>

      <div class="ru-section">
        <div class="ru-section__header">
          <h3>Farebná škála</h3>
        </div>
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

      <div class="ru-task-list">
        <button class="ru-task-item" type="button" @click="openPanel('language')">
          <div class="ru-task-item__body">
            <div class="ru-task-item__title">{{ t('settings.language.title') }}</div>
          </div>
          <div class="ru-settings-item__chev" aria-hidden="true">›</div>
        </button>
        <button class="ru-task-item" type="button" @click="openPanel('about')">
          <div class="ru-task-item__body">
            <div class="ru-task-item__title">{{ t('settings.aboutApp.title') }}</div>
          </div>
          <div class="ru-settings-item__chev" aria-hidden="true">›</div>
        </button>
      </div>

      <button class="ru-btn danger ru-btn--full" type="button" @click="doLogout">
        {{ t('nav.logout') }}
      </button>

      <div class="ru-app-footer">
        <img :src="logoUrl" alt="ekidio" />
      </div>
    </div>

    <RuModal v-if="activePanel" :title="panelTitle" @close="closePanel">
      <div class="ru-settings-modal">
        <template v-if="activePanel === 'tasks'">
          <div class="ru-section">
            <div class="ru-section__header">
              <h3>Posun rotácie</h3>
            </div>

            <div class="ru-rotation-hero" aria-hidden="true">
              <svg class="ru-rotation-hero__icon" viewBox="0 0 64 64" fill="none">
                <path d="M50 24A19 19 0 0 0 18.5 15" />
                <path d="M18.5 15H28" />
                <path d="M18.5 15v9.5" />
                <path d="M14 40A19 19 0 0 0 45.5 49" />
                <path d="M45.5 49H36" />
                <path d="M45.5 49v-9.5" />
                <circle cx="32" cy="32" r="7.5" />
                <path d="M32 27.5v5l3.5 2.5" />
              </svg>
            </div>

            <button class="ru-btn danger ru-btn--full ru-btn--no-shadow" @click="shiftRotationNow" :disabled="shiftingRotation">
              {{ shiftingRotation ? 'Posúvam…' : 'Posunúť rotáciu teraz' }}
            </button>
          </div>

          <div class="ru-section">
            <div class="ru-section__header">
              <h3>Automatická rotácia úloh</h3>
            </div>

            <div class="ru-rotation">
              <div class="ru-choice">
                <div class="ru-choice__label">Obdobie</div>
                <div class="ru-segment" role="radiogroup" aria-label="Obdobie rotácie">
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="rotationFrequency === 'weekly'"
                    class="ru-segment__btn"
                    :class="{ active: rotationFrequency === 'weekly' }"
                    @click="rotationFrequency = 'weekly'"
                  >
                    Týždeň
                  </button>
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="rotationFrequency === 'biweekly'"
                    class="ru-segment__btn"
                    :class="{ active: rotationFrequency === 'biweekly' }"
                    @click="rotationFrequency = 'biweekly'"
                  >
                    2 týždne
                  </button>
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="rotationFrequency === 'monthly'"
                    class="ru-segment__btn"
                    :class="{ active: rotationFrequency === 'monthly' }"
                    @click="rotationFrequency = 'monthly'"
                  >
                    Mesiac
                  </button>
                </div>
                <p class="ru-help" v-if="rotationFrequency === 'monthly'">
                  Mesačná rotácia beží vždy <strong>1. deň v mesiaci o 00:01</strong>.
                </p>
              </div>

              <div class="ru-choice" v-if="rotationFrequency !== 'monthly'">
                <div class="ru-choice__label">Začiatočný deň</div>
                <div class="ru-daygrid" role="radiogroup" aria-label="Začiatočný deň rotácie">
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="rotationDay === 'monday'"
                    class="ru-daycard"
                    :class="{ active: rotationDay === 'monday' }"
                    @click="rotationDay = 'monday'"
                  >
                    <span class="ru-daycard__title">Pondelok</span>
                  </button>
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="rotationDay === 'saturday'"
                    class="ru-daycard"
                    :class="{ active: rotationDay === 'saturday' }"
                    @click="rotationDay = 'saturday'"
                  >
                    <span class="ru-daycard__title">Sobota</span>
                  </button>
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="rotationDay === 'sunday'"
                    class="ru-daycard"
                    :class="{ active: rotationDay === 'sunday' }"
                    @click="rotationDay = 'sunday'"
                  >
                    <span class="ru-daycard__title">Nedeľa</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="ru-section">
            <div class="ru-section__header">
              <h3>Penalizácia sobotných povinných úloh</h3>
            </div>
            <div class="ru-field inline">
              <label>
                <span>Multiplikátor</span>
                <input
                  v-model.number="weekendMultiplier"
                  type="number"
                  step="0.1"
                  min="1"
                />
              </label>
            </div>
            <button
              class="ru-btn ru-btn--primary ru-btn--full"
              type="button"
              @click="saveTaskSettings"
              :disabled="savingTaskSettings"
            >
              {{ savingTaskSettings ? 'Ukladám…' : 'Uložiť' }}
            </button>
          </div>
        </template>

        <template v-else-if="activePanel === 'kiosk'">
          <div class="ru-section">
            <div class="ru-section__header">
              <h3>Kiosk režim</h3>
            </div>
            <p class="ru-card__subtitle">
              Kiosk režim je pre tablet na stene: deti naraz, úlohy sa dajú odklikávať.
            </p>

            <p class="ru-card__subtitle">
              Stav: <strong>{{ kioskEnabled ? 'aktívny' : 'neaktívny' }}</strong>
            </p>

            <label class="ru-field">
              <span>PIN pre vypnutie (klikni na X vpravo hore v kiosku)</span>
              <input
                v-model="kioskPin"
                inputmode="numeric"
                type="password"
                maxlength="6"
                placeholder="••••"
              />
            </label>

            <label class="ru-field">
              <span>Čas svietenia displeja bez pohybu (sekundy)</span>
              <input
                v-model.number="kioskScreenIdleSec"
                type="number"
                :min="kioskScreenIdleMin"
                :max="kioskScreenIdleMax"
                inputmode="numeric"
                @change="saveKioskScreenIdle"
              />
            </label>
            <p class="ru-card__subtitle">
              Po {{ kioskScreenIdleSec }} s bez dotyku a bez pohybu pred kamerou sa displej vypne.
              <template v-if="isNativeAndroid">Platí v Android aplikácii.</template>
            </p>

            <p class="ru-form-msg error" v-if="kioskError">{{ kioskError }}</p>
            <p class="ru-form-msg success" v-if="kioskSaved">Uložené.</p>

            <div class="ru-kiosk-actions">
              <button class="ru-btn ghost ru-btn--full" type="button" @click="saveKioskPinOnly">
                Uložiť PIN
              </button>
              <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="startKiosk">
                {{ kioskEnabled ? 'Otvoriť kiosk' : 'Spustiť kiosk' }}
              </button>
            </div>
          </div>
        </template>

        <template v-else-if="activePanel === 'profile'">
          <div class="ru-section">
            <div class="ru-section__header">
              <h3>Profil</h3>
            </div>
            <label class="ru-field">
              <span>Meno</span>
              <input v-model="parentFirstName" type="text" />
            </label>
            <label class="ru-field">
              <span>Priezvisko</span>
              <input v-model="parentLastName" type="text" />
            </label>
            <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="saveParentProfile">
              Uložiť
            </button>
          </div>

          <div class="ru-section">
            <div class="ru-section__header">
              <h3>Zmeniť heslo</h3>
            </div>
            <label class="ru-field">
              <span>Aktuálne heslo</span>
              <input v-model="parentCurrentPassword" type="password" autocomplete="current-password" />
            </label>
            <label class="ru-field">
              <span>Nové heslo</span>
              <input v-model="parentNewPassword" type="password" autocomplete="new-password" />
            </label>
            <label class="ru-field">
              <span>Nové heslo znova</span>
              <input v-model="parentNewPassword2" type="password" autocomplete="new-password" />
            </label>
            <p class="ru-form-msg error" v-if="passwordError">{{ passwordError }}</p>
            <p class="ru-form-msg success" v-if="passwordSuccess">Heslo bolo zmenené.</p>
            <button
              class="ru-btn ru-btn--primary ru-btn--full"
              type="button"
              @click="saveParentPassword"
              :disabled="passwordSaving"
            >
              {{ passwordSaving ? 'Ukladám…' : 'Uložiť heslo' }}
            </button>
          </div>

          <div class="ru-section">
            <div class="ru-section__header">
              <h3>Reset dát</h3>
            </div>
            <p class="ru-card__subtitle">
              Pozor: táto akcia je nevratná. Vymaže dáta iba pre tvoj účet.
            </p>
            <p class="ru-form-msg success" v-if="resetSuccess">{{ resetSuccess }}</p>
            <p class="ru-form-msg error" v-if="resetError">{{ resetError }}</p>
            <button class="ru-btn danger ru-btn--full" type="button" @click="resetChildren" :disabled="resetLoading">
              Resetovať deti
            </button>
            <button class="ru-btn danger ru-btn--full" type="button" @click="resetTasks" :disabled="resetLoading">
              Resetovať úlohy
            </button>
            <button class="ru-btn danger ru-btn--full" type="button" @click="resetRewards" :disabled="resetLoading">
              Resetovať odmeny
            </button>
          </div>

          <div class="ru-section">
            <div class="ru-section__header">
              <h3>Zrušiť účet</h3>
            </div>
            <p class="ru-card__subtitle">
              Natrvalo vymaže tvoj účet. Ak si správca rodiny, vymažú sa aj všetky rodinné dáta.
              Táto akcia je nevratná.
            </p>
            <label class="ru-field">
              <span>Aktuálne heslo</span>
              <input
                v-model="deleteAccountPassword"
                type="password"
                autocomplete="current-password"
                placeholder="Zadaj heslo pre potvrdenie"
              />
            </label>
            <p class="ru-help subtle">
              Ak ste sa prihlásili cez Google, nechajte heslo prázdne a nižšie napíšte ZRUŠIŤ.
            </p>
            <label class="ru-field">
              <span>Potvrdenie (Google účet)</span>
              <input
                v-model="deleteAccountConfirm"
                type="text"
                autocomplete="off"
                placeholder="ZRUŠIŤ"
              />
            </label>
            <p class="ru-form-msg error" v-if="deleteAccountError">{{ deleteAccountError }}</p>
            <button
              class="ru-btn danger ru-btn--full"
              type="button"
              @click="deleteAccount"
              :disabled="deleteAccountLoading"
            >
              {{ deleteAccountLoading ? 'Ruším účet…' : 'Zrušiť účet' }}
            </button>
          </div>
        </template>

        <template v-else-if="activePanel === 'language'">
          <div class="ru-section">
            <div class="ru-section__header">
              <h3>{{ t('settings.language.title') }}</h3>
            </div>
            <p class="ru-card__subtitle">{{ t('settings.language.desc') }}</p>
            <div class="ru-lang-switcher">
              <button
                type="button"
                class="ru-btn"
                :class="{ 'ru-btn--primary': i18nState.locale === 'sk' }"
                @click="setLocale('sk')"
              >
                {{ t('settings.language.sk') }}
              </button>
              <button
                type="button"
                class="ru-btn"
                :class="{ 'ru-btn--primary': i18nState.locale === 'en' }"
                @click="setLocale('en')"
              >
                {{ t('settings.language.en') }}
              </button>
            </div>
          </div>
        </template>

        <template v-else-if="activePanel === 'about'">
          <div class="ru-section">
            <div class="ru-section__header">
              <h3>O aplikácii</h3>
            </div>
            <div class="ru-about">
              <div class="ru-about__row">
                <span class="ru-about__label">Verzia</span>
                <span class="ru-about__value">{{ appVersion }}</span>
              </div>
              <div class="ru-about__row" v-if="buildVersion">
                <span class="ru-about__label">Build</span>
                <span class="ru-about__value">{{ buildVersion }}</span>
              </div>
              <div class="ru-about__row">
                <span class="ru-about__label">Prostredie</span>
                <span class="ru-about__value">{{ appEnv }}</span>
              </div>
              <div class="ru-about__row">
                <span class="ru-about__label">Kontakt</span>
                <a class="ru-about__value ru-about__link" href="mailto:info@ekidio.com">info@ekidio.com</a>
              </div>
              <div class="ru-about__update" v-if="appUpdateInfo">
                <p class="ru-about__update-text">
                  {{ appUpdateInfo.message }}
                  Dostupná verzia {{ appUpdateInfo.latestVersion }} (máte {{ appUpdateInfo.currentVersion }}).
                </p>
                <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="goAppDownload">
                  Stiahnuť novú verziu
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </RuModal>
  </section>
</template>

<script setup>
import { emitRuDataChanged } from '../events/ruEvents';
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api/client';
import RuModal from '../components/RuModal.vue';
import { useEffectiveChildId } from '../composables/useEffectiveChildId';
import coinPng from '../images/star.png';
import logoUrl from '../images/logo-gen.png';
import { getAppReleaseVersion } from '../lib/appVersion';
import { getAvailableAppUpdate, getNativeAppVersion, openDownloadPage } from '../lib/appUpdate';
import { updateKioskMotionIdleTimeout } from '../lib/kioskMotion';
import {
  DEFAULT_KIOSK_SCREEN_IDLE_SEC,
  getKioskScreenIdleSec,
  MAX_KIOSK_SCREEN_IDLE_SEC,
  MIN_KIOSK_SCREEN_IDLE_SEC,
  setKioskScreenIdleSec,
} from '../lib/kioskSettings';
import { Capacitor } from '@capacitor/core';
import { t, i18nState, setLocale } from '../i18n';

const props = defineProps({
  role: { type: String, default: 'child' },
  childId: { type: [String, Number], default: '' },
  localized: { type: Object, default: () => ({}) },
});

const effectiveChildId = useEffectiveChildId({
  childId: () => props.childId,
  localized: () => props.localized,
});

const localizedChildren = computed(() => props.localized?.children || []);
const childData = ref(null);
const childError = ref('');
const childLoading = ref(true);
const parentAccentFixed = '#5abb6f';

const childDisplay = computed(() => {
  if (childData.value?.child) return childData.value.child;
  const id = effectiveChildId.value;
  if (id && localizedChildren.value.length) {
    const found = localizedChildren.value.find((c) => String(c.id) === String(id));
    if (found) return found;
  }
  return localizedChildren.value[0] || null;
});

const palette = ['#0ea5e9', '#16a34a', '#f97316', '#f59e0b', '#6366f1', '#ec4899'];
const localAccent = ref(localStorage.getItem('ru-accent') || '');
const accentColor = computed(() =>
  isParent.value ? parentAccentFixed : childDisplay.value?.color || localAccent.value || '#0ea5e9'
);
const accentLight = computed(() => `${accentColor.value}33`);

const avatarUrl = ref('');
const avatarFile = ref(null);
const previewAvatar = computed(() => avatarUrl.value || childDisplay.value?.avatar_url || '');
const savingAvatar = ref(false);
// PIN removed
const weekendMultiplier = ref(props.localized?.weekendMultiplier || 3);
const savingMultiplier = ref(false);
const shiftingRotation = ref(false);
const coinIcon = coinPng;
const nativeAppVersion = ref('');
const appUpdateInfo = ref(null);
const appVersion = computed(() => {
  if (nativeAppVersion.value) return nativeAppVersion.value;
  return getAppReleaseVersion(props.localized || {});
});
const buildVersion = props.localized?.buildVersion ? String(props.localized.buildVersion) : '';
const appEnv = import.meta?.env?.MODE || 'production';

const activePanel = ref('');
const openPanel = (key) => {
  activePanel.value = key;
  if (key === 'kiosk') {
    loadKioskSettings();
  }
  if (key === 'about') {
    refreshAppUpdateInfo();
  }
};

const refreshAppUpdateInfo = async () => {
  if (!Capacitor.isNativePlatform()) {
    appUpdateInfo.value = null;
    return;
  }
  appUpdateInfo.value = await getAvailableAppUpdate({ force: true });
};

const goAppDownload = () => {
  openDownloadPage(appUpdateInfo.value?.downloadUrl, appUpdateInfo.value?.latestVersion);
};
const closePanel = () => {
  activePanel.value = '';
};
const panelTitle = computed(() => {
  if (activePanel.value === 'tasks') return t('settings.taskRotationSettings');
  if (activePanel.value === 'kiosk') return t('settings.kiosk.title');
  if (activePanel.value === 'profile') return t('settings.profile');
  if (activePanel.value === 'language') return t('settings.language.title');
  if (activePanel.value === 'about') return t('settings.aboutApp.title');
  return '';
});

const parentFirstNameKey = 'ru_parent_first_name';
const parentLastNameKey = 'ru_parent_last_name';
const parentFirstName = ref(localStorage.getItem(parentFirstNameKey) || '');
const parentLastName = ref(localStorage.getItem(parentLastNameKey) || '');
const saveParentProfile = () => {
  localStorage.setItem(parentFirstNameKey, parentFirstName.value || '');
  localStorage.setItem(parentLastNameKey, parentLastName.value || '');
  alert('Uložené');
};

const parentCurrentPassword = ref('');
const parentNewPassword = ref('');
const parentNewPassword2 = ref('');
const passwordSaving = ref(false);
const passwordError = ref('');
const passwordSuccess = ref(false);

const kioskEnabled = ref(false);
const kioskPin = ref('');
const kioskError = ref('');
const kioskSaved = ref(false);
const kioskScreenIdleSec = ref(DEFAULT_KIOSK_SCREEN_IDLE_SEC);
const kioskScreenIdleMin = MIN_KIOSK_SCREEN_IDLE_SEC;
const kioskScreenIdleMax = MAX_KIOSK_SCREEN_IDLE_SEC;
const isNativeAndroid = Capacitor.getPlatform() === 'android';

const sha256Hex = async (input) => {
  const raw = String(input || '');
  const enc = new TextEncoder();
  const bytes = enc.encode(raw);
  const digest = await crypto.subtle.digest('SHA-256', bytes);
  return Array.from(new Uint8Array(digest))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
};

const loadKioskSettings = () => {
  kioskError.value = '';
  kioskSaved.value = false;
  try {
    kioskEnabled.value = localStorage.getItem('ru_kiosk_enabled') === '1';
  } catch {
    kioskEnabled.value = false;
  }
  kioskScreenIdleSec.value = getKioskScreenIdleSec();
  // Do not load PIN back (security): user must retype to change it.
  kioskPin.value = '';
};

const saveKioskScreenIdle = async () => {
  const saved = setKioskScreenIdleSec(kioskScreenIdleSec.value);
  kioskScreenIdleSec.value = saved;
  await updateKioskMotionIdleTimeout(saved);
};

const saveKioskPinOnly = async () => {
  kioskError.value = '';
  kioskSaved.value = false;

  if (!isParent.value) {
    kioskError.value = 'Prístup len pre rodiča.';
    return;
  }

  const pin = String(kioskPin.value || '').trim();

  let existingHash = '';
  try {
    existingHash = localStorage.getItem('ru_kiosk_pin_hash') || '';
  } catch {}

  if (!pin && !existingHash) {
    kioskError.value = 'Najprv nastav PIN.';
    return;
  }
  if (!pin) {
    await saveKioskScreenIdle();
    kioskSaved.value = true;
    return;
  }
  if (!/^\d{4,6}$/.test(pin)) {
    kioskError.value = 'PIN musí mať 4 až 6 číslic.';
    return;
  }
  try {
    const h = await sha256Hex(pin);
    localStorage.setItem('ru_kiosk_pin_hash', h);
  } catch {
    kioskError.value = 'Nepodarilo sa uložiť PIN.';
    return;
  }
  await saveKioskScreenIdle();
  kioskSaved.value = true;
};

const startKiosk = async () => {
  kioskError.value = '';
  kioskSaved.value = false;

  if (!isParent.value) {
    kioskError.value = 'Prístup len pre rodiča.';
    return;
  }

  const pin = String(kioskPin.value || '').trim();
  let existingHash = '';
  try {
    existingHash = localStorage.getItem('ru_kiosk_pin_hash') || '';
  } catch {}

  // Require PIN to exist before starting.
  if (!pin && !existingHash) {
    kioskError.value = 'Najprv nastav PIN.';
    return;
  }

  // If user entered a new PIN, save it.
  if (pin) {
    await saveKioskPinOnly();
    if (kioskError.value) return;
  } else {
    await saveKioskScreenIdle();
  }

  try {
    localStorage.setItem('ru_kiosk_enabled', '1');
  } catch {}
  kioskEnabled.value = true;

  try {
    window.location.hash = '#/kiosk';
  } catch {}
};

const resetLoading = ref(false);
const resetError = ref('');
const resetSuccess = ref('');

const deleteAccountPassword = ref('');
const deleteAccountConfirm = ref('');
const deleteAccountLoading = ref(false);
const deleteAccountError = ref('');

const deleteAccount = async () => {
  if (deleteAccountLoading.value) return;
  deleteAccountError.value = '';

  if (
    !confirm(
      'Naozaj chcete natrvalo zrušiť účet? Táto akcia je nevratná.'
    )
  ) {
    return;
  }
  if (!confirm('Posledná šanca. Naozaj zrušiť účet?')) return;

  deleteAccountLoading.value = true;
  try {
    await api.deleteParentAccount({
      currentPassword: deleteAccountPassword.value,
      confirmText: deleteAccountConfirm.value,
    });
    deleteAccountPassword.value = '';
    deleteAccountConfirm.value = '';
    await api.logout();
    window.location.reload();
  } catch (e) {
    deleteAccountError.value = e?.message || 'Účet sa nepodarilo zrušiť';
  } finally {
    deleteAccountLoading.value = false;
  }
};

const saveParentPassword = async () => {
  passwordError.value = '';
  passwordSuccess.value = false;
  if (passwordSaving.value) return;

  const cur = String(parentCurrentPassword.value || '');
  const next = String(parentNewPassword.value || '');
  const next2 = String(parentNewPassword2.value || '');

  if (!cur || !next || !next2) {
    passwordError.value = 'Vyplň všetky polia.';
    return;
  }
  if (next.length < 6) {
    passwordError.value = 'Nové heslo musí mať aspoň 6 znakov.';
    return;
  }
  if (next !== next2) {
    passwordError.value = 'Nové heslá sa nezhodujú.';
    return;
  }

  passwordSaving.value = true;
  try {
    await api.changeParentPassword(cur, next);
    parentCurrentPassword.value = '';
    parentNewPassword.value = '';
    parentNewPassword2.value = '';
    passwordSuccess.value = true;
  } catch (e) {
    passwordError.value = e?.message || 'Heslo sa nepodarilo zmeniť';
  } finally {
    passwordSaving.value = false;
  }
};

const runReset = async (kind, fn) => {
  if (resetLoading.value) return;
  resetError.value = '';
  resetSuccess.value = '';
  const label =
    kind === 'children' ? 'deti' : kind === 'tasks' ? 'úlohy' : kind === 'rewards' ? 'odmeny' : 'dáta';
  if (!confirm(`Naozaj chceš resetovať ${label}? Táto akcia je nevratná.`)) return;
  resetLoading.value = true;
  try {
    await fn();
    resetSuccess.value = `Reset ${label} bol úspešný.`;
    try {
      emitRuDataChanged({ type: `reset_${kind}` });
    } catch {}
  } catch (e) {
    resetError.value = e?.message || `Reset ${label} sa nepodaril`;
  } finally {
    resetLoading.value = false;
  }
};

const resetChildren = () => runReset('children', api.resetChildren);
const resetTasks = () => runReset('tasks', api.resetTasks);
const resetRewards = () => runReset('rewards', api.resetRewards);

const doLogout = async () => {
  if (!confirm('Naozaj sa chcete odhlásiť?')) return;
  await api.logout();
  // simplest: reload app (forces login/token flow)
  window.location.reload();
};

const isParent = computed(() => {
  if (props.role === 'parent') return true;
  if (props.role === 'child') return false;
  if (props.localized && props.localized.isParent && !props.localized.forceChild) return true;
  return false;
});

const loadChild = async () => {
  if (isParent.value) return;
  if (!effectiveChildId.value) {
    childError.value = 'Chýba ID dieťaťa';
    childLoading.value = false;
    return;
  }
  childLoading.value = true;
  childError.value = '';
  try {
    const data = await api.getChildOverview(effectiveChildId.value, null);
    childData.value = data;
    avatarUrl.value = data.child?.avatar_url || '';
    // sync accent with server color
    if (data.child?.color) {
      localAccent.value = data.child.color;
      localStorage.setItem('ru-accent', data.child.color);
    }
  } catch (e) {
    childError.value = e?.message || 'Chyba pri načítaní dát';
  } finally {
    childLoading.value = false;
  }
};

const setAccent = async (c) => {
  if (isParent.value) {
    document.documentElement.style.setProperty('--ru-accent', parentAccentFixed);
    document.documentElement.style.setProperty('--ru-accent-light', `${parentAccentFixed}33`);
    return;
  }
  localAccent.value = c;
  localStorage.setItem('ru-accent', c);
  if (effectiveChildId.value) {
    try {
      await api.setChildColor(effectiveChildId.value, c);
      if (childData.value?.child) {
        childData.value.child.color = c;
      }
    } catch (err) {
      console.error(err);
      childError.value = err?.message || 'Chyba pri ukladaní farby';
    }
  } else if (childData.value?.child) {
    childData.value.child.color = c;
  }
};

const saveAvatar = async () => {
  if (!effectiveChildId.value) return;
  savingAvatar.value = true;
  try {
    if (avatarFile.value) {
      const res = await api.uploadChildAvatar(avatarFile.value);
      avatarUrl.value = res.url;
    }
    await api.saveChildAvatar(effectiveChildId.value, avatarUrl.value);
    if (childData.value?.child) {
      childData.value.child.avatar_url = avatarUrl.value;
    }
  } catch (e) {
    alert(e?.message || 'Chyba pri ukladaní avatara');
  } finally {
    savingAvatar.value = false;
  }
};

// PIN removed

const onFile = (e) => {
  const file = e.target.files && e.target.files[0];
  if (!file) return;
  avatarFile.value = file;
  // Modern UX: upload & save immediately after picking
  saveAvatar();
};

const removeAvatar = async () => {
  avatarFile.value = null;
  avatarUrl.value = '';
  await saveAvatar();
};

const shiftRotationNow = async () => {
  if (
    !confirm(
      'Naozaj chcete POSUNÚŤ rotáciu? Táto akcia vytvorí nový plán a rotačné úlohy sa posunú.'
    )
  )
    return;
  shiftingRotation.value = true;
  try {
    await api.shiftRotation();
    alert('Rotácia bola posunutá');
    try {
      emitRuDataChanged({ type: 'shift_rotation' });
    } catch {}
  } catch (e) {
    alert(e?.message || 'Chyba pri posune rotácie');
  } finally {
    shiftingRotation.value = false;
  }
};

const rotationFrequency = ref('weekly');
const rotationDay = ref('monday');
const rotationNextRunTs = ref(0);
const savingRotation = ref(false);
const savingTaskSettings = computed(() => savingRotation.value || savingMultiplier.value);

const rotationNextRunLabel = computed(() => {
  const ts = Number(rotationNextRunTs.value || 0);
  if (!ts) return '';
  try {
    return new Date(ts * 1000).toLocaleString();
  } catch {
    return '';
  }
});

const loadRotationSettings = async () => {
  try {
    const res = await api.getRotationSettings();
    if (res?.frequency) rotationFrequency.value = String(res.frequency);
    if (res?.day) rotationDay.value = String(res.day);
    rotationNextRunTs.value = Number(res?.nextRunTs || 0);
  } catch {
    // Ignore – settings panel still works with defaults.
  }
};

const saveRotationSettings = async () => {
  savingRotation.value = true;
  try {
    const res = await api.saveRotationSettings(rotationFrequency.value, rotationDay.value);
    rotationNextRunTs.value = Number(res?.nextRunTs || 0);
    alert('Nastavenie rotácie bolo uložené');
  } catch (e) {
    alert(e?.message || 'Chyba pri uložení nastavenia rotácie');
  } finally {
    savingRotation.value = false;
  }
};

const saveWeekendMultiplier = async () => {
  const val = Math.max(1, parseFloat(weekendMultiplier.value) || 1);
  weekendMultiplier.value = val;
  savingMultiplier.value = true;
  try {
    await api.saveWeekendMultiplier(val);
    alert('Multiplikátor bol uložený');
  } catch (e) {
    alert(e?.message || 'Chyba pri uložení multiplikátora');
  } finally {
    savingMultiplier.value = false;
  }
};

const saveTaskSettings = async () => {
  savingRotation.value = true;
  const val = Math.max(1, parseFloat(weekendMultiplier.value) || 1);
  weekendMultiplier.value = val;
  savingMultiplier.value = true;
  try {
    const [rotationRes] = await Promise.all([
      api.saveRotationSettings(rotationFrequency.value, rotationDay.value),
      api.saveWeekendMultiplier(val),
    ]);
    rotationNextRunTs.value = Number(rotationRes?.nextRunTs || 0);
    alert('Nastavenia boli uložené');
  } catch (e) {
    alert(e?.message || 'Chyba pri ukladaní nastavení');
  } finally {
    savingRotation.value = false;
    savingMultiplier.value = false;
  }
};

onMounted(async () => {
  if (Capacitor.isNativePlatform()) {
    nativeAppVersion.value = await getNativeAppVersion();
  }
  if (!isParent.value) {
    loadChild();
  } else {
    loadRotationSettings();
    loadKioskSettings();
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
.ru-coin {
  width: 25px;
  height: 25px;
  object-fit: contain;
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

.ru-settings-modal .ru-section {
  margin: 0 0 16px;
  padding: 0;
  background: transparent;
  border: 0;
  border-radius: 0;
  box-shadow: none;
}

.ru-settings-modal .ru-section:last-child {
  margin-bottom: 0;
}

.ru-settings-modal .ru-section__header {
  margin-bottom: 10px;
}

.ru-settings-modal .ru-btn {
  margin-top: 8px;
}

.ru-settings-modal .ru-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}

.ru-settings-modal .ru-field > span,
.ru-settings-modal .ru-field.inline label > span,
.ru-settings-modal .ru-choice__label {
  font-weight: 800;
  color: #0f172a;
  font-size: 14px;
  line-height: 1.2;
}

.ru-settings-modal .ru-field input,
.ru-settings-modal .ru-field textarea,
.ru-settings-modal .ru-field select,
.ru-settings-modal .ru-pin__input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #ffffff;
  color: #0f172a;
  font-size: 16px;
  font-weight: 700;
  line-height: 1.35;
  font-family: inherit;
  box-sizing: border-box;
}

.ru-settings-modal .ru-field input::placeholder,
.ru-settings-modal .ru-field textarea::placeholder,
.ru-settings-modal .ru-field select::placeholder,
.ru-settings-modal .ru-pin__input::placeholder {
  color: #94a3b8;
  font-weight: 600;
}

.ru-settings-modal .ru-field.inline {
  flex-direction: row;
  align-items: flex-end;
  gap: 10px;
  flex-wrap: wrap;
}

.ru-settings-modal .ru-field.inline label {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ru-settings-modal .ru-help,
.ru-settings-modal .ru-card__subtitle,
.ru-settings-modal .ru-daycard__meta,
.ru-settings-modal .ru-about__label,
.ru-settings-modal .ru-about__value,
.ru-settings-modal .ru-form-msg {
  font-family: inherit;
}

.ru-section {
  margin: 0 0 14px;
  padding: 14px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.ru-section__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.ru-avatar-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding-top: 6px;
}

.ru-avatar-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
}

.ru-file-btn input[type="file"] {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
  padding: 0;
  margin: -1px;
}

.ru-file-btn {
  position: relative;
}

.ru-app-footer {
  display: flex;
  justify-content: center;
  padding: 18px 0 6px;
  opacity: 0.7;
}
.ru-app-footer img {
  max-width: 100px;
  max-height: 100px;
  width: auto;
  height: auto;
  display: block;
}

@media (max-width: 768px) {
  .ru-avatar {
    width: 80px;
    height: 80px;
    font-size: 18px;
  }
  .ru-settings-modal .ru-field input,
  .ru-settings-modal .ru-field textarea,
  .ru-settings-modal .ru-field select {
    width: 100%;
  }
}

.ru-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 10px;
}
.ru-field.inline {
  flex-direction: row;
  align-items: flex-end;
  gap: 10px;
  flex-wrap: wrap;
}
.ru-field input {
  width: 260px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
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
.ru-settings-item__chev {
  color: #94a3b8;
  font-size: 22px;
  line-height: 1;
  flex-shrink: 0;
}

.ru-kiosk-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
@media (max-width: 520px) {
  .ru-settings-modal .ru-field.inline {
    flex-direction: column;
    align-items: stretch;
  }

  .ru-kiosk-actions {
    grid-template-columns: 1fr;
  }
}

.ru-about {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ru-about__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  min-width: 0;
}
.ru-about__label {
  color: #64748b;
  font-weight: 700;
  font-size: 13px;
  flex-shrink: 0;
}
.ru-about__value {
  color: #0f172a;
  font-weight: 800;
  font-size: 13px;
  min-width: 0;
  text-align: right;
  overflow-wrap: anywhere;
}
.ru-about__link {
  color: var(--ru-accent, #0ea5e9);
  text-decoration: none;
}
.ru-about__update {
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid rgba(15, 23, 42, 0.08);
}
.ru-about__update-text {
  margin: 0 0 12px;
  color: #334155;
  font-weight: 700;
  font-size: 13px;
  line-height: 1.45;
}
@media (max-width: 640px) {
  .ru-about__row {
    flex-direction: column;
    gap: 4px;
  }

  .ru-about__value {
    text-align: left;
  }
}
.ru-about__logo {
  display: flex;
  justify-content: center;
  padding: 10px 0 0;
  opacity: 0.9;
}
.ru-about__logo img {
  height: 80px;
  width: auto;
  display: block;
}

.ru-colors {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
  gap: 10px;
  max-width: 320px;
}
.ru-colors button {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: 2px solid transparent;
  cursor: pointer;
}
.ru-colors button.active {
  border-color: var(--ru-accent, #0ea5e9);
  box-shadow: 0 0 0 3px var(--ru-accent-light, #e0f2fe);
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
.ru-switch {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.ru-file {
  display: flex;
  flex-direction: column;
  gap: 4px;
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

.ru-btn--no-shadow {
  box-shadow: none;
}

/* Rotation settings (pretty UI) */
.ru-rotation {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 10px 0 6px;
}

.ru-choice__label {
  font-weight: 800;
  color: #0f172a;
  font-size: 13px;
  margin-bottom: 8px;
}

.ru-segment {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  padding: 8px;
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.04);
  border: 1px solid rgba(15, 23, 42, 0.08);
}

.ru-segment__btn {
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  color: #0f172a;
  border-radius: 14px;
  padding: 10px 10px;
  font-weight: 800;
  font-size: 13px;
  cursor: pointer;
  transition: box-shadow 120ms ease, border-color 120ms ease, transform 80ms ease;
}

.ru-segment__btn:hover {
  border-color: rgba(15, 23, 42, 0.18);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.ru-segment__btn:active {
  transform: translateY(1px);
}

.ru-segment__btn.active {
  border-color: var(--ru-accent, #0ea5e9);
  box-shadow: 0 0 0 3px var(--ru-accent-light, #e0f2fe);
}

.ru-daygrid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.ru-daycard {
  width: 100%;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  border-radius: 16px;
  padding: 12px 12px;
  text-align: left;
  cursor: pointer;
  transition: box-shadow 120ms ease, border-color 120ms ease, transform 80ms ease;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-height: 68px;
}

.ru-daycard:hover {
  border-color: rgba(15, 23, 42, 0.18);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.ru-daycard:active {
  transform: translateY(1px);
}

.ru-daycard.active {
  border-color: var(--ru-accent, #0ea5e9);
  box-shadow: 0 0 0 3px var(--ru-accent-light, #e0f2fe);
}

.ru-daycard__title {
  font-weight: 900;
  color: #0f172a;
  font-size: 13px;
}

.ru-daycard__meta {
  color: #64748b;
  font-weight: 700;
  font-size: 12px;
  line-height: 1.2;
}

.ru-help {
  margin: 8px 2px 0;
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}
.ru-help.subtle {
  color: #64748b;
}

@media (max-width: 520px) {
  .ru-daygrid {
    grid-template-columns: 1fr;
  }
}

/* Shift rotation (simple) */
.ru-rotation-hero {
  display: flex;
  justify-content: center;
  padding: 8px 0 14px;
}
.ru-rotation-hero__icon {
  width: 96px;
  height: 96px;
  stroke: var(--ru-accent, #5abb6f);
  stroke-width: 3.5;
  stroke-linecap: round;
  stroke-linejoin: round;
  opacity: 0.95;
  filter: drop-shadow(0 1px 2px rgba(15, 23, 42, 0.10));
}
.ru-rotation-hero__icon rect {
  stroke: var(--ru-accent, #5abb6f);
}

.ru-lang-switcher {
  display: flex;
  gap: 10px;
  margin-top: 12px;
}

.ru-lang-switcher .ru-btn {
  flex: 1;
}
</style>

