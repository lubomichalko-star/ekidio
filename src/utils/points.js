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

function pointsWord(count) {
  const n = Math.abs(Number(count) || 0);
  if (n === 1) return 'bod';
  if (n >= 2 && n <= 4) return 'body';
  return 'bodov';
}

export function formatSignedPoints(value) {
  const n = Number(value) || 0;
  const sign = n >= 0 ? '+' : '-';
  const abs = Math.abs(n);
  return `${sign}${abs} ${pointsWord(abs)}`;
}

/**
 * Label for points for a given assignment/task.
 * - Voluntary: rating
 * - Mandatory: "rating | -penalty" (penalty can be multiplied on Saturday-only tasks)
 */
export function pointLabel(item, isMandatory, selectedDay, weekendMultiplier = 1) {
  const rating = Number(item?.task_rating ?? item?.rating) || 0;
  if (!rating) return '0';

  if (!isMandatory) return `${rating}`;

  const mult = Number(weekendMultiplier) > 0 ? Number(weekendMultiplier) : 1;
  const applyMult = Number(selectedDay) === 6 && isSaturdayOnlyTask(item);
  const penalty = Math.round(rating * (applyMult ? mult : 1));
  return `${rating} | -${penalty}`;
}

/** Human-friendly earn label, e.g. "+9 bodov" or "+4 body". */
export function taskPointsEarnLabel(item) {
  const rating = Number(item?.task_rating ?? item?.rating) || 0;
  return formatSignedPoints(rating);
}

