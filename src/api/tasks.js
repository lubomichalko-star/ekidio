import { http } from './http';

export const tasksApi = {
  list: () => http.get('/tasks'),
  get: (id) => http.get(`/tasks/${id}`),
  save: (task) =>
    http.post('/tasks', {
      id: task.id || 0,
      package_id: task.package_id || '',
      name: task.name || '',
      description: task.description || '',
      days_of_week: (task.days_of_week || []).join(','),
      rating: task.rating || 0,
      task_category: task.task_category || 'povinne',
      rotation_enabled: !!task.rotation_enabled,
      shared_task: !!task.shared_task,
      estimated_time: task.estimated_time || '',
      assigned_children: task.assigned_children || [],
    }),
  delete: (id) => http.del(`/tasks/${id}`),
  updateDays: (taskId, days) =>
    http.post(`/tasks/${taskId}/days`, {
      days_of_week: days.join(','),
    }),
  updateField: (taskId, field, value) =>
    http.post(`/tasks/${taskId}/field`, {
      field,
      value,
    }),
  addChildToTask: (taskId, childId) =>
    http.post(`/tasks/${taskId}/children/add`, { child_id: childId }),
  removeChildFromTask: (taskId, childId) =>
    http.post(`/tasks/${taskId}/children/remove`, { child_id: childId }),
  shiftSingleTask: (taskId, toChildId) =>
    http.post('/admin/shift-task', {
      task_id: Number(taskId || 0),
      to_child_id: Number(toChildId || 0),
    }),

  librarySummary: () => http.get('/tasks/library'),
  importFromLibrary: (selectedIds) =>
    http.post('/tasks/import-library', {
      selected_ids: Array.isArray(selectedIds) ? selectedIds.map((id) => Number(id) || 0).filter(Boolean) : [],
    }),
};

export default tasksApi;

