export function getAppReleaseVersion(localized = {}) {
  if (typeof __APP_RELEASE_VERSION__ !== 'undefined' && __APP_RELEASE_VERSION__) {
    return String(__APP_RELEASE_VERSION__);
  }

  const fromLocalized = localized?.appVersion || '';
  const fromWp =
    typeof window !== 'undefined' && window.rodinneUlohyApp?.appVersion
      ? window.rodinneUlohyApp.appVersion
      : '';

  return String(fromLocalized || fromWp || '0.0.0');
}

export function getAppVersionLabel(localized = {}) {
  const version = getAppReleaseVersion(localized);
  return version && version !== '0.0.0' ? version : '';
}
