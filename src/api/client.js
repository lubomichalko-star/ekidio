import { http } from './http';
import { getToken, setToken, clearToken } from '../auth/tokenStorage';
import { setStoredAuth, clearStoredAuth, dispatchAuthChanged, getStoredAuth } from '../auth/authState';
import { signOutNativeGoogle } from '../auth/googleAuth';

async function requireToken() {
  const token = await getToken();
  if (!token) {
    const err = new Error('Nie ste prihlásený');
    err.status = 401;
    throw err;
  }
}

export const api = {
  me: () => http.get('/auth/me'),

  loginParent: async (username, password) => {
    const res = await http.post('/auth/login', { username, password });
    if (res?.token) await setToken(res.token);
    if (res?.subject?.type === 'parent') {
      setStoredAuth({ role: 'parent', childId: '' });
      dispatchAuthChanged({ role: 'parent', childId: '' });
    }
    return res;
  },

  registerParent: async ({ firstName = '', lastName = '', email = '', password = '' } = {}) => {
    return http.post('/auth/register', {
      first_name: String(firstName || ''),
      last_name: String(lastName || ''),
      email: String(email || '').trim(),
      password: String(password || ''),
    });
  },

  verifyParentRegistration: async ({ email = '', code = '' } = {}) => {
    const res = await http.post('/auth/register/verify', {
      email: String(email || '').trim(),
      code: String(code || '').trim(),
    });
    if (res?.token) await setToken(res.token);
    if (res?.subject?.type === 'parent') {
      setStoredAuth({ role: 'parent', childId: '' });
      dispatchAuthChanged({ role: 'parent', childId: '' });
    }
    return res;
  },

  resendParentRegistrationCode: async (email) => {
    return http.post('/auth/register/resend', {
      email: String(email || '').trim(),
    });
  },

  forgotPassword: async (email) => {
    return http.post('/auth/forgot-password', {
      email: String(email || '').trim(),
    });
  },

  loginWithGoogle: async (credential) => {
    const res = await http.post('/auth/google', {
      credential: String(credential || '').trim(),
    });
    if (res?.token) await setToken(res.token);
    if (res?.subject?.type === 'parent') {
      setStoredAuth({ role: 'parent', childId: '' });
      dispatchAuthChanged({ role: 'parent', childId: '' });
    }
    return res;
  },

  loginChildByCode: async (code) => {
    const res = await http.post('/auth/login', { child_code: String(code || '') });
    if (res?.token) await setToken(res.token);
    if (res?.subject?.type === 'child') {
      const cid = String(res.subject.child_id || '');
      setStoredAuth({ role: 'child', childId: cid });
      dispatchAuthChanged({ role: 'child', childId: cid });
    }
    return res;
  },

  acceptInvite: async (token, password, { firstName = '', lastName = '' } = {}) => {
    const res = await http.post('/auth/invite/accept', {
      token: String(token || '').trim(),
      password: String(password || ''),
      first_name: String(firstName || ''),
      last_name: String(lastName || ''),
    });
    if (res?.token) await setToken(res.token);
    if (res?.subject?.type === 'parent') {
      setStoredAuth({ role: 'parent', childId: '' });
      dispatchAuthChanged({ role: 'parent', childId: '' });
    }
    return res;
  },

  listFamilyInvites: async () => {
    await requireToken();
    return http.get('/family/invites');
  },
  createFamilyInvite: async (email) => {
    await requireToken();
    return http.post('/family/invites', { email: String(email || '') });
  },
  listFamilyMembers: async () => {
    await requireToken();
    return http.get('/family/members');
  },
  revokeFamilyInvite: async (inviteId) => {
    await requireToken();
    return http.post('/family/invites/revoke', { id: Number(inviteId || 0) });
  },

  // Parent (admin) assignment status update
  updateTaskStatus: (assignmentId, status) =>
    http.post(`/assignments/${assignmentId}/status`, { status }),

  // Child status update (also works for parent token)
  updateChildTaskStatus: async (assignmentId, status) => {
    await requireToken();
    return http.post('/child/task-status', { assignment_id: assignmentId, status });
  },

  saveChildAvatar: async (childId, avatarUrl) => {
    await requireToken();
    return http.post(
      '/child/avatar',
      { avatar_url: avatarUrl },
      { query: { child_id: childId || 0 } }
    );
  },

  getChildOverview: async (childId, day = null, weekStart = null) => {
    try {
      const query = { child_id: childId || 0 };
      if (day !== null && day !== undefined) query.day = day;
      if (weekStart) query.week_start = weekStart;
      return await http.get('/child/overview', { query });
    } catch (e) {
      // Only clear session for child role (deleted child) or true auth failure.
      try {
        const role = getStoredAuth()?.role || '';
        if (e && e.status === 401) {
          await clearToken();
          clearStoredAuth();
          dispatchAuthChanged({ role: 'child', childId: '' });
        } else if (role === 'child' && e && (e.status === 403 || e.status === 404)) {
          await clearToken();
          clearStoredAuth();
          dispatchAuthChanged({ role: 'child', childId: '' });
        }
      } catch {}
      throw e;
    }
  },

  purchaseReward: async (childId, rewardId) => {
    await requireToken();
    return http.post(
      '/child/rewards/purchase',
      { reward_id: rewardId },
      { query: { child_id: childId || 0 } }
    );
  },

  markRewardUsed: (purchaseId) =>
    http.post(`/rewards/purchases/${purchaseId}/use`, {}),

  uploadChildAvatar: async (file) => {
    await requireToken();
    const form = new FormData();
    form.append('avatar', file);
    return http.postForm('/child/avatar/upload', form);
  },

  setChildColor: async (childId, color) => {
    await requireToken();
    return http.post(
      '/child/color',
      { color },
      { query: { child_id: childId || 0 } }
    );
  },

  changeParentPassword: async (currentPassword, newPassword) => {
    await requireToken();
    return http.post('/parent/password', {
      current_password: String(currentPassword || ''),
      new_password: String(newPassword || ''),
    });
  },

  deleteParentAccount: async ({ currentPassword = '', confirmText = '' } = {}) => {
    await requireToken();
    return http.post('/parent/account/delete', {
      current_password: String(currentPassword || ''),
      confirm_text: String(confirmText || ''),
    });
  },

  regenerateWeek: () => http.post('/admin/regenerate-week', {}),
  shiftRotation: () => http.post('/admin/shift-rotation', {}),

  getRotationSettings: () => http.get('/admin/rotation-settings'),
  saveRotationSettings: (frequency, day) =>
    http.post('/admin/rotation-settings', {
      frequency: String(frequency || 'weekly'),
      day: String(day || 'monday'),
    }),

  getWeekendMultiplier: () => http.get('/admin/weekend-multiplier'),
  saveWeekendMultiplier: (multiplier) =>
    http.post('/admin/weekend-multiplier', { multiplier }),

  resetChildren: async () => {
    await requireToken();
    return http.post('/admin/reset/children', {});
  },
  resetTasks: async () => {
    await requireToken();
    return http.post('/admin/reset/tasks', {});
  },
  resetRewards: async () => {
    await requireToken();
    return http.post('/admin/reset/rewards', {});
  },

  sendFeedback: async (text, path = '') => {
    await requireToken();
    return http.post('/feedback', { text: String(text || ''), path: String(path || '') });
  },

  logout: async () => {
    try {
      await http.post('/auth/logout', {});
    } catch (e) {
      // ignore network/auth errors, still clear local token
    } finally {
      await signOutNativeGoogle();
      await clearToken();
    }
    clearStoredAuth();
    dispatchAuthChanged({ role: 'child', childId: '' });
    return { ok: true };
  },
};

export default api;

