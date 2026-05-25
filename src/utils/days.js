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

