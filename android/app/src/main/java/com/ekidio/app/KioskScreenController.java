package com.ekidio.app;

import android.animation.Animator;
import android.animation.AnimatorListenerAdapter;
import android.animation.ValueAnimator;
import android.app.Activity;
import android.content.Context;
import android.graphics.Color;
import android.os.Build;
import android.os.PowerManager;
import android.os.SystemClock;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.WindowManager;
import android.view.animation.LinearInterpolator;

import com.getcapacitor.BridgeActivity;

/**
 * Fades to black, then turns the display off while keeping CPU awake for camera wake.
 */
final class KioskScreenController {

  private static final long FADE_DURATION_MS = 1000;

  private final Activity activity;
  private View blackOverlay = null;
  private PowerManager.WakeLock partialWakeLock = null;
  private Runnable userActivityCallback = null;
  private ValueAnimator fadeAnimator = null;

  private final View.OnTouchListener touchListener = (v, event) -> {
    int action = event.getActionMasked();
    if (action == MotionEvent.ACTION_DOWN
        || action == MotionEvent.ACTION_MOVE
        || action == MotionEvent.ACTION_UP) {
      notifyUserActivity();
    }
    return false;
  };

  KioskScreenController(Activity activity) {
    this.activity = activity;
  }

  void setUserActivityCallback(Runnable callback) {
    userActivityCallback = callback;
  }

  void start() {
    acquirePartialWakeLock();
    attachTouchListeners();
  }

  void stop() {
    detachTouchListeners();
    releasePartialWakeLock();
    snapOn();
  }

  void turnOn() {
    cancelFade();
    wakeScreen();
    setKeepScreenOn(true);
    restoreSystemBrightness();

    if (blackOverlay == null || blackOverlay.getVisibility() != View.VISIBLE) {
      return;
    }

    float startAlpha = blackOverlay.getAlpha();
    if (startAlpha <= 0.01f) {
      hideBlackOverlay();
      return;
    }

    fadeAnimator = ValueAnimator.ofFloat(startAlpha, 0f);
    fadeAnimator.setDuration(FADE_DURATION_MS);
    fadeAnimator.setInterpolator(new LinearInterpolator());
    fadeAnimator.addUpdateListener(animation -> {
      float alpha = (float) animation.getAnimatedValue();
      blackOverlay.setAlpha(alpha);
      applyBrightnessWhileFadingOn(alpha);
    });
    fadeAnimator.addListener(new AnimatorListenerAdapter() {
      @Override
      public void onAnimationEnd(Animator animation) {
        fadeAnimator = null;
        hideBlackOverlay();
        restoreSystemBrightness();
      }
    });
    fadeAnimator.start();
  }

  void turnOff() {
    cancelFade();
    setKeepScreenOn(false);
    ensureBlackOverlay();
    blackOverlay.setAlpha(0f);
    blackOverlay.setVisibility(View.VISIBLE);
    blackOverlay.bringToFront();

    fadeAnimator = ValueAnimator.ofFloat(0f, 1f);
    fadeAnimator.setDuration(FADE_DURATION_MS);
    fadeAnimator.setInterpolator(new LinearInterpolator());
    fadeAnimator.addUpdateListener(animation -> {
      float alpha = (float) animation.getAnimatedValue();
      blackOverlay.setAlpha(alpha);
      applyBrightnessWhileFadingOff(alpha);
    });
    fadeAnimator.addListener(new AnimatorListenerAdapter() {
      @Override
      public void onAnimationEnd(Animator animation) {
        fadeAnimator = null;
        requestDisplaySleep();
      }
    });
    fadeAnimator.start();
  }

  private void snapOn() {
    cancelFade();
    hideBlackOverlay();
    setKeepScreenOn(true);
    restoreSystemBrightness();
    wakeScreen();
  }

  private void cancelFade() {
    if (fadeAnimator != null) {
      fadeAnimator.cancel();
      fadeAnimator = null;
    }
  }

  private void wakeScreen() {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
      activity.setShowWhenLocked(true);
      activity.setTurnScreenOn(true);
    }

    Window window = activity.getWindow();
    if (window != null) {
      window.addFlags(
          WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON
              | WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED
      );
    }

    PowerManager pm = (PowerManager) activity.getSystemService(Context.POWER_SERVICE);
    if (pm == null) {
      return;
    }
    try {
      PowerManager.WakeLock wl = pm.newWakeLock(
          PowerManager.SCREEN_BRIGHT_WAKE_LOCK
              | PowerManager.ACQUIRE_CAUSES_WAKEUP
              | PowerManager.ON_AFTER_RELEASE,
          "ekidio:kiosk:screen"
      );
      wl.acquire(3000);
      wl.release();
    } catch (Exception ignored) {
    }
  }

  private void requestDisplaySleep() {
    setKeepScreenOn(false);
    ensureBlackOverlay();
    blackOverlay.setAlpha(1f);
    blackOverlay.setVisibility(View.VISIBLE);
    blackOverlay.bringToFront();
    applyMinBrightness();

    PowerManager pm = (PowerManager) activity.getSystemService(Context.POWER_SERVICE);
    if (pm == null) {
      return;
    }

    try {
      pm.getClass().getMethod("goToSleep", long.class).invoke(pm, SystemClock.uptimeMillis());
    } catch (ReflectiveOperationException | SecurityException ignored) {
      // Regular apps cannot call goToSleep – black overlay + min brightness stays active.
    }
  }

  private void restoreSystemBrightness() {
    Window window = activity.getWindow();
    if (window == null) {
      return;
    }
    WindowManager.LayoutParams lp = window.getAttributes();
    lp.screenBrightness = WindowManager.LayoutParams.BRIGHTNESS_OVERRIDE_NONE;
    window.setAttributes(lp);
  }

  private void applyMinBrightness() {
    Window window = activity.getWindow();
    if (window == null) {
      return;
    }
    WindowManager.LayoutParams lp = window.getAttributes();
    lp.screenBrightness = WindowManager.LayoutParams.BRIGHTNESS_OVERRIDE_OFF;
    window.setAttributes(lp);
  }

  private void applyBrightnessWhileFadingOff(float overlayAlpha) {
    Window window = activity.getWindow();
    if (window == null) {
      return;
    }
    WindowManager.LayoutParams lp = window.getAttributes();
    float brightness = 1f - overlayAlpha;
    if (brightness <= 0.01f) {
      lp.screenBrightness = WindowManager.LayoutParams.BRIGHTNESS_OVERRIDE_OFF;
    } else {
      lp.screenBrightness = brightness;
    }
    window.setAttributes(lp);
  }

  private void applyBrightnessWhileFadingOn(float overlayAlpha) {
    Window window = activity.getWindow();
    if (window == null) {
      return;
    }
    WindowManager.LayoutParams lp = window.getAttributes();
    float brightness = 1f - overlayAlpha;
    if (brightness <= 0.01f) {
      lp.screenBrightness = WindowManager.LayoutParams.BRIGHTNESS_OVERRIDE_OFF;
    } else {
      lp.screenBrightness = brightness;
    }
    window.setAttributes(lp);
  }

  private void notifyUserActivity() {
    if (userActivityCallback != null) {
      userActivityCallback.run();
    }
  }

  private void attachTouchListeners() {
    Window window = activity.getWindow();
    if (window == null) {
      return;
    }
    View decor = window.getDecorView();
    decor.setOnTouchListener(touchListener);

    if (activity instanceof BridgeActivity bridgeActivity) {
      try {
        View webView = bridgeActivity.getBridge().getWebView();
        if (webView != null) {
          webView.setOnTouchListener(touchListener);
        }
      } catch (Exception ignored) {
      }
    }
  }

  private void detachTouchListeners() {
    Window window = activity.getWindow();
    if (window != null) {
      window.getDecorView().setOnTouchListener(null);
    }
    if (activity instanceof BridgeActivity bridgeActivity) {
      try {
        View webView = bridgeActivity.getBridge().getWebView();
        if (webView != null) {
          webView.setOnTouchListener(null);
        }
      } catch (Exception ignored) {
      }
    }
    if (blackOverlay != null) {
      blackOverlay.setOnTouchListener(null);
    }
  }

  private void setKeepScreenOn(boolean on) {
    Window window = activity.getWindow();
    if (window == null) {
      return;
    }

    View decor = window.getDecorView();
    decor.setKeepScreenOn(on);
    if (on) {
      window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
    } else {
      window.clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
    }

    if (activity instanceof BridgeActivity bridgeActivity) {
      try {
        View webView = bridgeActivity.getBridge().getWebView();
        if (webView != null) {
          webView.setKeepScreenOn(on);
        }
      } catch (Exception ignored) {
      }
    }
  }

  private void ensureBlackOverlay() {
    ViewGroup decor = (ViewGroup) activity.getWindow().getDecorView();
    if (blackOverlay == null) {
      blackOverlay = new View(activity);
      blackOverlay.setBackgroundColor(Color.BLACK);
      blackOverlay.setElevation(10000f);
      blackOverlay.setClickable(true);
      blackOverlay.setFocusable(true);
      blackOverlay.setOnTouchListener((v, event) -> {
        notifyUserActivity();
        return true;
      });
    }
    if (blackOverlay.getParent() == null) {
      decor.addView(
          blackOverlay,
          new ViewGroup.LayoutParams(
              ViewGroup.LayoutParams.MATCH_PARENT,
              ViewGroup.LayoutParams.MATCH_PARENT
          )
      );
    }
  }

  private void hideBlackOverlay() {
    if (blackOverlay != null) {
      blackOverlay.setAlpha(0f);
      blackOverlay.setVisibility(View.GONE);
    }
  }

  private void acquirePartialWakeLock() {
    if (partialWakeLock != null && partialWakeLock.isHeld()) {
      return;
    }
    PowerManager pm = (PowerManager) activity.getSystemService(Context.POWER_SERVICE);
    if (pm == null) {
      return;
    }
    try {
      partialWakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "ekidio:kiosk:camera");
      partialWakeLock.setReferenceCounted(false);
      partialWakeLock.acquire();
    } catch (Exception ignored) {
      partialWakeLock = null;
    }
  }

  private void releasePartialWakeLock() {
    if (partialWakeLock == null) {
      return;
    }
    try {
      if (partialWakeLock.isHeld()) {
        partialWakeLock.release();
      }
    } catch (Exception ignored) {
    }
    partialWakeLock = null;
  }
}
