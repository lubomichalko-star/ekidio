<template>
  <section class="ru-login ru-login--lubo">
    <div class="ru-login__center">
      <BrandLogo class="ru-login__logo" variant="green" size="lg" />

      <div class="ru-login__bottom" v-if="step === 'choose'">
        <button class="ru-btn ru-btn--primary ru-btn--full" type="button" @click="goToStep('parent')">
          {{ t('auth.login.parentTitle') }}
        </button>
        <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('child')">
          {{ t('auth.login.childTitle') }}
        </button>
      </div>

      <div class="ru-login__panel" v-if="step !== 'choose'">
        <button class="ru-back" type="button" @click="reset">{{ t('auth.login.back') }}</button>

        <form v-if="step === 'parent' && !showForgotPassword" class="ru-form" @submit.prevent="submitParent">
          <h2>{{ t('auth.login.title') }}</h2>
          <label class="ru-field">
            <span>{{ t('fields.email') }}</span>
            <input v-model="username" type="text" autocomplete="username" />
          </label>
          <label class="ru-field">
            <span>{{ t('fields.password') }}</span>
            <input v-model="password" type="password" autocomplete="current-password" />
          </label>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">
            {{ loading ? t('auth.login.logging') : t('auth.login.button') }}
          </button>
          <div class="ru-divider" v-if="isGoogleEnabled"><span>{{ t('auth.google.orContinue') }}</span></div>
          <button v-if="isNativeGoogleEnabled" class="ru-btn ru-btn--google ru-btn--full" type="button" @click="submitGoogle" :disabled="loading">
            {{ loading ? t('auth.google.connecting') : t('auth.google.continue') }}
          </button>
          <button v-else-if="isWebGoogleEnabled" class="ru-btn ru-btn--google ru-btn--full" type="button" @click="triggerWebGoogleSignIn" :disabled="loading">
            {{ loading ? t('auth.google.connecting') : t('auth.google.continue') }}
          </button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('register')">{{ t('auth.register.createAccount') }}</button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('invite')">{{ t('auth.invite.title') }}</button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="openForgotPassword">{{ t('auth.forgotPassword.link') }}</button>
        </form>

        <div v-else-if="step === 'parent' && showForgotPassword" class="ru-forgot-password">
          <h2>{{ t('auth.forgotPassword.title') }}</h2>
          <label class="ru-field">
            <span>{{ t('fields.email') }}</span>
            <input v-model="forgotEmail" type="email" autocomplete="email" />
          </label>
          <p v-if="forgotSuccess" class="ru-success">{{ forgotSuccess }}</p>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button type="button" class="ru-btn ru-btn--primary ru-btn--full" @click="forgotPassword" :disabled="loading">
            {{ t('auth.forgotPassword.sendResetLink') }}
          </button>
        </div>

        <form v-else-if="step === 'register'" class="ru-form" @submit.prevent="submitRegister">
          <h2>{{ t('auth.register.title') }}</h2>
          <p class="ru-sub">{{ t('auth.register.desc') }}</p>
          <label class="ru-field"><span>{{ t('fields.name') }}</span><input v-model="registerFirstName" type="text" autocomplete="given-name" /></label>
          <label class="ru-field"><span>{{ t('fields.surname') }}</span><input v-model="registerLastName" type="text" autocomplete="family-name" /></label>
          <label class="ru-field"><span>{{ t('fields.email') }}</span><input v-model="registerEmail" type="email" autocomplete="email" /></label>
          <label class="ru-field"><span>{{ t('fields.password') }}</span><input v-model="registerPassword" type="password" autocomplete="new-password" /></label>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">
            {{ loading ? t('auth.register.creating') : t('auth.register.button') }}
          </button>
          <div class="ru-divider" v-if="isGoogleEnabled"><span>{{ t('auth.google.orContinue') }}</span></div>
          <button v-if="isNativeGoogleEnabled" class="ru-btn ru-btn--google ru-btn--full" type="button" @click="submitGoogle" :disabled="loading">
            {{ loading ? t('auth.google.connecting') : t('auth.google.continue') }}
          </button>
          <button v-else-if="isWebGoogleEnabled" class="ru-btn ru-btn--google ru-btn--full" type="button" @click="triggerWebGoogleSignIn" :disabled="loading">
            {{ loading ? t('auth.google.connecting') : t('auth.google.continue') }}
          </button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('parent')">{{ t('auth.register.yesAccount') }}</button>
        </form>

        <form v-else-if="step === 'verify'" class="ru-form" @submit.prevent="submitVerify">
          <h2>{{ t('auth.verify.title') }}</h2>
          <p class="ru-sub">{{ t('auth.verify.desc') }} <strong>{{ verifyEmail }}</strong></p>
          <label class="ru-field"><span>{{ t('auth.verify.code') }}</span><input v-model="verificationCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456" /></label>
          <p class="ru-note">{{ t('auth.verify.spamCheck') }}</p>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">{{ loading ? t('auth.verify.verifying') : t('auth.verify.button') }}</button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="resendVerificationCode" :disabled="loading">{{ t('auth.verify.sendAgain') }}</button>
        </form>

        <form v-else-if="step === 'invite'" class="ru-form" @submit.prevent="submitInvite">
          <h2>{{ t('auth.invite.title') }}</h2>
          <p class="ru-sub">{{ t('auth.invite.desc') }}</p>
          <label class="ru-field"><span>{{ t('auth.invite.code') }}</span><input v-model="inviteCode" type="text" autocomplete="one-time-code" /></label>
          <label class="ru-field"><span>{{ t('fields.name') }}</span><input v-model="inviteFirstName" type="text" autocomplete="given-name" /></label>
          <label class="ru-field"><span>{{ t('fields.surname') }}</span><input v-model="inviteLastName" type="text" autocomplete="family-name" /></label>
          <label class="ru-field"><span>{{ t('fields.password') }}</span><input v-model="invitePassword" type="password" autocomplete="new-password" /></label>
          <label class="ru-field"><span>{{ t('fields.passwordAgain') }}</span><input v-model="invitePassword2" type="password" autocomplete="new-password" /></label>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">{{ loading ? t('auth.invite.activating') : t('auth.invite.activate') }}</button>
          <button class="ru-btn ghost ru-btn--full" type="button" @click="goToStep('parent')">{{ t('auth.login.backToLogin') }}</button>
        </form>

        <form v-else-if="step === 'child'" class="ru-form" @submit.prevent="submitChild">
          <h2>{{ t('auth.login.childTitle') }}</h2>
          <p class="ru-sub">{{ t('fields.codeDesc') }}</p>
          <label class="ru-field"><span>{{ t('fields.pin') }}</span><input v-model="childCode" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" placeholder="123456" /></label>
          <p class="ru-error" v-if="error">{{ error }}</p>
          <button class="ru-btn ru-btn--primary ru-btn--full" type="submit" :disabled="loading">{{ loading ? t('auth.login.logging') : t('common.continue') }}</button>
        </form>
      </div>

      <div class="ru-login__lang">
        <LangSwitcherPill />
      </div>

      <p class="ru-build-stamp" v-if="appVersionLabel">{{ t('settings.aboutApp.version') }} {{ appVersionLabel }}</p>
    </div>
    <div v-if="isWebGoogleEnabled" ref="webGoogleButtonHost" class="ru-google-button-host" aria-hidden="true"></div>
  </section>
</template>

<script setup>
import BrandLogo from '../components/BrandLogo.vue';
import LangSwitcherPill from '../components/LangSwitcherPill.vue';
import { useLoginFlow } from '../composables/useLoginFlow';

const flow = useLoginFlow();
const {
  step, username, password, childCode, inviteCode, invitePassword, invitePassword2,
  inviteFirstName, inviteLastName, registerFirstName, registerLastName, registerEmail,
  registerPassword, verifyEmail, verificationCode, loading, error, webGoogleButtonHost,
  showForgotPassword, forgotEmail, forgotSuccess, appVersionLabel, isGoogleEnabled,
  isNativeGoogleEnabled, isWebGoogleEnabled, t, goToStep, reset, openForgotPassword,
  submitParent, submitRegister, submitVerify, resendVerificationCode, submitInvite,
  submitChild, submitGoogle, triggerWebGoogleSignIn, forgotPassword,
} = flow;
</script>

<style scoped>
.ru-login--lubo {
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
.ru-login__center { width: 100%; max-width: 400px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; padding-top: 24px; }
@media (min-width: 768px) { .ru-login__center { padding-top: 324px; } }
.ru-login__lang {
  width: 100%;
  display: flex;
  justify-content: center;
}
.ru-build-stamp { margin: 18px 0 0; color: #94a3b8; font-size: 11px; font-weight: 700; text-align: center; }
.ru-login__logo { margin-bottom: 18px; }
.ru-login__bottom {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  width: 100%;
  margin: 0 0 14px;
}

.ru-login--lubo .ru-btn--full {
  width: 100%;
  max-width: 100%;
}
.ru-login__panel { width: 100%; background: #fff; border: 1px solid rgba(15, 23, 42, 0.1); border-radius: 16px; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06); padding: 14px; }
.ru-back { border: 0; background: transparent; color: var(--ru-accent); font-weight: 800; padding: 8px 0 10px; }
.ru-form h2 { margin: 4px 0 6px; font-size: 18px; font-weight: 900; }
.ru-sub { margin: 0 0 12px; color: #64748b; font-weight: 700; font-size: 13px; }
.ru-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
.ru-field span { font-weight: 700; color: #0f172a; font-size: 13px; }
.ru-field input { height: 44px; padding: 0 12px; border-radius: 14px; border: 1px solid rgba(15, 23, 42, 0.1); background: #fff; font-weight: 800; color: #0f172a; }
.ru-field input:focus { outline: none; border-color: var(--ru-accent); box-shadow: 0 0 0 3px var(--ru-accent-light); }
.ru-login--lubo .ru-btn.ghost { background: #fff; color: var(--ru-accent); border-color: var(--ru-accent); }
.ru-divider { display: flex; align-items: center; gap: 10px; margin: 14px 0 10px; color: #64748b; font-size: 12px; font-weight: 700; }
.ru-divider::before, .ru-divider::after { content: ''; flex: 1; height: 1px; background: rgba(15, 23, 42, 0.12); }
.ru-google-button-host { position: fixed; left: -9999px; top: 0; width: 1px; height: 1px; overflow: hidden; opacity: 0; pointer-events: none; }
.ru-btn--google { margin-bottom: 10px; background: #fff; color: #0f172a; border: 1px solid rgba(15, 23, 42, 0.12); }
.ru-note { margin: 8px 0; color: #64748b; font-size: 13px; font-weight: 700; }
.ru-error { color: #b91c1c; font-weight: 800; margin: 8px 0; }
.ru-success { color: #15803d; font-weight: 700; margin: 8px 0; }
</style>
