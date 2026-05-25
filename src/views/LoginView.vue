<template>
  <section class="ru-login">
    <div class="ru-login__center">
      <img class="ru-login__logo" :src="logoUrl" alt="ekidio" />

      <div class="ru-login__bottom" v-if="step === 'choose'">
        <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="goToStep('parent')">
          Prihlásenie rodiča
        </button>
        <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('child')">
          Som dieťa
        </button>
      </div>
      <div class="ru-login__panel" v-if="step !== 'choose'">
        <button class="ru-back" type="button" @click="reset">
          ‹ Späť
        </button>

        <form v-if="step === 'parent'" class="ru-form" @submit.prevent="submitParent">
          <h2>Prihlásenie</h2>
          <label class="ru-field">
            <span>Email alebo používateľské meno</span>
            <input v-model="username" type="text" autocomplete="username" />
          </label>
          <label class="ru-field">
            <span>Heslo</span>
            <input v-model="password" type="password" autocomplete="current-password" />
          </label>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">
            {{ loading ? 'Prihlasujem…' : 'Prihlásiť sa' }}
          </button>

          <div class="ru-divider" v-if="isGoogleEnabled">
            <span>Alebo pokračuj cez Google</span>
          </div>
          <button
            v-if="isNativeGoogleEnabled"
            class="ru-btn ru-btn--google ru-btn--full"
            type="button"
            @click="submitGoogle"
            :disabled="loading"
          >
            {{ loading ? 'Pripájam Google…' : 'Pokračovať cez Google' }}
          </button>
          <div v-else-if="isWebGoogleEnabled" ref="parentGoogleButton" class="ru-google-button"></div>

          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('register')">
            Vytvoriť účet
          </button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('invite')">
            Mám pozvánku
          </button>
        </form>

        <form v-else-if="step === 'register'" class="ru-form" @submit.prevent="submitRegister">
          <h2>Registrácia</h2>
          <p class="ru-sub">
            Vytvor si rodičovský účet. Overovací kód pošleme na email.
          </p>
          <label class="ru-field">
            <span>Meno</span>
            <input v-model="registerFirstName" type="text" autocomplete="given-name" />
          </label>
          <label class="ru-field">
            <span>Priezvisko</span>
            <input v-model="registerLastName" type="text" autocomplete="family-name" />
          </label>
          <label class="ru-field">
            <span>Email</span>
            <input v-model="registerEmail" type="email" autocomplete="email" />
          </label>
          <label class="ru-field">
            <span>Heslo</span>
            <input v-model="registerPassword" type="password" autocomplete="new-password" />
          </label>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">
            {{ loading ? 'Registrujem…' : 'Registrovať sa' }}
          </button>

          <div class="ru-divider" v-if="isGoogleEnabled">
            <span>Alebo pokračuj cez Google</span>
          </div>
          <button
            v-if="isNativeGoogleEnabled"
            class="ru-btn ru-btn--google ru-btn--full"
            type="button"
            @click="submitGoogle"
            :disabled="loading"
          >
            {{ loading ? 'Pripájam Google…' : 'Registrovať sa cez Google' }}
          </button>
          <div v-else-if="isWebGoogleEnabled" ref="registerGoogleButton" class="ru-google-button"></div>

          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('parent')">
            Už mám účet
          </button>
        </form>

        <form v-else-if="step === 'verify'" class="ru-form" @submit.prevent="submitVerify">
          <h2>Overenie emailu</h2>
          <p class="ru-sub">
            Na <strong>{{ verifyEmail }}</strong> sme poslali overovací kód.
          </p>
          <label class="ru-field">
            <span>Overovací kód</span>
            <input
              v-model="verificationCode"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="6"
              placeholder="123456"
            />
          </label>
          <p class="ru-note">Ak email nevidíš, skontroluj aj spam.</p>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">
            {{ loading ? 'Overujem…' : 'Overiť email' }}
          </button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="resendVerificationCode" :disabled="loading">
            Poslať kód znova
          </button>
        </form>

        <form v-else-if="step === 'invite'" class="ru-form" @submit.prevent="submitInvite">
          <h2>Mám pozvánku</h2>
          <p class="ru-sub">
            Zadaj pozývací kód z emailu a nastav si heslo.
          </p>
          <label class="ru-field">
            <span>Pozývací kód</span>
            <input v-model="inviteCode" type="text" autocomplete="one-time-code" />
          </label>
          <label class="ru-field">
            <span>Meno (voliteľné)</span>
            <input v-model="inviteFirstName" type="text" autocomplete="given-name" />
          </label>
          <label class="ru-field">
            <span>Priezvisko (voliteľné)</span>
            <input v-model="inviteLastName" type="text" autocomplete="family-name" />
          </label>
          <label class="ru-field">
            <span>Heslo</span>
            <input v-model="invitePassword" type="password" autocomplete="new-password" />
          </label>
          <label class="ru-field">
            <span>Heslo znova</span>
            <input v-model="invitePassword2" type="password" autocomplete="new-password" />
          </label>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">
            {{ loading ? 'Aktivujem…' : 'Aktivovať prístup' }}
          </button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('parent')">
            Späť na prihlásenie
          </button>
        </form>

        <form v-else-if="step === 'child'" class="ru-form" @submit.prevent="submitChild">
          <h2>Som dieťa</h2>
          <p class="ru-sub">
            Zadaj 6‑miestny kód z rodičovskej aplikácie.
          </p>
          <label class="ru-field">
            <span>Kód</span>
            <input
              v-model="childCode"
              inputmode="numeric"
              autocomplete="one-time-code"
              pattern="[0-9]*"
              maxlength="6"
              placeholder="123456"
            />
          </label>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">
            {{ loading ? 'Prihlasujem…' : 'Pokračovať' }}
          </button>
        </form>
      </div>
    </div>
  </section>
</template>

<script setup>
import { Capacitor } from '@capacitor/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import logoUrl from '../images/logo-gen.png';
import { api } from '../api/client';
import { initializeNativeGoogleSignIn, signInWithNativeGoogle } from '../auth/googleAuth';
import { getGoogleClientId } from '../config/appConfig';

const router = useRouter();

const step = ref('choose'); // choose | parent | register | verify | invite | child
const username = ref('');
const password = ref('');
const childCode = ref('');
const inviteCode = ref('');
const invitePassword = ref('');
const invitePassword2 = ref('');
const inviteFirstName = ref('');
const inviteLastName = ref('');
const registerFirstName = ref('');
const registerLastName = ref('');
const registerEmail = ref('');
const registerPassword = ref('');
const verifyEmail = ref('');
const verificationCode = ref('');
const loading = ref(false);
const error = ref('');
const parentGoogleButton = ref(null);
const registerGoogleButton = ref(null);

const googleClientId = getGoogleClientId();
const isNativePlatform = Capacitor.isNativePlatform();
const isGoogleEnabled = computed(() => !!googleClientId);
const isNativeGoogleEnabled = computed(() => isNativePlatform && !!googleClientId);
const isWebGoogleEnabled = computed(() => !isNativePlatform && !!googleClientId);

let googleScriptPromise = null;
let googleInitialized = false;

const clearError = () => {
  error.value = '';
};

const goToStep = async (nextStep) => {
  step.value = nextStep;
  clearError();
  loading.value = false;
  await nextTick();
  if (isWebGoogleEnabled.value) renderGoogleButton();
};

const reset = () => {
  step.value = 'choose';
  username.value = '';
  password.value = '';
  childCode.value = '';
  inviteCode.value = '';
  invitePassword.value = '';
  invitePassword2.value = '';
  inviteFirstName.value = '';
  inviteLastName.value = '';
  registerFirstName.value = '';
  registerLastName.value = '';
  registerEmail.value = '';
  registerPassword.value = '';
  verifyEmail.value = '';
  verificationCode.value = '';
  clearError();
  loading.value = false;
};

const ensureGoogleScript = async () => {
  if (!isWebGoogleEnabled.value || typeof window === 'undefined') return false;
  if (window.google?.accounts?.id) return true;
  if (!googleScriptPromise) {
    googleScriptPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      script.onload = () => resolve(true);
      script.onerror = () => reject(new Error('Google prihlásenie sa nepodarilo načítať'));
      document.head.appendChild(script);
    });
  }
  return googleScriptPromise;
};

const handleGoogleCredential = async (response) => {
  clearError();
  loading.value = true;
  try {
    await api.loginWithGoogle(response?.credential || '');
    await router.replace('/');
  } catch (e) {
    error.value = e?.message || 'Chyba pri Google prihlásení';
  } finally {
    loading.value = false;
  }
};

const submitGoogle = async () => {
  clearError();
  loading.value = true;
  try {
    const credential = await signInWithNativeGoogle(googleClientId);
    if (!credential) {
      error.value = 'Google prihlásenie sa nepodarilo dokončiť';
      return;
    }
    await api.loginWithGoogle(credential);
    await router.replace('/');
  } catch (e) {
    error.value = e?.message || 'Chyba pri Google prihlásení';
  } finally {
    loading.value = false;
  }
};

const renderGoogleButton = async () => {
  if (!isWebGoogleEnabled.value) return;
  const container = step.value === 'parent'
    ? parentGoogleButton.value
    : step.value === 'register'
      ? registerGoogleButton.value
      : null;
  if (!container) return;

  try {
    await ensureGoogleScript();
    if (!window.google?.accounts?.id) return;
    if (!googleInitialized) {
      window.google.accounts.id.initialize({
        client_id: googleClientId,
        callback: handleGoogleCredential,
      });
      googleInitialized = true;
    }
    container.innerHTML = '';
    window.google.accounts.id.renderButton(container, {
      type: 'standard',
      theme: 'outline',
      size: 'large',
      shape: 'pill',
      text: step.value === 'register' ? 'signup_with' : 'continue_with',
      width: Math.max(container.clientWidth || 0, 280),
    });
  } catch (e) {
    error.value = e?.message || 'Google prihlásenie sa nepodarilo načítať';
  }
};

watch(step, async () => {
  await nextTick();
  if (isWebGoogleEnabled.value) renderGoogleButton();
});

onMounted(async () => {
  await nextTick();
  if (isNativeGoogleEnabled.value) {
    try {
      await initializeNativeGoogleSignIn(googleClientId);
    } catch {}
    return;
  }
  if (isWebGoogleEnabled.value) renderGoogleButton();
});

const submitParent = async () => {
  clearError();
  loading.value = true;
  try {
    await api.loginParent(username.value, password.value);
    await router.replace('/');
  } catch (e) {
    error.value = e?.message || 'Chyba pri prihlásení';
  } finally {
    loading.value = false;
  }
};

const submitRegister = async () => {
  clearError();
  loading.value = true;
  try {
    const firstName = String(registerFirstName.value || '').trim();
    const lastName = String(registerLastName.value || '').trim();
    const email = String(registerEmail.value || '').trim();
    const registerPasswordValue = String(registerPassword.value || '');
    if (!firstName || !lastName || !email) {
      error.value = 'Vyplň meno, priezvisko a email';
      return;
    }
    if (registerPasswordValue.length < 6) {
      error.value = 'Heslo musí mať aspoň 6 znakov';
      return;
    }
    await api.registerParent({
      firstName,
      lastName,
      email,
      password: registerPasswordValue,
    });
    verifyEmail.value = email;
    verificationCode.value = '';
    await goToStep('verify');
  } catch (e) {
    error.value = e?.message || 'Chyba pri registrácii';
  } finally {
    loading.value = false;
  }
};

const submitVerify = async () => {
  clearError();
  loading.value = true;
  try {
    const code = String(verificationCode.value || '').replace(/\D+/g, '').slice(0, 6);
    if (code.length !== 6) {
      error.value = 'Zadaj 6-miestny overovací kód';
      return;
    }
    await api.verifyParentRegistration({
      email: verifyEmail.value,
      code,
    });
    await router.replace('/');
  } catch (e) {
    error.value = e?.message || 'Chyba pri overení emailu';
  } finally {
    loading.value = false;
  }
};

const resendVerificationCode = async () => {
  clearError();
  loading.value = true;
  try {
    await api.resendParentRegistrationCode(verifyEmail.value);
  } catch (e) {
    error.value = e?.message || 'Kód sa nepodarilo odoslať';
  } finally {
    loading.value = false;
  }
};

const submitInvite = async () => {
  clearError();
  loading.value = true;
  try {
    const code = String(inviteCode.value || '').trim();
    if (code.length < 12) {
      error.value = 'Zadaj pozývací kód';
      return;
    }
    const p1 = String(invitePassword.value || '');
    const p2 = String(invitePassword2.value || '');
    if (p1.length < 6) {
      error.value = 'Heslo musí mať aspoň 6 znakov';
      return;
    }
    if (p1 !== p2) {
      error.value = 'Heslá sa nezhodujú';
      return;
    }
    await api.acceptInvite(code, p1, { firstName: inviteFirstName.value, lastName: inviteLastName.value });
    await router.replace('/');
  } catch (e) {
    error.value = e?.message || 'Chyba pri aktivácii pozvánky';
  } finally {
    loading.value = false;
  }
};

const submitChild = async () => {
  clearError();
  loading.value = true;
  try {
    const code = String(childCode.value || '').replace(/\D+/g, '').slice(0, 6);
    if (code.length !== 6) {
      error.value = 'Zadaj 6‑miestny kód';
      return;
    }
    await api.loginChildByCode(code);
    await router.replace('/');
  } catch (e) {
    error.value = e?.message || 'Chyba pri prihlásení';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.ru-login {
  /* Force a real viewport height so flex layout is reliable on Android WebView */
  box-sizing: border-box;
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #f3f4f6;
  justify-content: flex-start;
  padding: 16px 16px calc(24px + env(safe-area-inset-bottom, 0px));
  overflow-y: auto;
}

.ru-login__center {
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  padding-top: 24px;
}

@media (min-width: 768px) {
  .ru-login__center {
    padding-top: 324px;
  }
}

.ru-login__logo {
  width: min(220px, 60vw);
  max-height: 32vh;
  height: auto;
  opacity: 0.95;
  margin-bottom: 18px;
}

.ru-login__bottom {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  width: 100%;
  margin: 0 0 14px;
}

.ru-login__panel {
  width: 100%;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  padding: 14px;
  margin: 0;
}

.ru-back {
  border: 0;
  background: transparent;
  color: #0f172a;
  font-weight: 800;
  padding: 8px 0 10px;
}

.ru-form h2 {
  margin: 4px 0 6px;
  font-size: 18px;
  font-weight: 900;
}

.ru-sub {
  margin: 0 0 12px;
  color: #64748b;
  font-weight: 700;
  font-size: 13px;
}

.ru-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 10px;
}

.ru-field span {
  font-weight: 700;
  color: #0f172a;
  font-size: 13px;
}

.ru-field input {
  height: 44px;
  padding: 0 12px;
  border-radius: 14px;
  border: 1px solid rgba(15, 23, 42, 0.1);
  background: #ffffff;
  font-weight: 800;
  color: #0f172a;
}

.ru-divider {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 14px 0 10px;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.ru-divider::before,
.ru-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(15, 23, 42, 0.12);
}

.ru-google-button {
  width: 100%;
  display: flex;
  justify-content: center;
  margin-bottom: 10px;
}

.ru-btn--google {
  margin-bottom: 10px;
  background: #ffffff;
  color: #0f172a;
  border: 1px solid rgba(15, 23, 42, 0.12);
}

.ru-note {
  margin: 8px 0;
  color: #64748b;
  font-size: 13px;
  font-weight: 700;
}

.ru-error {
  color: #b91c1c;
  font-weight: 800;
  margin: 8px 0;
}
</style>

