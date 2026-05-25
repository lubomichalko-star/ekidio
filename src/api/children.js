import { http } from './http';

export const childrenApi = {
  list: () => http.get('/children'),
  save: (child) =>
    http.post('/children', {
      id: child.id || 0,
      child_id: child.id || 0,
      child_name: child.name || '',
      child_email: child.email || '',
      child_password: child.password || '',
      child_avatar_url: child.avatar_url || '',
      child_color: child.color || '#4CAF50',
    }),
  delete: (id) =>
    http.del(`/children/${id}`),
  reorder: (ids) =>
    http.post('/children/reorder', { ids: Array.isArray(ids) ? ids : [] }),
};

export default childrenApi;

