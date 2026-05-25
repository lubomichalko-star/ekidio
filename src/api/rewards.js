import { http } from './http';

export const rewardsApi = {
  list: () => http.get('/rewards'),
  save: (reward) =>
    http.post('/rewards', {
      id: reward.id || 0,
      title: reward.title || '',
      category: reward.category || '',
      details: reward.details || '',
      icon: reward.icon || '',
      points_cost: reward.points_cost || 0,
    }),
  delete: (id) => http.del(`/rewards/${id}`),
  librarySummary: () => http.get('/rewards/library'),
  importFromLibrary: (selectedIds) =>
    http.post('/rewards/import-library', {
      selected_ids: Array.isArray(selectedIds) ? selectedIds.map((id) => Number(id) || 0).filter(Boolean) : [],
    }),
};

export default rewardsApi;

