import { Capacitor } from '@capacitor/core';
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api/client';
import { initializeNativeGoogleSignIn, signInWithNativeGoogle } from '../auth/googleAuth';
import { getGoogleClientId } from '../config/appConfig';
import { getAppVersionLabel } from '../lib/appVersion';
import { t } from '../i18n';

export function useLoginFlow() {
  const router = useRouter();

  const step = ref('choose');
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
  const webGoogleButtonHost = ref(null);
  const showForgotPassword = ref(false);
  const forgotEmail = ref('');
  const forgotSuccess = ref('');

  const googleClientId = getGoogleClientId();
  const appVersionLabel = getAppVersionLabel();
  const FAV_GREEN = '#5abb6f';
  const FAV_GREEN_LIGHT = '#5abb6f33';
  const isNativePlatform = Capacitor.isNativePlatform();
  const isGoogleEnabled = computed(() => !!googleClientId);
  const isNativeGoogleEnabled = computed(() => isNativePlatform && !!googleClientId);
  const isWebGoogleEnabled = computed(() => !isNativePlatform && !!googleClientId);

  let googleScriptPromise = null;
  let googleInitialized = false;

  const clearError = () => {
    error.value = '';
  };

  const goAfterLogin = async () => {
    await nextTick();
    try {
      await router.isReady();
    } catch {}
    await router.replace('/');
  };

  const goToStep = async (nextStep) => {
    step.value = nextStep;
    showForgotPassword.value = false;
    forgotSuccess.value = '';
    clearError();
    loading.value = false;
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
    showForgotPassword.value = false;
    forgotEmail.value = '';
    forgotSuccess.value = '';
    clearError();
    loading.value = false;
  };

  const openForgotPassword = () => {
    clearError();
    forgotSuccess.value = '';
    forgotEmail.value = String(username.value || '').trim();
    showForgotPassword.value = true;
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
        script.onerror = () => reject(new Error(t('errors.auth.googleLoadFailed')));
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
    } catch (e) {
      error.value = e?.message || t('errors.auth.googleLoginFailed');
      loading.value = false;
      return;
    }
    await goAfterLogin();
  };

  const submitGoogle = async () => {
    clearError();
    loading.value = true;
    try {
      const credential = await signInWithNativeGoogle(googleClientId);
      if (!credential) {
        error.value = t('errors.auth.googleFinishFailed');
        loading.value = false;
        return;
      }
      await api.loginWithGoogle(credential);
    } catch (e) {
      error.value = e?.message || t('errors.auth.googleLoginFailed');
      loading.value = false;
      return;
    }
    await goAfterLogin();
  };

  const ensureGoogleIdentity = async () => {
    if (!isWebGoogleEnabled.value || typeof window === 'undefined') return false;
    await ensureGoogleScript();
    if (!window.google?.accounts?.id) return false;
    if (!googleInitialized) {
      window.google.accounts.id.initialize({
        client_id: googleClientId,
        callback: handleGoogleCredential,
        ux_mode: 'popup',
        auto_select: false,
        context: 'signin',
        itp_support: true,
      });
      googleInitialized = true;
    }
    return true;
  };

  const triggerWebGoogleSignIn = async () => {
    clearError();
    if (loading.value) return;
    loading.value = true;
    try {
      const ok = await ensureGoogleIdentity();
      if (!ok) {
        error.value = t('errors.auth.googleLoadFailed');
        return;
      }
      const host = webGoogleButtonHost.value;
      if (!host) return;
      host.innerHTML = '';
      window.google.accounts.id.renderButton(host, {
        type: 'standard',
        theme: 'outline',
        size: 'large',
        text: step.value === 'register' ? 'signup_with' : 'signin_with',
        width: 280,
      });
      await nextTick();
      await new Promise((resolve) => setTimeout(resolve, 50));
      const clickTarget =
        host.querySelector('[role="button"]') ||
        host.querySelector('div[tabindex="0"]') ||
        host.firstElementChild;
      clickTarget?.click();
    } catch (e) {
      error.value = e?.message || t('errors.auth.googleLoadFailed');
    } finally {
      loading.value = false;
    }
  };

  const submitParent = async () => {
    clearError();
    const u = String(username.value || '').trim();
    const p = String(password.value || '');
    if (!u || !p) {
      error.value = t('errors.requiredFields');
      return;
    }
    loading.value = true;
    try {
      await api.loginParent(u, p);
    } catch (e) {
      error.value = e?.message || t('errors.auth.loginFailed');
      loading.value = false;
      return;
    }
    await goAfterLogin();
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
        error.value = t('errors.nameSurnameEmailRequired');
        return;
      }
      if (registerPasswordValue.length < 6) {
        error.value = t('errors.passwordMinLength');
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
      error.value = e?.message || t('errors.auth.registerFailed');
    } finally {
      loading.value = false;
    }
  };

  const submitVerify = async () => {
    clearError();
    loading.value = true;
    const code = String(verificationCode.value || '').replace(/\D+/g, '').slice(0, 6);
    if (code.length !== 6) {
      error.value = t('errors.auth.codeRequired');
      loading.value = false;
      return;
    }
    try {
      await api.verifyParentRegistration({
        email: verifyEmail.value,
        code,
      });
    } catch (e) {
      error.value = e?.message || t('errors.auth.verifyFailed');
      loading.value = false;
      return;
    }
    await goAfterLogin();
  };

  const resendVerificationCode = async () => {
    clearError();
    loading.value = true;
    try {
      await api.resendParentRegistrationCode(verifyEmail.value);
    } catch (e) {
      error.value = e?.message || t('errors.auth.codeSendFailed');
    } finally {
      loading.value = false;
    }
  };

  const submitInvite = async () => {
    clearError();
    loading.value = true;
    const code = String(inviteCode.value || '').trim();
    if (code.length < 12) {
      error.value = t('errors.auth.inviteCodeRequired');
      loading.value = false;
      return;
    }
    const p1 = String(invitePassword.value || '');
    const p2 = String(invitePassword2.value || '');
    if (p1.length < 6) {
      error.value = t('errors.passwordMinLength');
      loading.value = false;
      return;
    }
    if (p1 !== p2) {
      error.value = t('errors.passwordsDoNotMatch');
      loading.value = false;
      return;
    }
    try {
      await api.acceptInvite(code, p1, {
        firstName: inviteFirstName.value,
        lastName: inviteLastName.value,
      });
    } catch (e) {
      error.value = e?.message || t('errors.auth.activationFailed');
      loading.value = false;
      return;
    }
    await goAfterLogin();
  };

  const submitChild = async () => {
    clearError();
    loading.value = true;
    const code = String(childCode.value || '').replace(/\D+/g, '').slice(0, 6);
    if (code.length !== 6) {
      error.value = t('errors.auth.codeRequired');
      loading.value = false;
      return;
    }
    try {
      await api.loginChildByCode(code);
    } catch (e) {
      error.value = e?.message || t('errors.auth.loginFailed');
      loading.value = false;
      return;
    }
    await goAfterLogin();
  };

  const forgotPassword = async () => {
    clearError();
    forgotSuccess.value = '';
    const email = String(forgotEmail.value || '').trim();
    if (!email) {
      error.value = t('errors.emailRequired');
      return;
    }
    loading.value = true;
    try {
      await api.forgotPassword(email);
      forgotSuccess.value = t('success.passwordRecoverySent');
    } catch (e) {
      error.value = e?.message || t('errors.auth.passwordRecoveryFailed');
    } finally {
      loading.value = false;
    }
  };

  onMounted(async () => {
    document.documentElement.style.setProperty('--ru-accent', FAV_GREEN);
    document.documentElement.style.setProperty('--ru-accent-light', FAV_GREEN_LIGHT);

    await nextTick();
    if (isNativeGoogleEnabled.value) {
      try {
        await initializeNativeGoogleSignIn(googleClientId);
      } catch {}
      return;
    }
    if (isWebGoogleEnabled.value) {
      try {
        await ensureGoogleIdentity();
      } catch {}
    }
  });

  return {
    step,
    username,
    password,
    childCode,
    inviteCode,
    invitePassword,
    invitePassword2,
    inviteFirstName,
    inviteLastName,
    registerFirstName,
    registerLastName,
    registerEmail,
    registerPassword,
    verifyEmail,
    verificationCode,
    loading,
    error,
    webGoogleButtonHost,
    showForgotPassword,
    forgotEmail,
    forgotSuccess,
    appVersionLabel,
    isGoogleEnabled,
    isNativeGoogleEnabled,
    isWebGoogleEnabled,
    t,
    goToStep,
    reset,
    openForgotPassword,
    submitParent,
    submitRegister,
    submitVerify,
    resendVerificationCode,
    submitInvite,
    submitChild,
    submitGoogle,
    triggerWebGoogleSignIn,
    forgotPassword,
  };
}
