import { http } from './http';

export const pointsApi = {
  overview: (childId) =>
    http.get('/points/overview', { query: { child_id: childId || 0 } }),
  add: (childId, points, reason) =>
    http.post('/points/add', {
      child_id: childId,
      points,
      reason,
    }),
  deduct: (childId, points, reason) =>
    http.post('/points/deduct', {
      child_id: childId,
      points,
      reason,
    }),
  deleteEntry: (entryId) =>
    http.del(`/points/entry/${entryId}`),
};

export default pointsApi;

