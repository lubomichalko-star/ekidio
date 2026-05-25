export function isSaturdayOnlyTask(item) {
  const daysStr = item?.days_of_week || item?.days || '';
  if (daysStr) {
    const days = String(daysStr)
      .split(',')
      .map((d) => parseInt(String(d).trim(), 10))
      .filter((n) => Number.isFinite(n) && !Number.isNaN(n));
    return days.length === 1 && days[0] === 6;
  }
  return String(item?.task_type || '').toLowerCase() === 'weekend';
}

/**
 * Label for points for a given assignment/task.
 * - Voluntary: rating
 * - Mandatory: "rating | -penalty" (penalty can be multiplied on Saturday-only tasks)
 */
export function pointLabel(item, isMandatory, selectedDay, weekendMultiplier = 1) {
  const rating = Number(item?.task_rating) || 0;
  if (!rating) return '0';

  if (!isMandatory) return `${rating}`;

  const mult = Number(weekendMultiplier) > 0 ? Number(weekendMultiplier) : 1;
  const applyMult = Number(selectedDay) === 6 && isSaturdayOnlyTask(item);
  const penalty = Math.round(rating * (applyMult ? mult : 1));
  return `${rating} | -${penalty}`;
}

