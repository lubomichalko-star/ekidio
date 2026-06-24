const modules = import.meta.glob('../assets/task-icons/*.{png,svg,jpg,jpeg,webp}', {
  eager: true,
  import: 'default',
});

export const taskIconOptions = Object.entries(modules)
  .map(([path, url]) => {
    const file = path.split('/').pop() || '';
    const id = file.replace(/\.[^.]+$/, '');
    return { id, url, file };
  })
  .sort((a, b) => a.id.localeCompare(b.id, 'sk'));

export function getTaskIconUrl(iconId) {
  const id = String(iconId || '').trim();
  if (!id) return '';
  const found = taskIconOptions.find((opt) => opt.id === id);
  return found?.url || '';
}

export function getDefaultTaskIconId() {
  return taskIconOptions[0]?.id || '';
}
