export const DAYS_SHORT_SK = [
  { value: 1, label: 'PO' },
  { value: 2, label: 'UT' },
  { value: 3, label: 'ST' },
  { value: 4, label: 'ŠT' },
  { value: 5, label: 'PI' },
  { value: 6, label: 'SO' },
  { value: 0, label: 'NE' },
];

// Map JS getDay(): 0..6 -> "weekday order index" where Monday is first.
export const DAY_ORDER = {
  1: 0,
  2: 1,
  3: 2,
  4: 3,
  5: 4,
  6: 5,
  0: 6,
};

export function toYmd(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/** Monday (Y-m-d) of the week containing `date`. */
export function getWeekStartForDate(date = new Date()) {
  const d = new Date(date);
  if (Number.isNaN(d.getTime())) return '';
  d.setHours(12, 0, 0, 0);
  const dow = d.getDay();
  const diff = dow === 0 ? -6 : 1 - dow;
  d.setDate(d.getDate() + diff);
  return toYmd(d);
}

export function ymdForWeekDay(weekStart, dayW) {
  const ws = String(weekStart || '');
  const day = Number(dayW);
  const offsets = { 1: 0, 2: 1, 3: 2, 4: 3, 5: 4, 6: 5, 0: 6 };
  if (!ws || !Object.prototype.hasOwnProperty.call(offsets, day)) return '';
  const base = new Date(`${ws}T12:00:00`);
  if (Number.isNaN(base.getTime())) return '';
  base.setDate(base.getDate() + offsets[day]);
  return toYmd(base);
}

export function addWeeksToWeekStart(weekStart, weeks) {
  const ws = String(weekStart || '');
  if (!ws) return '';
  const d = new Date(`${ws}T12:00:00`);
  if (Number.isNaN(d.getTime())) return '';
  d.setDate(d.getDate() + Number(weeks || 0) * 7);
  return toYmd(d);
}

export function formatDayPillParts(dayW, weekStart) {
  const meta = DAYS_SHORT_SK.find((d) => Number(d.value) === Number(dayW));
  const label = meta?.label || '';
  const ymd = ymdForWeekDay(weekStart, dayW);
  if (!ymd || !label) return { label, date: '' };
  const [, month, day] = ymd.split('-');
  return { label, date: `${Number(day)}.${Number(month)}` };
}

/** @deprecated use formatDayPillParts for two-line pills */
export function formatDayPillLabel(dayW, weekStart) {
  const { label, date } = formatDayPillParts(dayW, weekStart);
  if (!date) return label;
  return `${label} ${date}`;
}

export function isYmdPastOrToday(ymd) {
  const target = String(ymd || '');
  if (!target) return false;
  return target <= toYmd(new Date());
}

/**
 * Returns true when `day` is in the past (or today) relative to `todayDay`.
 * Both days use JS Date.getDay() convention: 0=Sunday ... 6=Saturday.
 */
export function isDayPastOrToday(day, todayDay) {
  const todayIdx = Object.prototype.hasOwnProperty.call(DAY_ORDER, todayDay)
    ? DAY_ORDER[todayDay]
    : 0;
  const dayIdx = Object.prototype.hasOwnProperty.call(DAY_ORDER, day)
    ? DAY_ORDER[day]
    : todayIdx;
  return dayIdx <= todayIdx;
}

